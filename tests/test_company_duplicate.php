<?php

// tests/test_company_duplicate.php
require_once __DIR__ . '/../config/config.php'; // ensure this exists

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../' . str_replace('\\', '/', $class) . '.php';
    $file = str_replace('App/', 'app/', $file);
    if (file_exists($file)) require $file;
});

echo "This test requires a valid DB connection.\n";
try {
    $model = new \App\Models\Company();
    $res = $model->findPotentialDuplicate('Ajasis Media', '05321234567', 'https://ajasismedia.com');
    var_dump($res);
} catch (\Exception $e) {
    echo "DB not available: " . $e->getMessage() . "\n";
}
