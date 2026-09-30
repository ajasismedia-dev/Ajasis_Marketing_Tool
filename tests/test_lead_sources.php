<?php

// tests/test_lead_sources.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.example.php'; // Use example config to avoid DB issues during tests

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    $file = str_replace('App/', 'app/', $file);
    if (file_exists($file)) require $file;
});

echo "Testing LeadFinderService...\n";
$finder = new \App\Services\LeadFinder\LeadFinderService('all');

try {
    // Disable DB check in LeadFinderService for this test by passing false or mocking
    // Actually we can't easily mock it without editing the source, but LeadFinderService catches DB exceptions now.
    $results = $finder->search('plastik', 'Konya', '', 3);
    echo "Statuses:\n";
    print_r($results['statuses']);
    echo "\nResults Count: " . count($results['leads']) . "\n";
    
    // Print the first lead if exists
    if (!empty($results['leads'])) {
        print_r($results['leads'][0]);
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "Done.\n";
