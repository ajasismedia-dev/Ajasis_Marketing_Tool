<?php

// tests/test_normalizer.php
require_once __DIR__ . '/../app/Services/LeadFinder/Helpers/LeadNormalizer.php';

use App\Services\LeadFinder\Helpers\LeadNormalizer;

$passed = 0;
$failed = 0;

function assertEqual($expected, $actual, $name) {
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        echo "[PASS] $name\n";
    } else {
        $failed++;
        echo "[FAIL] $name - Expected '$expected', got '$actual'\n";
    }
}

// 1. Phone Normalization
assertEqual('905321234567', LeadNormalizer::normalizePhone('0532 123 45 67'), 'Phone with spaces');
assertEqual('905321234567', LeadNormalizer::normalizePhone('+90 (532) 123-4567'), 'Phone with symbols');
assertEqual('905321234567', LeadNormalizer::normalizePhone('905321234567'), 'Phone exact');
assertEqual('123456', LeadNormalizer::normalizePhone('123456'), 'Phone short');

// 2. Domain Normalization
assertEqual('example.com', LeadNormalizer::normalizeDomain('http://www.example.com/'), 'Domain with www');
assertEqual('example.com', LeadNormalizer::normalizeDomain('https://example.com/contact'), 'Domain with path');
assertEqual('example.com', LeadNormalizer::normalizeDomain('example.com'), 'Raw domain');

// 3. Company Name Normalization (Turkish)
assertEqual('ajasis media', LeadNormalizer::normalizeCompanyName('Ajasis Media'), 'Company name case');
assertEqual('cagdas isik', LeadNormalizer::normalizeCompanyName('Çağdaş Işık'), 'Company name Turkish chars');
assertEqual('ornek sirket', LeadNormalizer::normalizeCompanyName('ÖRNEK ŞİRKET'), 'Company name upper Turkish');

echo "\nNormalizer Tests: $passed passed, $failed failed.\n";
