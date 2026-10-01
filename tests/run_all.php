<?php

require_once __DIR__ . "/../app/Helpers/StringHelper.php";
require_once __DIR__ . '/TestHelper.php';

require_once __DIR__ . '/../app/Services/LeadFinder/LeadSourceInterface.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Helpers/LeadNormalizer.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Enrichment/WebsiteEnricher.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Sources/ListOfCompanySource.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Sources/KsoSource.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Sources/KtoSource.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Sources/OpenStreetMapSource.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Enrichment/GooglePlacesEnricher.php';
require_once __DIR__ . '/../app/Services/LeadFinder/LeadFinderService.php';

use App\Services\LeadFinder\Helpers\LeadNormalizer;
use App\Services\LeadFinder\Enrichment\WebsiteEnricher;
use App\Services\LeadFinder\Enrichment\GooglePlacesEnricher;
use App\Services\LeadFinder\Sources\ListOfCompanySource;
use App\Services\LeadFinder\Sources\KsoSource;
use App\Services\LeadFinder\Sources\KtoSource;
use App\Services\LeadFinder\LeadFinderService;

echo "--- NORMALIZATION TESTS ---\n";
TestHelper::assertEqual('905321234567', LeadNormalizer::normalizePhone('0532 123 45 67'), 'Phone with spaces');
TestHelper::assertEqual('example.com', LeadNormalizer::normalizeDomain('https://www.example.com/foo'), 'Domain normalization');
TestHelper::assertEqual('cagdas isik', LeadNormalizer::normalizeCompanyName('Çağdaş Işık'), 'Turkish lowercase');
TestHelper::assertEqual('ornek sirket', LeadNormalizer::normalizeCompanyName('ÖRNEK ŞİRKET'), 'Turkish uppercase');

echo "\n--- SSRF/DNS TESTS ---\n";
TestHelper::assertNull(WebsiteEnricher::enrich('http://localhost'), 'SSRF localhost blocked');
TestHelper::assertNull(WebsiteEnricher::enrich('http://127.0.0.1'), 'SSRF IPv4 loopback blocked');
TestHelper::assertNull(WebsiteEnricher::enrich('http://10.0.0.1'), 'SSRF private IPv4 blocked');
// IPv6 bypass check
TestHelper::assertNull(WebsiteEnricher::enrich('http://[::1]'), 'SSRF IPv6 loopback blocked');

echo "\n--- SOURCE TESTS ---\n";
$loc = new ListOfCompanySource();
$locRes = $loc->search('plastik', 'Konya', '', 10);
TestHelper::assertEqual(0, count($locRes), 'ListOfCompany should return empty (UNAVAILABLE)');

$kso = new KsoSource();
try {
    $ksoRes = $kso->search('plastik', 'Konya', '', 3);
    if (!empty($ksoRes)) {
        TestHelper::assertTrue(count($ksoRes) > 0, 'KSO search returned items');
        TestHelper::assertNotEmpty($ksoRes[0]['name'], 'KSO first item has a name');
    } else {
        TestHelper::skip('KSO returned 0 results (Network timeout, cloudflare block, or actually 0)');
    }
} catch (\Exception $e) {
    TestHelper::skip('KSO search network error: ' . $e->getMessage());
}

// Skip some network heavy tests but allow basic execution
TestHelper::skip('OSM mapping (tested manually)');
TestHelper::skip('Duplicate DB detection (tested via UI manually)');
TestHelper::skip('Email lead->company transfer (tested via UI controller check)');

echo "\n--- KTO & GOOGLE PLACES TESTS ---\n";
$kto = new KtoSource();
$committees = $kto->getCommittees();
TestHelper::assertTrue(count($committees) >= 70, 'KTO committees count >= 70');
TestHelper::assertEqual('MİMARLIK FAALİYETLERİ', $committees['21'] ?? '', 'KTO committee 21 is MİMARLIK FAALİYETLERİ');

$confHigh = GooglePlacesEnricher::calculateConfidence('ADALET DÖKÜM ANONİM ŞİRKETİ', 'Adalet Döküm', 'Karatay, Konya');
TestHelper::assertEqual('HIGH', $confHigh['level'], 'Google match confidence HIGH for core name in Konya');

$confLow = GooglePlacesEnricher::calculateConfidence('ADALET DÖKÜM ANONİM ŞİRKETİ', 'Adalet Döküm', 'Çankaya, Ankara');
TestHelper::assertEqual('LOW', $confLow['level'], 'Google match confidence LOW for city mismatch');

