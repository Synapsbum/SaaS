<?php
// Veritabanı Ayarları
define('DB_HOST', 'localhost');
define('DB_NAME', 'tokatbet_site');
define('DB_USER', 'tokatbet_site');
define('DB_PASS', '962xzZUJamRyWH4WYP4d');

// Cryptomus API
define('CRYPTOMUS_API_KEY', 'YOUR_API_KEY_HERE');
define('CRYPTOMUS_MERCHANT_ID', 'YOUR_MERCHANT_ID_HERE');
define('OXAPAY_API_KEY', 'I2TDZ7-OEQDSZ-GIYIYW-KSIEH4');
define('OXAPAY_SANDBOX', false); // Test için true yap
// Site Ayarları
define('SITE_NAME', 'tokat.bet Scripts');
define('SITE_URL', 'https://tokat.bet');
define('BASE_PATH', '/');

// Güvenlik
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200);
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params(SESSION_LIFETIME);

// Hata Ayarları
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Zaman Dilimi
date_default_timezone_set('Europe/Istanbul');

// Session Başlat
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>