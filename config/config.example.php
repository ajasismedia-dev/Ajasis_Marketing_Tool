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

// Google Places API (New)
define('GOOGLE_PLACES_API_KEY', '');
define('GOOGLE_PLACES_ENABLED', true);

// Timezone Configuration
define('APP_TIMEZONE', 'Europe/Istanbul');
define('DB_TIMEZONE', '+03:00');
date_default_timezone_set(APP_TIMEZONE);

// Business Profile
define('BUSINESS_NAME', 'Ajasis Media');

// SMTP Configuration (Single-email sending via PHPMailer)
define('SMTP_ENABLED', false);
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls'); // 'tls', 'ssl', or ''
define('SMTP_FROM_EMAIL', '');
define('SMTP_FROM_NAME', 'Ajasis Media');
define('SMTP_REPLY_TO', '');