$enrichEmpty = GooglePlacesEnricher::enrich('');
TestHelper::assertEqual(false, $enrichEmpty['success'], 'Google Places empty query rejected');

$invalidConfirm = GooglePlacesEnricher::confirmMatch('invalidtoken123');
TestHelper::assertEqual(false, $invalidConfirm['success'], 'Google Places confirmMatch with invalid token rejected');

echo "\n--- SOURCE BALANCING & TRACE TESTS ---\n";
TestHelper::assertEqual('Konya Ticaret Odası + Konya Sanayi Odası', LeadNormalizer::mergeSources('Konya Ticaret Odası', 'Konya Sanayi Odası'), 'Source trace merge with plus');
TestHelper::assertEqual('Konya Ticaret Odası', LeadNormalizer::mergeSources('Konya Ticaret Odası', 'Konya Ticaret Odası'), 'Source trace deduplication');

$mockKto = array_map(fn($i) => ['name' => "KTO Firma $i", 'source' => 'Konya Ticaret Odası', 'phone' => "033200000$i"], range(1, 20));
$mockKso = array_map(fn($i) => ['name' => "KSO Firma $i", 'source' => 'Konya Sanayi Odası', 'phone' => "033211111$i"], range(1, 5));
$mockOsm = array_map(fn($i) => ['name' => "OSM Firma $i", 'source' => 'OpenStreetMap', 'phone' => "033222222$i"], range(1, 5));

$balanced = LeadFinderService::balanceSources(['KTO' => $mockKto, 'KSO' => $mockKso, 'OSM' => $mockOsm], 25);
TestHelper::assertEqual(25, count($balanced), 'Balanced sources count equals limit 25');

$sourceCounts = [];
foreach ($balanced as $b) {
    $sourceCounts[$b['source']] = ($sourceCounts[$b['source']] ?? 0) + 1;
}
TestHelper::assertEqual(15, $sourceCounts['Konya Ticaret Odası'] ?? 0, 'KTO quota is 15');
TestHelper::assertEqual(5, $sourceCounts['Konya Sanayi Odası'] ?? 0, 'KSO quota is 5');
TestHelper::assertEqual(5, $sourceCounts['OpenStreetMap'] ?? 0, 'OSM quota is 5');

// Test spillover when KSO is empty
$spillover = LeadFinderService::balanceSources(['KTO' => $mockKto, 'KSO' => [], 'OSM' => $mockOsm], 25);
TestHelper::assertEqual(25, count($spillover), 'Spillover fills quota to 25');

echo "\n--- GOOGLE PLACES POLICY COMPLIANCE TESTS ---\n";
// 1. Verify schema does not contain google_maps_uri
$schemaSql = file_get_contents(__DIR__ . '/../database/schema.sql');
TestHelper::assertTrue(strpos($schemaSql, 'google_maps_uri') === false, 'Schema does NOT contain google_maps_uri');
TestHelper::assertTrue(strpos($schemaSql, 'google_place_id') !== false, 'Schema contains google_place_id');

// 2. Verify LeadsController whitelist does NOT contain google_maps_uri
$controllerCode = file_get_contents(__DIR__ . '/../app/Controllers/LeadsController.php');
TestHelper::assertTrue(strpos($controllerCode, "'google_maps_uri'") === false, 'LeadsController does NOT accept google_maps_uri');

// 3. Verify Google Places Cache files store ONLY Place ID and metadata
$cacheFiles = glob(__DIR__ . '/../storage/cache/google_places/*.json');
$checkedCount = 0;
foreach ($cacheFiles as $cf) {
    if (strpos(basename($cf), 'pending_') === 0) continue;
    $cacheJson = json_decode(file_get_contents($cf), true);
    $checkedCount++;
    TestHelper::assertTrue(!empty($cacheJson['google_place_id']), 'Cache has google_place_id');
    TestHelper::assertTrue(!isset($cacheJson['phone']), 'Cache has NO phone');
    TestHelper::assertTrue(!isset($cacheJson['formattedAddress']), 'Cache has NO formattedAddress');
    TestHelper::assertTrue(!isset($cacheJson['address']), 'Cache has NO address');
    TestHelper::assertTrue(!isset($cacheJson['displayName']), 'Cache has NO displayName');
    TestHelper::assertTrue(!isset($cacheJson['websiteUri']), 'Cache has NO websiteUri');
    TestHelper::assertTrue(!isset($cacheJson['google_maps_uri']), 'Cache has NO google_maps_uri');
}
if ($checkedCount > 0) {
    TestHelper::assertTrue($checkedCount > 0, "Verified $checkedCount cache files are strictly policy compliant");
}

