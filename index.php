<?php
// Start session with secure parameters
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'use_strict_mode' => true,
]);

// Define Constants
define('ROOT_DIR', __DIR__);
define('APP_DIR', ROOT_DIR . '/app');
define('CONFIG_DIR', ROOT_DIR . '/config');
define('STORAGE_DIR', ROOT_DIR . '/storage');
define('PUBLIC_DIR', ROOT_DIR . '/public');

// Load configurations
require_once CONFIG_DIR . '/config.php';

// Enable error reporting based on environment
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', STORAGE_DIR . '/logs/error.log');
    error_reporting(E_ALL);
}

// Simple Autoloader for Core, Controllers, Models, Helpers
spl_autoload_register(function ($class) {
    // Convert Namespace to directory paths (e.g. App\Core\Database -> app/Core/Database.php)
    $prefix = 'App\\';
    $base_dir = APP_DIR . '/';
    $len = strlen($prefix);
    
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the App
use App\Core\App;

$app = new App();
$app->run();
