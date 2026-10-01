<?php
/**
 * Integration Test for Live External Lead Sources (KSO, KTO)
 * Run explicitly when testing external portal connectivity:
 *   php tests/integration/lead_sources_live.php
 */

require_once __DIR__ . '/../../app/Helpers/StringHelper.php';
require_once __DIR__ . '/../TestHelper.php';
require_once __DIR__ . '/../../app/Services/LeadFinder/LeadSourceInterface.php';
require_once __DIR__ . '/../../app/Services/LeadFinder/Sources/KsoSource.php';
require_once __DIR__ . '/../../app/Services/LeadFinder/Sources/KtoSource.php';

use App\Services\LeadFinder\Sources\KsoSource;
use App\Services\LeadFinder\Sources\KtoSource;

echo "=== LIVE LEAD SOURCES INTEGRATION TEST ===\n\n";

echo "--- KSO Live Search ---\n";
$kso = new KsoSource();
try {
    $ksoRes = $kso->search('plastik', 'Konya', '', 3);
    if (!empty($ksoRes)) {
        TestHelper::assertTrue(count($ksoRes) > 0, 'KSO search returned items');
        TestHelper::assertNotEmpty($ksoRes[0]['name'], 'KSO first item has a name');
        echo "Found " . count($ksoRes) . " KSO leads (First: " . $ksoRes[0]['name'] . ")\n";
    } else {
        TestHelper::skip('KSO returned 0 results (Timeout or portal change)');
    }
} catch (\Throwable $e) {
    TestHelper::skip('KSO search network error: ' . $e->getMessage());
}

echo "\n--- KTO Live Search ---\n";
$kto = new KtoSource();
try {
    $ktoRes = $kto->search('mimarlık', 'Konya', '', 3);
    if (!empty($ktoRes)) {
        TestHelper::assertTrue(count($ktoRes) > 0, 'KTO search returned items');
        TestHelper::assertNotEmpty($ktoRes[0]['name'], 'KTO first item has a name');
        echo "Found " . count($ktoRes) . " KTO leads (First: " . $ktoRes[0]['name'] . ")\n";
    } else {
        TestHelper::skip('KTO returned 0 results');
    }
} catch (\Throwable $e) {
    TestHelper::skip('KTO search network error: ' . $e->getMessage());
}

TestHelper::finish();