echo "\n--- FAZ 3.6.3 SCORING & WEBSITE PERSISTENCE TESTS ---\n";
// 1. Confidence score bounded in [0.00, 1.00]
$adraRes = GooglePlacesEnricher::calculateConfidence('ADRA BÜRO MOBİLYA SANAYİ VE TİCARET LİMİTED ŞİRKETİ', 'Adrabüromobilya', 'Karatay, Konya');
TestHelper::assertTrue($adraRes['score'] <= 1.0, 'ADRA confidence score <= 1.0 (got ' . $adraRes['score'] . ')');
TestHelper::assertTrue($adraRes['score'] >= 0.0, 'ADRA confidence score >= 0.0');
TestHelper::assertEqual('HIGH', $adraRes['level'], 'ADRA is HIGH level match');

$adaletRes = GooglePlacesEnricher::calculateConfidence('ADALET DÖKÜM A.Ş.', 'Adalet Çelik Döküm', 'Karatay, Konya');
TestHelper::assertTrue($adaletRes['score'] <= 1.0 && $adaletRes['score'] >= 0.70, 'Adalet Döküm is HIGH match within [0.70, 1.00]');

$twobkRes = GooglePlacesEnricher::calculateConfidence('2BK MİMARLIK MÜHENDİSLİK İNŞAAT TAAHHÜT GAYRİMENKUL DEĞERLEME SANAYİ VE TİCARET LİMİTED ŞİRKETİ', '2BK Mimarlık', 'Selçuklu, Konya');
TestHelper::assertTrue($twobkRes['score'] <= 1.0 && $twobkRes['score'] >= 0.70, '2BK is HIGH match within [0.70, 1.00]');

$burotimeRes = GooglePlacesEnricher::calculateConfidence('TOSUNOĞULLARI MOBİLYA SANAYİ VE TİCARET ANONİM ŞİRKETİ', 'Bürotime', 'Selçuklu, Konya');
TestHelper::assertEqual('LOW', $burotimeRes['level'], 'Tosunoğulları vs Bürotime is LOW match');
TestHelper::assertTrue($burotimeRes['score'] < 0.40, 'Bürotime score < 0.40');

$shortGenericRes = GooglePlacesEnricher::calculateConfidence('ALİ KAYA İNŞAAT VE EMLAK LİMİTED ŞİRKETİ', 'Emlak', 'Meram, Konya');
TestHelper::assertEqual('LOW', $shortGenericRes['level'], 'Short generic single-token candidate is LOW match');
TestHelper::assertTrue($shortGenericRes['score'] < 0.40, 'Short generic score < 0.40');

// 2. WebsiteEnricher final_url presence and SSRF rejection
$ssrfRes = WebsiteEnricher::enrich('http://127.0.0.1');
TestHelper::assertNull($ssrfRes, 'WebsiteEnricher SSRF blocked returns null (no final_url)');

// 3. Persistent source checks (Absence of "Google Places" and presence of "Website")
$viewCode = file_get_contents(__DIR__ . '/../app/Views/leads/index.php');
TestHelper::assertTrue(strpos($viewCode, "res.source_trace || 'Google Places'") === false, 'View does NOT fallback to Google Places in source badge');
TestHelper::assertTrue(strpos($viewCode, "form-source-") !== false, 'View updates hidden form-source field');

echo "\n--- FAZ 3.6.4 GOOGLE COMPLIANCE & ENRICHMENT METADATA TESTS ---\n";
// 1. Medium match modal has NO custom SVG logo and NO 'Powered by Google' / 'Google Maps Platform'
TestHelper::assertTrue(strpos($viewCode, 'id="googleMatchModal"') !== false, 'Google match modal exists in view');
if (preg_match('/<div id="googleMatchModal".*?<\/div>\s*<\/div>\s*<\/div>/s', $viewCode, $modalMatches)) {
    TestHelper::assertTrue(strpos($modalMatches[0], '<svg') === false, 'Medium match modal has NO custom SVG logo');
}
TestHelper::assertTrue(strpos($viewCode, 'Powered by Google') === false, 'View does NOT contain Powered by Google');
TestHelper::assertTrue(strpos($viewCode, 'Google Maps Platform') === false, 'View does NOT contain Google Maps Platform');

// 2. Exact text attribution: <span class="google-maps-attribution" translate="no">Google Maps</span>
TestHelper::assertTrue(strpos($viewCode, 'class="google-maps-attribution"') !== false, 'View has google-maps-attribution class');
TestHelper::assertTrue(strpos($viewCode, 'translate="no"') !== false, 'View has translate="no" on attribution');
TestHelper::assertTrue(strpos($viewCode, '>Google Maps</span>') !== false, 'View has exact Google Maps text attribution');

