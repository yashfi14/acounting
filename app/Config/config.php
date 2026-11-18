<?php

/**
 * Konfigurasi Dasar Aplikasi Acounting
 *
 * Jangan pernah hardcode nilai sensitif di sini.
 * Semua konfigurasi dibaca dari file .env
 */

// === Load .env ===
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'"); // strip optional quotes

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
            $_ENV[$name]    = $value;
            $_SERVER[$name] = $value;
        }
    }
}

loadEnv(__DIR__ . '/../../.env');

// === Environment ===
define('APP_ENV', getenv('APP_ENV') ?: 'production');

// === Timezone ===
date_default_timezone_set('Asia/Jakarta');

// === Error Reporting (security: jangan tampilkan error di production) ===
if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// === Base URL ===
define('BASEURL', getenv('BASEURL') ?: 'http://localhost/xampp/acounting/public');
if (!defined('BASE_URL')) {
    define('BASE_URL', BASEURL);
}

// === Database ===
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'acounting_db');

// === WhatsApp API ===
define('WA_API_KEY', getenv('WA_API_KEY') ?: '');
define('WA_API_URL', getenv('WA_API_URL') ?: '');

// === Email SMTP ===
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_USER', getenv('SMTP_USER') ?: '');
define('SMTP_PASS', getenv('SMTP_PASS') ?: '');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));

// === AI Assistant ===
define('AI_PROVIDER', getenv('AI_PROVIDER') ?: 'mock');
define('AI_API_KEY',  getenv('AI_API_KEY')  ?: '');
define('AI_MODEL', getenv('AI_MODEL') ?: 'gemini-flash-lite-latest');
define('AI_TEMPERATURE', (float)(getenv('AI_TEMPERATURE') !== false ? getenv('AI_TEMPERATURE') : 0.30));
define('AI_MAX_OUTPUT_TOKENS', (int)(getenv('AI_MAX_OUTPUT_TOKENS') ?: 800));

// Kunci enkripsi untuk API key yang disimpan di database.
// Wajib disimpan terpisah dari database (environment variable atau storage key file).
define('AI_ENCRYPTION_KEY', getenv('AI_ENCRYPTION_KEY') ?: '');
