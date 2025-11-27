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

// Base currency for total calculations
define('BASE_CURRENCY', 'CNY');

// Currency settings - Multiple currencies supported
// Exchange rates are relative to CNY (how much 1 unit of foreign currency = in CNY)
define('CURRENCIES', [
    'CNY' => ['symbol' => '¥', 'name' => 'Chinese Yuan', 'rate' => 1],
    'JPY' => ['symbol' => '¥', 'name' => 'Japanese Yen', 'rate' => 0.05],    // 1 JPY = 0.05 CNY
    'USD' => ['symbol' => '$', 'name' => 'US Dollar', 'rate' => 7.25],       // 1 USD = 7.25 CNY
]);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
