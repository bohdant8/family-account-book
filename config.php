<?php
/**
 * Account Book Configuration
 * Family Account Book Application
 */

// Application settings
define('APP_NAME', 'Family Account Book');
define('APP_VERSION', '1.0.0');

// Database settings
define('DB_PATH', __DIR__ . '/data/accountbook.db');

// Timezone
date_default_timezone_set('Asia/Shanghai');

// Currency settings
define('CURRENCY_SYMBOL', '¥');
define('CURRENCY_CODE', 'CNY');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

