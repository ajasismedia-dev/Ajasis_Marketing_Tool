<?php

// tests/test_website_enricher.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../app/Services/LeadFinder/Enrichment/WebsiteEnricher.php';

use App\Services\LeadFinder\Enrichment\WebsiteEnricher;

echo "Testing WebsiteEnricher...\n";
echo "1. Real world site (konya.bel.tr):\n";
$data = WebsiteEnricher::enrich('https://www.konya.bel.tr');
var_dump($data);

echo "2. SSRF check (localhost):\n";
$data = WebsiteEnricher::enrich('http://localhost');
var_dump($data);

echo "3. SSRF check (127.0.0.1):\n";
$data = WebsiteEnricher::enrich('http://127.0.0.1');
var_dump($data);

echo "4. SSRF check (10.0.0.1):\n";
$data = WebsiteEnricher::enrich('http://10.0.0.1');
var_dump($data);

echo "Done.\n";
