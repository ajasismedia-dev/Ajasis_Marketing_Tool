<?php

namespace App\Services\LeadFinder\Enrichment;

use App\Helpers\StringHelper;

class GooglePlacesEnricher
{
    private static $textSearchUrl = 'https://places.googleapis.com/v1/places:searchText';
    private static $placeDetailsUrl = 'https://places.googleapis.com/v1/places/';
    private static $cacheDir = __DIR__ . '/../../../../storage/cache/google_places/';
    private static $logFile = __DIR__ . '/../../../../storage/logs/google_places.log';
    private static $cacheTtl = 2592000; // 30 days in seconds (30 * 86400)
    private static $pendingTtl = 3600; // 1 hour for medium match confirmation

    /**
     * Enrich a lead using Google Places API (New) and subsequent WebsiteEnricher
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
                'api_stats' => ['text_search_calls' => 0, 'place_details_calls' => 0, 'cache_hit' => false]
            ];
        }

        // 1. Check 30-day Cache
        $cacheKey = md5(self::normalizeCoreName($companyName) . '_' . StringHelper::normalizeTurkish(mb_strtolower($city)));
        $cacheFile = self::$cacheDir . $cacheKey . '.json';

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < self::$cacheTtl)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (!empty($cached) && is_array($cached)) {
                $cached['from_cache'] = true;
                $cached['api_stats'] = [
                    'text_search_calls' => 0,
                    'place_details_calls' => 0,
                    'cache_hit' => true
                ];
                self::logApiCall($companyName, 0, 0, true, $cached['confidence'] ?? 'HIGH');
                return $cached;
            }
        }

        // 2. Check API Key
        $apiKey = self::getApiKey();
        if (empty($apiKey)) {
            return [
                'success' => false,
                'status' => 'not_configured',
                'confidence' => 'NONE',
                'message' => 'Google Places API anahtarı gerekli (config.php içinde yapılandırılmadı).',
                'api_stats' => ['text_search_calls' => 0, 'place_details_calls' => 0, 'cache_hit' => false]
            ];
        }

        if (defined('GOOGLE_PLACES_ENABLED') && !GOOGLE_PLACES_ENABLED) {
            return [
                'success' => false,
                'status' => 'not_configured',
                'confidence' => 'NONE',
                'message' => 'Google Places entegrasyonu devre dışı bırakılmış.',
                'api_stats' => ['text_search_calls' => 0, 'place_details_calls' => 0, 'cache_hit' => false]
            ];
        }

        // 3. Search via Google Places (New) Text Search
        $districtClean = trim($district);
        $cityClean = trim($city ?: 'Konya');
        $queryText = trim("{$companyName} " . ($districtClean ? "{$districtClean} " : "") . "{$cityClean} Türkiye");

        $searchResult = self::executeTextSearch($queryText, $apiKey);

        if (!$searchResult['success']) {
            $searchResult['api_stats'] = ['text_search_calls' => 1, 'place_details_calls' => 0, 'cache_hit' => false];
            self::logApiCall($companyName, 1, 0, false, 'ERROR');
            return $searchResult;
        }

        $candidates = $searchResult['candidates'] ?? [];
        if (empty($candidates)) {
            $result = [
                'success' => false,
                'status' => 'not_found',
                'confidence' => 'NONE',
                'message' => 'Google Places üzerinde eşleşen işletme bulunamadı.',
                'from_cache' => false,
                'api_stats' => ['text_search_calls' => 1, 'place_details_calls' => 0, 'cache_hit' => false]
            ];
            self::logApiCall($companyName, 1, 0, false, 'NONE');
            self::saveCache($cacheFile, $result);
            return $result;
        }

        // 4. Calculate Confidence for top 5 candidates
        $evaluated = [];
        $candidates = array_slice($candidates, 0, 5);

        foreach ($candidates as $cand) {
            $candName = $cand['displayName']['text'] ?? '';
            $candAddr = $cand['formattedAddress'] ?? '';
            $conf = self::calculateConfidence($companyName, $candName, $candAddr, $cityClean);
            $evaluated[] = [
                'candidate' => $cand,
                'confidence' => $conf['level'],
                'score' => $conf['score'],
                'display_name' => $candName,
                'formatted_address' => $candAddr,
                'place_id' => $cand['id'] ?? '',
                'google_maps_uri' => $cand['googleMapsUri'] ?? '',
                'business_status' => $cand['businessStatus'] ?? 'OPERATIONAL'
            ];
        }

        // Sort candidates by score descending
        usort($evaluated, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $best = $evaluated[0];

        // 5. Handle LOW Confidence (Strictly rejected, no bypass)
        if ($best['confidence'] === 'LOW') {
            $result = [
                'success' => false,
                'status' => 'low_confidence',
                'confidence' => 'LOW',
                'score' => $best['score'],
                'candidate' => [
                    'display_name' => $best['display_name'],
                    'formatted_address' => $best['formatted_address']
                ],
                'message' => 'Eşleşme bulunamadı / düşük güven (otomatik kayıt yapılmaz).',
                'from_cache' => false,
                'api_stats' => ['text_search_calls' => 1, 'place_details_calls' => 0, 'cache_hit' => false]
            ];
            self::logApiCall($companyName, 1, 0, false, 'LOW');
            self::saveCache($cacheFile, $result);
            return $result;
        }

        // 6. Handle MEDIUM Confidence (Requires user confirmation with token)
        if ($best['confidence'] === 'MEDIUM') {
            $matchToken = bin2hex(random_bytes(16));
            self::savePendingCandidate($matchToken, [
                'place_id' => $best['place_id'],
                'company_name' => $companyName,
                'city' => $cityClean,
                'district' => $districtClean,
                'best_candidate' => $best,
                'cache_file' => $cacheFile,
                'created_at' => time()
            ]);

            self::logApiCall($companyName, 1, 0, false, 'MEDIUM');

            return [
                'success' => true,
                'status' => 'medium_confidence',
                'confidence' => 'MEDIUM',
                'score' => $best['score'],
                'match_token' => $matchToken,
                'candidate' => [
                    'display_name' => $best['display_name'],
                    'formatted_address' => $best['formatted_address']
                ],
                'message' => 'Muhtemel Google Places eşleşmesi bulundu, onayınız bekleniyor.',
                'from_cache' => false,
                'api_stats' => ['text_search_calls' => 1, 'place_details_calls' => 0, 'cache_hit' => false]
            ];
        }

        // 7. HIGH Match: Fetch Place Details immediately
        return self::finalizeEnrichment($best['place_id'], $best, $companyName, $cacheFile, 1);
    }

    /**
     * Confirm a medium match using the verified match token without repeating Text Search
     *
     * @param string $matchToken
     * @return array
     */
    public static function confirmMatch($matchToken)
    {
        $pending = self::getPendingCandidate($matchToken);
        if (!$pending) {
            return [
                'success' => false,
                'status' => 'invalid_token',
                'message' => 'Geçersiz veya süresi dolmuş eşleşme oturumu.',
                'api_stats' => ['text_search_calls' => 0, 'place_details_calls' => 0, 'cache_hit' => false]
            ];
        }

        $placeId = $pending['place_id'];
        $best = $pending['best_candidate'];
        $companyName = $pending['company_name'];
        $cacheFile = $pending['cache_file'];

        // Remove pending token once used
        self::deletePendingCandidate($matchToken);

        // Fetch Place Details (0 text search calls, 1 place details call)
        return self::finalizeEnrichment($placeId, $best, $companyName, $cacheFile, 0);
    }

