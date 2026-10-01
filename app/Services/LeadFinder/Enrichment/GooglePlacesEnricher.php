<?php

namespace App\Services\LeadFinder\Enrichment;

use App\Helpers\StringHelper;

class GooglePlacesEnricher
{
    private static $textSearchUrl = 'https://places.googleapis.com/v1/places:searchText';
    private static $placeDetailsUrl = 'https://places.googleapis.com/v1/places/';
    private static $cacheDir = __DIR__ . '/../../../../storage/cache/google_places/';
    private static $logFile = __DIR__ . '/../../../../storage/logs/google_places.log';
    private static $placeIdCacheTtl = 2592000; // 30 days in seconds (30 * 86400)
    private static $pendingTtl = 3600; // 1 hour for medium match confirmation token

    /**
     * Enrich a lead using Google Places API (New) for identity/website resolution,
     * then extract contact data via WebsiteEnricher without permanently storing Google content.
     *
     * @param string $companyName
     * @param string $city
     * @param string $district
     * @return array
     */
    public static function enrich($companyName, $city = 'Konya', $district = '')
    {
        $companyName = trim($companyName);
        if (empty($companyName)) {
            return [
                'success' => false,
                'status' => 'empty_query',
                'message' => 'Firma adı belirtilmedi.',
                'api_stats' => ['text_search_calls' => 0, 'place_details_calls' => 0, 'place_id_cache_hit' => false]
            ];
        }

        $coreName = self::normalizeCoreName($companyName);
        $normCity = StringHelper::normalizeTurkish(mb_strtolower($city ?: 'Konya'));
        $cacheKey = md5($coreName . '_' . $normCity);
        $cacheFile = self::$cacheDir . $cacheKey . '.json';

        // 1. Check Place ID Cache (Policy-compliant: ONLY place_id and metadata stored)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < self::$placeIdCacheTtl)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (!empty($cached['google_place_id'])) {
                // Place ID Cache Hit! Skip Text Search entirely (0 Text Search, 1 Place Details)
                return self::fetchDetailsAndEnrich(
                    $cached['google_place_id'],
                    $coreName,
                    $cacheFile,
                    0, // 0 text search calls
                    true // place_id_cache_hit = true
                );
            }
        }

        // 2. Check API Key & Configuration
        if (!self::isConfigured()) {
            return [
                'success' => false,
                'status' => 'not_configured',
                'confidence' => 'NONE',
                'message' => 'Google Places API anahtarı gerekli (config.php içinde yapılandırılmadı veya devre dışı).',
                'api_stats' => ['text_search_calls' => 0, 'place_details_calls' => 0, 'place_id_cache_hit' => false]
            ];
        }
        $apiKey = self::getApiKey();

        if (defined('GOOGLE_PLACES_ENABLED') && !GOOGLE_PLACES_ENABLED) {
            return [
                'success' => false,
                'status' => 'not_configured',
                'confidence' => 'NONE',
                'message' => 'Google Places entegrasyonu devre dışı bırakılmış.',
                'api_stats' => ['text_search_calls' => 0, 'place_details_calls' => 0, 'place_id_cache_hit' => false]
            ];
        }

        // 3. Search via Google Places (New) Text Search
        $districtClean = trim($district);
        $cityClean = trim($city ?: 'Konya');
        $queryText = trim("{$companyName} " . ($districtClean ? "{$districtClean} " : "") . "{$cityClean} Türkiye");

        $searchResult = self::executeTextSearch($queryText, $apiKey);

        if (!$searchResult['success']) {
            $searchResult['api_stats'] = ['text_search_calls' => 1, 'place_details_calls' => 0, 'place_id_cache_hit' => false];
            self::logApiCall($coreName, 1, 0, false, 'ERROR');
            return $searchResult;
        }

        $candidates = $searchResult['candidates'] ?? [];
        if (empty($candidates)) {
            self::logApiCall($coreName, 1, 0, false, 'NONE');
            return [
                'success' => false,
                'status' => 'not_found',
                'confidence' => 'NONE',
                'message' => 'Google Places üzerinde eşleşen işletme bulunamadı.',
                'api_stats' => ['text_search_calls' => 1, 'place_details_calls' => 0, 'place_id_cache_hit' => false]
            ];
        }

        // 4. Calculate Confidence for candidates
        $evaluated = [];
        $candidates = array_slice($candidates, 0, 5);

        foreach ($candidates as $cand) {
            $candName = $cand['displayName']['text'] ?? '';
            $candAddr = $cand['formattedAddress'] ?? '';
            $conf = self::calculateConfidence($companyName, $candName, $candAddr, $cityClean);
            $evaluated[] = [
                'place_id' => $cand['id'] ?? '',
                'confidence' => $conf['level'],
                'score' => $conf['score'],
                'display_name' => $candName,
                'formatted_address' => $candAddr
            ];
        }

        usort($evaluated, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $best = $evaluated[0];

        // 5. Handle LOW Confidence: Strictly reject (no storage, no bypass)
        if ($best['confidence'] === 'LOW') {
            self::logApiCall($coreName, 1, 0, false, 'LOW');
            return [
                'success' => false,
                'status' => 'low_confidence',
                'confidence' => 'LOW',
                'score' => $best['score'],
                'message' => 'Eşleşme bulunamadı / düşük güven (otomatik kayıt yapılmaz).',
                'api_stats' => ['text_search_calls' => 1, 'place_details_calls' => 0, 'place_id_cache_hit' => false]
            ];
        }

        // 6. Handle MEDIUM Confidence: Return confirmation token without storing Google content in server cache
        if ($best['confidence'] === 'MEDIUM') {
            $matchToken = bin2hex(random_bytes(16));
            // Store ONLY token, place_id, normalized_company and timestamp in pending storage
            self::savePendingToken($matchToken, [
                'place_id' => $best['place_id'],
                'normalized_company' => $coreName,
                'cache_file' => $cacheFile,
                'score' => $best['score'],
                'timestamp' => time()
            ]);

            self::logApiCall($coreName, 1, 0, false, 'MEDIUM_PENDING');

            return [
                'success' => true,
                'status' => 'medium_confidence',
                'confidence' => 'MEDIUM',
                'confidence_level' => 'MEDIUM',
                'confidence_score' => $best['score'],
                'score' => $best['score'],
                'match_token' => $matchToken,
                'candidate' => [
                    'display_name' => $best['display_name'],
                    'formatted_address' => $best['formatted_address']
                ],
                'attribution' => 'Google Maps',
                'message' => 'Muhtemel Google Places eşleşmesi bulundu, onayınız bekleniyor.',
                'api_stats' => ['text_search_calls' => 1, 'place_details_calls' => 0, 'place_id_cache_hit' => false]
            ];
        }

        // 7. HIGH Match: Save Place ID to cache & fetch details immediately (1 Text Search, 1 Place Details)
        self::savePlaceIdCache($cacheFile, $coreName, $best['place_id']);
        return self::fetchDetailsAndEnrich($best['place_id'], $coreName, $cacheFile, 1, false, 'HIGH', $best['score']);
    }

    /**
     * Confirm a medium match using the verified match token without repeating Text Search
     *
     * @param string $matchToken
     * @return array
     */
    public static function confirmMatch($matchToken)
    {
        $pending = self::getPendingToken($matchToken);
        if (!$pending) {
            return [
                'success' => false,
                'status' => 'invalid_token',
                'message' => 'Geçersiz veya süresi dolmuş eşleşme oturumu.',
                'api_stats' => ['text_search_calls' => 0, 'place_details_calls' => 0, 'place_id_cache_hit' => false]
            ];
        }

        $placeId = $pending['place_id'];
        $coreName = $pending['normalized_company'];
        $cacheFile = $pending['cache_file'];
        $score = $pending['score'] ?? 0.6;

        // Remove single-use pending token
        self::deletePendingToken($matchToken);

        // Save verified Place ID to 30-day cache
        self::savePlaceIdCache($cacheFile, $coreName, $placeId);

        // Fetch Place Details and run WebsiteEnricher (0 Text Search, 1 Place Details)
        return self::fetchDetailsAndEnrich($placeId, $coreName, $cacheFile, 0, false, 'MEDIUM_CONFIRMED', $score);
    }

    /**
     * Execute Place Details in-memory, discover websiteUri, crawl with WebsiteEnricher,
     * and return website-derived contact data without storing Google content.
     */
    private static function fetchDetailsAndEnrich($placeId, $coreName, $cacheFile, $textSearchCount, $cacheHit, $confidence = 'HIGH', $score = 1.0)
    {
        $apiKey = self::getApiKey();
        $detailsResult = self::executePlaceDetails($placeId, $apiKey);

        if (!$detailsResult['success']) {
            $detailsResult['api_stats'] = [
                'text_search_calls' => $textSearchCount,
                'place_details_calls' => 1,
                'place_id_cache_hit' => $cacheHit
            ];
            self::logApiCall($coreName, $textSearchCount, 1, $cacheHit, 'DETAILS_ERROR');
            return $detailsResult;
        }

        $placeDetails = $detailsResult['data'];
        $websiteUri = $placeDetails['websiteUri'] ?? '';
        $candName = $placeDetails['displayName']['text'] ?? '';
        $candAddress = $placeDetails['formattedAddress'] ?? '';

        // Transient Google preview for immediate UI attribution only (never saved to CRM)
        $googlePreview = [
            'display_name' => $candName,
            'phone' => $placeDetails['nationalPhoneNumber'] ?? $placeDetails['internationalPhoneNumber'] ?? '',
            'address' => $candAddress,
            'attribution' => 'Google Maps'
        ];

        $candidateInfo = [
            'display_name' => $candName,
            'formatted_address' => $candAddress
        ];

        // 1. If Google has NO websiteUri
        if (empty($websiteUri)) {
            self::logApiCall($coreName, $textSearchCount, 1, $cacheHit, 'MATCHED_NO_WEBSITE');
            return [
                'success' => true,
                'status' => 'google_matched_no_website',
                'confidence' => $confidence,
                'confidence_level' => $confidence,
                'confidence_score' => $score,
                'candidate' => $candidateInfo,
                'google_place_id' => $placeId,
                'website' => '',
                'phone' => '',
                'email' => '',
                'address' => '',
                'instagram' => '',
                'facebook' => '',
                'linkedin' => '',
                'youtube' => '',
                'source_trace' => '',
                'google_preview' => $googlePreview,
                'message' => 'İşletme eşleşti ancak web sitesi bulunamadı. Google içeriği CRM\'e kaydedilmedi.',
                'api_stats' => [
                    'text_search_calls' => $textSearchCount,
                    'place_details_calls' => 1,
                    'place_id_cache_hit' => $cacheHit
                ]
            ];
        }

        // 2. Website found: Crawl company's official website using WebsiteEnricher
        $webData = WebsiteEnricher::enrich($websiteUri);
        $finalWebsiteUrl = $websiteUri;

        $phone = '';
        $email = '';
        $address = '';
        $instagram = '';
        $facebook = '';
        $linkedin = '';
        $youtube = '';

        if (!empty($webData) && is_array($webData)) {
            $phone = $webData['phone'] ?? '';
            $email = $webData['email'] ?? '';
            $address = $webData['address'] ?? '';
            $instagram = $webData['instagram'] ?? '';
            $facebook = $webData['facebook'] ?? '';
            $linkedin = $webData['linkedin'] ?? '';
            $youtube = $webData['youtube'] ?? '';
        }

        self::logApiCall($coreName, $textSearchCount, 1, $cacheHit, 'SUCCESS');

        return [
            'success' => true,
            'status' => 'success',
            'confidence' => $confidence,
            'confidence_level' => $confidence,
            'confidence_score' => $score,
            'candidate' => $candidateInfo,
            'google_place_id' => $placeId,
            'website' => $finalWebsiteUrl,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'instagram' => $instagram,
            'facebook' => $facebook,
            'linkedin' => $linkedin,
            'youtube' => $youtube,
            'source_trace' => 'Website',
            'website_data' => $webData ?: [],
            'google_preview' => $googlePreview,
            'api_stats' => [
                'text_search_calls' => $textSearchCount,
                'place_details_calls' => 1,
                'place_id_cache_hit' => $cacheHit
            ]
        ];
    }

    private static function executeTextSearch($queryText, $apiKey)
    {
        $payload = json_encode([
            'textQuery' => $queryText,
            'maxResultCount' => 5
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::$textSearchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.businessStatus,places.primaryType'
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($err) {
            return ['success' => false, 'status' => 'timeout', 'message' => 'Google Places istek zaman aşımına uğradı.'];
        }

        if ($code === 400 || $code === 403) {
            return ['success' => false, 'status' => 'google_error', 'message' => 'Google Places API isteği reddedildi (API anahtarı veya yetkilendirme hatası).'];
        }

        if ($code === 429) {
            return ['success' => false, 'status' => 'quota_exceeded', 'message' => 'Google Places API kota limiti aşıldı.'];
        }

        if ($code >= 500) {
            return ['success' => false, 'status' => 'google_error', 'message' => 'Google Places sunucu hatası meydana geldi.'];
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            return ['success' => false, 'status' => 'google_error', 'message' => 'Google Places geçersiz yanıt döndürdü.'];
        }

        return ['success' => true, 'candidates' => $data['places'] ?? []];
    }

    private static function executePlaceDetails($placeId, $apiKey)
    {
        $url = self::$placeDetailsUrl . urlencode($placeId);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey,
            'X-Goog-FieldMask: id,displayName,formattedAddress,nationalPhoneNumber,internationalPhoneNumber,websiteUri,businessStatus'
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($err) {
            return ['success' => false, 'status' => 'timeout', 'message' => 'Google Place Details istek zaman aşımına uğradı.'];
        }

        if ($code >= 400 || !$resp) {
            return ['success' => false, 'status' => 'google_error', 'message' => 'Google Place Details verisi alınamadı.'];
        }

        $data = json_decode($resp, true);
        if (!$data || !is_array($data)) {
            return ['success' => false, 'status' => 'google_error', 'message' => 'Google Place Details geçersiz yanıt döndürdü.'];
        }

        return ['success' => true, 'data' => $data];
    }

    public static function calculateConfidence($companyName, $candidateName, $candidateAddress, $expectedCity = 'Konya')
    {
        $hasCity = (empty($expectedCity) || stripos($candidateAddress, $expectedCity) !== false);
        if (!$hasCity) {
            return ['level' => 'LOW', 'score' => 0.1];
        }

        $coreA = self::normalizeCoreName($companyName);
        $coreB = self::normalizeCoreName($candidateName);

        if (empty($coreA) || empty($coreB)) {
            return ['level' => 'LOW', 'score' => 0.2];
        }

        if ($coreA === $coreB) {
            return ['level' => 'HIGH', 'score' => 1.0];
        }

        $tokensA = array_filter(explode(' ', $coreA), function($t) { return mb_strlen($t) > 1; });
        $tokensB = array_filter(explode(' ', $coreB), function($t) { return mb_strlen($t) > 1; });

        if (empty($tokensA) || empty($tokensB)) {
            return ['level' => 'LOW', 'score' => 0.2];
        }

        $matched = 0;
        foreach ($tokensA as $ta) {
            foreach ($tokensB as $tb) {
                if ($ta === $tb || (mb_strlen($ta) > 3 && mb_strlen($tb) > 3 && (strpos($ta, $tb) !== false || strpos($tb, $ta) !== false))) {
                    $matched++;
                    break;
                }
            }
        }

        $ratioA = $matched / count($tokensA);
        $ratioB = $matched / count($tokensB);
        $overlap = max($ratioA, $ratioB);

        similar_text($coreA, $coreB, $simPercent);
        $simRatio = $simPercent / 100.0;

        $score = max($overlap, $simRatio);

        if ($score >= 0.70) {
            return ['level' => 'HIGH', 'score' => round($score, 2)];
        }
        if ($score >= 0.40) {
            return ['level' => 'MEDIUM', 'score' => round($score, 2)];
        }
        return ['level' => 'LOW', 'score' => round($score, 2)];
    }

    public static function normalizeCoreName($name)
    {
        $name = StringHelper::normalizeTurkish(mb_strtolower($name, 'UTF-8'));
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name);
        
        $suffixes = [
            '\\banonim sirketi\\b', '\\blimited sirketi\\b', '\\bltd sti\\b', '\\bltd\\b', '\\bsti\\b',
            '\\ba s\\b', '\\bas\\b', '\\bsanayi ve ticaret\\b', '\\bsan ve tic\\b', '\\bsanayi ve tic\\b',
            '\\bsanayi\\b', '\\bticaret\\b', '\\bsan\\b', '\\btic\\b', '\\bve\\b', '\\bholding\\b',
            '\\bsirketi\\b', '\\bkollektif sirketi\\b', '\\bkomandit sirketi\\b', '\\bsubesi\\b',
            '\\bmerkezi\\b', '\\binsaat\\b', '\\byapi\\b'
        ];
        foreach ($suffixes as $s) {
            $name = preg_replace('/' . $s . '/u', ' ', $name);
        }
        return preg_replace('/\s+/', ' ', trim($name));
    }

    /**
     * Save ONLY place_id and metadata (policy-compliant, NO Google content stored).
     */
    private static function savePlaceIdCache($cacheFile, $normalizedCompany, $placeId)
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0777, true);
        }
        $data = [
            'normalized_company' => $normalizedCompany,
            'google_place_id' => $placeId,
            'matched_at' => time()
        ];
        @file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Save transient medium-match token (NO Google content stored).
     */
    private static function savePendingToken($token, array $data)
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0777, true);
        }
        $file = self::$cacheDir . 'pending_' . $token . '.json';
        @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function getPendingToken($token)
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $file = self::$cacheDir . 'pending_' . $token . '.json';
        if (file_exists($file) && (time() - filemtime($file) < self::$pendingTtl)) {
            return json_decode(file_get_contents($file), true);
        }
        return null;
    }

    private static function deletePendingToken($token)
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token)) {
            $file = self::$cacheDir . 'pending_' . $token . '.json';
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    private static function logApiCall($companyKey, $textSearchCount, $placeDetailsCount, $placeIdCacheHit, $status)
    {
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $time = date('Y-m-d H:i:s');
        $hitStr = $placeIdCacheHit ? '1' : '0';
        // NEVER log API key, response body, phone, address, or websiteUri
        $logLine = "[$time] FIRMA: $companyKey | TEXT_SEARCH: $textSearchCount | PLACE_DETAILS: $placeDetailsCount | PLACE_ID_CACHE_HIT: $hitStr | STATUS: $status\n";
        @file_put_contents(self::$logFile, $logLine, FILE_APPEND);
    }

    public static function isConfigured()
    {
        $enabled = !defined('GOOGLE_PLACES_ENABLED') || (bool)GOOGLE_PLACES_ENABLED;
        return $enabled && !empty(self::getApiKey());
    }

    private static function getApiKey()
    {
        if (defined('GOOGLE_PLACES_API_KEY') && !empty(GOOGLE_PLACES_API_KEY)) {
            return GOOGLE_PLACES_API_KEY;
        }
        return getenv('GOOGLE_PLACES_API_KEY') ?: '';
    }
}
