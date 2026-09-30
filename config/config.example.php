<?php
// Base Path - the folder name where the app resides on the server.
// For production it should match your folder in public_html, e.g. '/marketingtool'
// If running from root, set it to ''
define('BASE_PATH', '/marketingtool');
define('APP_URL', 'http://localhost' . BASE_PATH); // Change to domain in prod

// Environment
define('ENVIRONMENT', 'development'); // Change to 'production' in live

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');
