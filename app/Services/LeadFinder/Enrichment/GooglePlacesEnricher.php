<?php

namespace App\Services\LeadFinder\Enrichment;

use App\Helpers\StringHelper;

class GooglePlacesEnricher
{
    private static $textSearchUrl = 'https://places.googleapis.com/v1/places:searchText';
    private static $placeDetailsUrl = 'https://places.googleapis.com/v1/places/';
    private static $cacheDir = __DIR__ . '/../../../../storage/cache/google_places/';
    private static $cacheTtl = 2592000; // 30 days in seconds (30 * 86400)

    /**
     * Enrich a lead using Google Places API (New) and subsequent WebsiteEnricher
     *
     * @param string $companyName
     * @param string $city
     * @param string $district
     * @param bool $forceAccept
     * @return array
     */
    public static function enrich($companyName, $city = 'Konya', $district = '', $forceAccept = false)
    {
        $companyName = trim($companyName);
        if (empty($companyName)) {
            return [
                'success' => false,
                'status' => 'empty_query',
                'message' => 'Firma adı belirtilmedi.'
            ];
        }

        // 1. Check Cache
        $cacheKey = md5(self::normalizeCoreName($companyName) . '_' . StringHelper::normalizeTurkish(mb_strtolower($city)));
        $cacheFile = self::$cacheDir . $cacheKey . '.json';

        if (!$forceAccept && file_exists($cacheFile) && (time() - filemtime($cacheFile) < self::$cacheTtl)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (!empty($cached) && is_array($cached)) {
                $cached['from_cache'] = true;
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
                'message' => 'Google Places: Yapılandırılmadı (API anahtarı eksik).'
            ];
        }

        if (defined('GOOGLE_PLACES_ENABLED') && !GOOGLE_PLACES_ENABLED) {
            return [
                'success' => false,
                'status' => 'not_configured',
                'confidence' => 'NONE',
                'message' => 'Google Places entegrasyonu devre dışı bırakılmış.'
            ];
        }

        // 3. Search via Google Places (New) Text Search
        $districtClean = trim($district);
        $cityClean = trim($city ?: 'Konya');
        $queryText = trim("{$companyName} " . ($districtClean ? "{$districtClean} " : "") . "{$cityClean} Türkiye");

        $searchResult = self::executeTextSearch($queryText, $apiKey);

        if (!$searchResult['success']) {
            return $searchResult;
        }

        $candidates = $searchResult['candidates'] ?? [];
        if (empty($candidates)) {
            $result = [
                'success' => false,
                'status' => 'not_found',
                'confidence' => 'NONE',
                'message' => 'Google Places üzerinde eşleşen işletme bulunamadı.',
                'from_cache' => false
            ];
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

        // 5. Handle Confidence Level
        if ($best['confidence'] === 'LOW' && !$forceAccept) {
            $result = [
                'success' => false,
                'status' => 'low_confidence',
                'confidence' => 'LOW',
                'candidate' => $best,
                'message' => 'Google Places eşleşme güveni çok düşük (otomatik kabul edilmedi).',
                'from_cache' => false
            ];
            self::saveCache($cacheFile, $result);
            return $result;
        }

        if ($best['confidence'] === 'MEDIUM' && !$forceAccept) {
            // Require user confirmation in UI
            return [
                'success' => true,
                'status' => 'medium_confidence',
                'confidence' => 'MEDIUM',
                'candidate' => $best,
                'message' => 'Muhtemel Google Places eşleşmesi bulundu, onayınız bekleniyor.',
                'from_cache' => false
            ];
        }

        // 6. HIGH (or user-approved) Match: Call Place Details
        $placeId = $best['place_id'];
        $detailsResult = self::executePlaceDetails($placeId, $apiKey);

        if (!$detailsResult['success']) {
            return $detailsResult;
        }

        $placeDetails = $detailsResult['data'];
        $phone = $placeDetails['nationalPhoneNumber'] ?? $placeDetails['internationalPhoneNumber'] ?? '';
        $website = $placeDetails['websiteUri'] ?? '';
        $address = $placeDetails['formattedAddress'] ?? $best['formatted_address'];
        $googleMapsUri = $placeDetails['googleMapsUri'] ?? $best['google_maps_uri'];
        $businessStatus = $placeDetails['businessStatus'] ?? $best['business_status'];
        $location = $placeDetails['location'] ?? null;

        // 7. Auto-chain to WebsiteEnricher if website found
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
            'confidence' => $best['confidence'],
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
            'source_trace' => $sourceTrace,
            'from_cache' => false
        ];

        self::saveCache($cacheFile, $finalEnriched);
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
            return ['level' => 'HIGH', 'score' => $score];
        }
        if ($score >= 0.40) {
            return ['level' => 'MEDIUM', 'score' => $score];
        }
        return ['level' => 'LOW', 'score' => $score];
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

    private static function getApiKey()
    {
        if (defined('GOOGLE_PLACES_API_KEY') && !empty(GOOGLE_PLACES_API_KEY)) {
            return GOOGLE_PLACES_API_KEY;
        }
        return getenv('GOOGLE_PLACES_API_KEY') ?: '';
    }
}
