<?php

require_once __DIR__ . '/TestHelper.php';

require_once __DIR__ . '/../app/Services/LeadFinder/LeadSourceInterface.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Helpers/LeadNormalizer.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Enrichment/WebsiteEnricher.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Sources/ListOfCompanySource.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Sources/KsoSource.php';

use App\Services\LeadFinder\Helpers\LeadNormalizer;
use App\Services\LeadFinder\Enrichment\WebsiteEnricher;
use App\Services\LeadFinder\Sources\ListOfCompanySource;
use App\Services\LeadFinder\Sources\KsoSource;

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
$ksoRes = $kso->search('plastik', 'Konya', '', 3);
TestHelper::assertTrue(is_array($ksoRes), 'KSO search returns array');

// Skip some network heavy tests but allow basic execution
TestHelper::skip('JSON-LD deep checking (tested manually via konya.bel.tr)');
TestHelper::skip('Internal relative URL (tested manually via konya.bel.tr)');
TestHelper::skip('OSM mapping (tested manually)');
TestHelper::skip('Duplicate DB detection (tested via UI manually)');
TestHelper::skip('Email lead->company transfer (tested via UI controller check)');

TestHelper::finish();
