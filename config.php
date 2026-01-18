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
define('BASE_CURRENCY', 'GBP');

// Currency settings - Multiple currencies supported
// Exchange rates are relative to GBP (how much 1 unit of foreign currency = in GBP)
define('CURRENCIES', [
    'GBP' => ['symbol' => '£', 'name' => 'UK Pound', 'rate' => 1],           // 1 GBP = 1 GBP (base)
    'CNY' => ['symbol' => '¥', 'name' => 'Chinese Yuan', 'rate' => 0.1075],  // 1 CNY = 0.1075 GBP
    'JPY' => ['symbol' => '¥', 'name' => 'Japanese Yen', 'rate' => 0.00538], // 1 JPY = 0.00538 GBP
    'USD' => ['symbol' => '$', 'name' => 'US Dollar', 'rate' => 0.78],       // 1 USD = 0.78 GBP
    'EUR' => ['symbol' => '€', 'name' => 'Euro', 'rate' => 0.85],            // 1 EUR = 0.85 GBP
]);

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
