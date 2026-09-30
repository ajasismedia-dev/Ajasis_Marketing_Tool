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

require __DIR__ . '/test_string_parsing.php';
