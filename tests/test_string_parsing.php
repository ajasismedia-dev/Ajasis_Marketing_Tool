<?php

require_once __DIR__ . '/TestHelper.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Enrichment/WebsiteEnricher.php';
require_once __DIR__ . '/../app/Services/LeadFinder/Helpers/LeadNormalizer.php';

use App\Services\LeadFinder\Enrichment\WebsiteEnricher;
use App\Services\LeadFinder\Helpers\LeadNormalizer;

echo "--- STRING PARSING TESTS ---\n";

// Access private method using reflection
function callPrivateMethod($class, $method, $args) {
    $reflection = new \ReflectionClass($class);
    $method = $reflection->getMethod($method);
    $method->setAccessible(true);
    return $method->invokeArgs(null, $args);
}

// 1. JSON-LD Object
$htmlObj = '<script type="application/ld+json">{"@type": "Organization", "telephone": "123456"}</script>';
$res = callPrivateMethod(WebsiteEnricher::class, 'extractJsonLd', [$htmlObj, 'telephone']);
TestHelper::assertEqual('123456', $res, 'JSON-LD Object');

// 2. JSON-LD Array
$htmlArr = '<script type="application/ld+json">[{"@type": "Person"}, {"@type": "LocalBusiness", "email": "test@test.com"}]</script>';
$res = callPrivateMethod(WebsiteEnricher::class, 'extractJsonLd', [$htmlArr, 'email']);
TestHelper::assertEqual('test@test.com', $res, 'JSON-LD Array');

// 3. JSON-LD @graph
$htmlGraph = '<script type="application/ld+json">{"@graph": [{"@type": "Organization", "sameAs": ["https://facebook.com/foo"]}]}</script>';
$res = callPrivateMethod(WebsiteEnricher::class, 'extractJsonLd', [$htmlGraph, 'social:facebook.com']);
TestHelper::assertEqual('https://facebook.com/foo', $res, 'JSON-LD @graph');

// 4. Relative URL
$base = 'https://example.com/about/us/';
TestHelper::assertEqual('https://example.com/contact', callPrivateMethod(WebsiteEnricher::class, 'resolveRelativeUrl', [$base, '/contact']), 'Absolute path');
TestHelper::assertEqual('https://example.com/about/us/contact', callPrivateMethod(WebsiteEnricher::class, 'resolveRelativeUrl', [$base, 'contact']), 'Relative path');
TestHelper::assertEqual('https://example.com/about/contact', callPrivateMethod(WebsiteEnricher::class, 'resolveRelativeUrl', [$base, '../contact']), 'Parent path');

// 5. KSO Host validation logic simulation
function validateKso($url) {
    $parsed = parse_url($url);
    $host = strtolower($parsed['host'] ?? '');
    return in_array($host, ['kso.org.tr', 'www.kso.org.tr']);
}
TestHelper::assertTrue(validateKso('https://kso.org.tr/test'), 'KSO exact domain');
TestHelper::assertTrue(validateKso('http://www.kso.org.tr/'), 'KSO www domain');
TestHelper::assertTrue(!validateKso('https://kso.org.tr.attacker.com/'), 'KSO attacker domain');
TestHelper::assertTrue(!validateKso('https://attacker.com/?x=kso.org.tr'), 'KSO attacker query');

echo "\n--- VALIDATION TESTS ---\n";
// 6. Email validation logic
function validateEmailSave($email) {
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    return true;
}
TestHelper::assertTrue(validateEmailSave('test@example.com'), 'Valid email passes');
TestHelper::assertTrue(!validateEmailSave('invalid-email'), 'Invalid email blocked');
TestHelper::assertTrue(validateEmailSave(''), 'Empty email passes');

// 7. Whatsapp logic tests
$emptyWa = LeadNormalizer::normalizePhone('');
TestHelper::assertEqual('', $emptyWa, 'Empty whatsapp remains empty after normalizer');

function isWaMobile($phone) {
    if (empty($phone)) return false;
    $p = LeadNormalizer::normalizePhone($phone);
    if (strlen($p) >= 10 && (strpos($p, '905') === 0)) return true;
    return false;
}
TestHelper::assertTrue(!isWaMobile('0332 123 45 67'), 'Fixed-line is NOT mobile');
TestHelper::assertTrue(isWaMobile('0532 123 45 67'), '053x is mobile');