    private static function finalizeEnrichment($placeId, array $best, $companyName, $cacheFile, $textSearchCallsCount = 0)
    {
        $apiKey = self::getApiKey();
        $detailsResult = self::executePlaceDetails($placeId, $apiKey);

        if (!$detailsResult['success']) {
            $detailsResult['api_stats'] = [
                'text_search_calls' => $textSearchCallsCount,
                'place_details_calls' => 1,
                'cache_hit' => false
            ];
            self::logApiCall($companyName, $textSearchCallsCount, 1, false, 'DETAILS_ERROR');
            return $detailsResult;
        }

        $placeDetails = $detailsResult['data'];
        $phone = $placeDetails['nationalPhoneNumber'] ?? $placeDetails['internationalPhoneNumber'] ?? '';
        $website = $placeDetails['websiteUri'] ?? '';
        $address = $placeDetails['formattedAddress'] ?? $best['formatted_address'];
        $googleMapsUri = $placeDetails['googleMapsUri'] ?? $best['google_maps_uri'];
        $businessStatus = $placeDetails['businessStatus'] ?? $best['business_status'];
        $location = $placeDetails['location'] ?? null;

        // Auto-chain to WebsiteEnricher if website found
        $webData = [];
        $sourceTrace = 'Google Places';
        if (!empty($website)) {
            $enrichedWeb = WebsiteEnricher::enrich($website);
            if (!empty($enrichedWeb) && is_array($enrichedWeb)) {
                $webData = $enrichedWeb;
                $sourceTrace = 'Google Places + Website';
            }
        }

        $finalEnriched = [
            'success' => true,
            'status' => 'success',
            'confidence' => $best['confidence'] ?? 'HIGH',
            'score' => $best['score'] ?? 1.0,
            'google_place_id' => $placeId,
            'google_maps_uri' => $googleMapsUri,
            'phone' => $phone,
            'website' => $website,
            'address' => $address,
            'business_status' => $businessStatus,
            'location' => $location,
            'email' => $webData['email'] ?? '',
            'instagram' => $webData['instagram'] ?? '',
            'facebook' => $webData['facebook'] ?? '',
            'linkedin' => $webData['linkedin'] ?? '',
            'youtube' => $webData['youtube'] ?? '',
            'website_data' => $webData,
            'source_trace' => $sourceTrace,
            'from_cache' => false,
            'api_stats' => [
                'text_search_calls' => $textSearchCallsCount,
                'place_details_calls' => 1,
                'cache_hit' => false
            ]
        ];

        self::saveCache($cacheFile, $finalEnriched);
        self::logApiCall($companyName, $textSearchCallsCount, 1, false, $finalEnriched['confidence']);

        return $finalEnriched;
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
            'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.businessStatus,places.primaryType,places.googleMapsUri'
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($err) {
            return [
                'success' => false,
                'status' => 'timeout',
                'message' => 'Google Places istek zaman aşımına uğradı.'
            ];
        }

        if ($code === 400 || $code === 403) {
            return [
                'success' => false,
                'status' => 'google_error',
                'message' => 'Google Places API isteği reddedildi (API anahtarı veya yetkilendirme hatası).'
            ];
        }

        if ($code === 429) {
            return [
                'success' => false,
                'status' => 'quota_exceeded',
                'message' => 'Google Places API kota limiti aşıldı.'
            ];
        }

        if ($code >= 500) {
            return [
                'success' => false,
                'status' => 'google_error',
                'message' => 'Google Places sunucu hatası meydana geldi.'
            ];
        }

        $data = json_decode($resp, true);
        if (!$data || !is_array($data)) {
            return [
                'success' => false,
                'status' => 'google_error',
                'message' => 'Google Places geçersiz yanıt döndürdü.'
            ];
        }

        return [
            'success' => true,
            'candidates' => $data['places'] ?? []
        ];
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
            'X-Goog-FieldMask: id,displayName,formattedAddress,nationalPhoneNumber,internationalPhoneNumber,websiteUri,businessStatus,location,googleMapsUri'
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'AjasisMarketingTool/1.0');

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($err) {
            return [
                'success' => false,
                'status' => 'timeout',
                'message' => 'Google Place Details istek zaman aşımına uğradı.'
            ];
        }