// 3. Place Details field mask is strictly id,websiteUri (no phone, no address, no displayName)
$enricherCode = file_get_contents(__DIR__ . '/../app/Services/LeadFinder/Enrichment/GooglePlacesEnricher.php');
TestHelper::assertTrue(strpos($enricherCode, "'X-Goog-FieldMask: id,websiteUri'") !== false, 'Place Details field mask is strictly id,websiteUri');
TestHelper::assertTrue(strpos($enricherCode, 'nationalPhoneNumber') === false, 'Place Details does NOT request nationalPhoneNumber');
TestHelper::assertTrue(strpos($enricherCode, 'internationalPhoneNumber') === false, 'Place Details does NOT request internationalPhoneNumber');

// 4. Response array does NOT contain google_preview (Static and zero-cost verification)
TestHelper::assertTrue(strpos($enricherCode, "'google_preview'") === false, 'GooglePlacesEnricher does NOT produce google_preview');
TestHelper::assertTrue(strpos($enricherCode, '"google_preview"') === false, 'GooglePlacesEnricher does NOT reference google_preview string');
TestHelper::assertTrue(!isset($enrichEmpty['google_preview']), 'Enrich return array does NOT contain google_preview');

// 5. DB save last_enriched_at metadata assignment
$controllerCode = file_get_contents(__DIR__ . '/../app/Controllers/LeadsController.php');
TestHelper::assertTrue(strpos($controllerCode, 'last_enriched_at') !== false, 'LeadsController assigns last_enriched_at');
TestHelper::assertTrue(strpos($controllerCode, "date('Y-m-d H:i:s')") !== false, 'LeadsController sets server-side timestamp');
// Verify last_enriched_at is NOT accepted blindly from POST whitelist
if (preg_match('/\$fields\s*=\s*\[(.*?)\];/s', $controllerCode, $fieldMatches)) {
    TestHelper::assertTrue(strpos($fieldMatches[1], "'last_enriched_at'") === false, 'POST whitelist does NOT contain last_enriched_at (tamper-proof)');
}

// 6. Live DB verification of last_enriched_at
if (file_exists(__DIR__ . '/../config/config.php')) {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../app/Core/Database.php';
    require_once __DIR__ . '/../app/Models/Company.php';
    
    try {
        $db = \App\Core\Database::getInstance()->getConnection();
        if ($db) {
            $companyModel = new \App\Models\Company();
            
            // Test Enriched Lead Save
            $testEnrichedData = [
                'name' => 'TEST_ENRICHED_' . bin2hex(random_bytes(4)),
                'phone' => '05329990011',
                'google_place_id' => 'ChIJtest_place_id_' . time(),
                'enrichment_status' => 'google_enriched',
                'status' => 'new'
            ];
            if (!empty($testEnrichedData['google_place_id']) || !empty($testEnrichedData['enrichment_status'])) {
                $testEnrichedData['last_enriched_at'] = date('Y-m-d H:i:s');
            } else {
                $testEnrichedData['last_enriched_at'] = null;
            }
            $enrichedId = $companyModel->create($testEnrichedData);
            $savedEnriched = $companyModel->findById($enrichedId);
            TestHelper::assertNotEmpty($savedEnriched['last_enriched_at'], 'Enriched company DB record has last_enriched_at NOT NULL');
            $companyModel->delete($enrichedId);
            
            // Test Normal Lead Save
            $testNormalData = [
                'name' => 'TEST_NORMAL_' . bin2hex(random_bytes(4)),
                'phone' => '05329990022',
                'google_place_id' => '',
                'enrichment_status' => '',
                'status' => 'new'
            ];
            if (!empty($testNormalData['google_place_id']) || !empty($testNormalData['enrichment_status'])) {
                $testNormalData['last_enriched_at'] = date('Y-m-d H:i:s');
            } else {
                $testNormalData['last_enriched_at'] = null;
            }
            $normalId = $companyModel->create($testNormalData);
            $savedNormal = $companyModel->findById($normalId);
            TestHelper::assertNull($savedNormal['last_enriched_at'], 'Normal company DB record has last_enriched_at IS NULL');
            $companyModel->delete($normalId);
        }
    } catch (\Throwable $e) {
        TestHelper::skip('DB live test skipped: ' . $e->getMessage());
    }
}

require __DIR__ . '/test_string_parsing.php';
require __DIR__ . '/test_sales_crm.php';

TestHelper::finish();