        if ($code >= 400 || !$resp) {
            return [
                'success' => false,
                'status' => 'google_error',
                'message' => 'Google Place Details verisi alınamadı.'
            ];
        }

        $data = json_decode($resp, true);
        if (!$data || !is_array($data)) {
            return [
                'success' => false,
                'status' => 'google_error',
                'message' => 'Google Place Details geçersiz yanıt döndürdü.'
            ];
        }

        return [
            'success' => true,
            'data' => $data
        ];
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

        // Token overlap
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
        // Remove punctuation
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $name);
        
        // Common Turkish company suffixes
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
        $name = preg_replace('/\s+/', ' ', trim($name));
        return $name;
    }

    private static function saveCache($cacheFile, $data)
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0777, true);
        }
        @file_put_contents($cacheFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function savePendingCandidate($token, $data)
    {
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0777, true);
        }
        $file = self::$cacheDir . 'pending_' . $token . '.json';
        @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    private static function getPendingCandidate($token)
    {
        // Strictly sanitize token to hex characters
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $file = self::$cacheDir . 'pending_' . $token . '.json';
        if (file_exists($file) && (time() - filemtime($file) < self::$pendingTtl)) {
            return json_decode(file_get_contents($file), true);
        }
        return null;
    }

    private static function deletePendingCandidate($token)
    {
        if (preg_match('/^[a-f0-9]{32}$/', $token)) {
            $file = self::$cacheDir . 'pending_' . $token . '.json';
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    private static function logApiCall($companyName, $textSearchCount, $placeDetailsCount, $cacheHit, $confidence)
    {
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $time = date('Y-m-d H:i:s');
        $hitStr = $cacheHit ? '1' : '0';
        $logLine = "[$time] FIRMA: $companyName | TEXT_SEARCH: $textSearchCount | PLACE_DETAILS: $placeDetailsCount | CACHE_HIT: $hitStr | CONFIDENCE: $confidence\n";
        @file_put_contents(self::$logFile, $logLine, FILE_APPEND);
    }

    private static function getApiKey()
    {
        if (defined('GOOGLE_PLACES_API_KEY') && !empty(GOOGLE_PLACES_API_KEY)) {
            return GOOGLE_PLACES_API_KEY;
        }
        return getenv('GOOGLE_PLACES_API_KEY') ?: '';
    }
}
