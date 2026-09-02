<?php
/**
 * Application Configuration & Environment Loader
 * Supports .env files and environment variables with sensible defaults.
 */

// Prevent direct execution outside script
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Simple .env loader without external dependencies
function loadEnv($path = __DIR__ . '/.env') {
    if (!file_exists($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Handle quoted values vs unquoted inline comments
            if (preg_match('/^([\'"])(.*)\1\s*(#.*)?$/', $value, $matches)) {
                $value = $matches[2];
            } else {
                if (strpos($value, '#') !== false) {
                    $parts = explode('#', $value, 2);
                    $value = trim($parts[0]);
                }
            }

            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Load .env
loadEnv();

/**
 * Get environment/config variable with fallback default
 */
function env($key, $default = null) {
    $val = getenv($key);
    if ($val === false) {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }
    if ($val === 'true' || $val === '(true)') return true;
    if ($val === 'false' || $val === '(false)') return false;
    if ($val === 'empty' || $val === '(empty)') return '';
    if ($val === 'null' || $val === '(null)') return null;
    return $val;
}

return [
    'app' => [
        'name' => 'Akhil V V — Portfolio',
        'url' => env('APP_URL', 'https://akhilvv.github.io'),
        'author' => 'Akhil V V',
        'email' => 'vvakhilkarun@gmail.com',
        'phone' => '+91 8590449417',
        'location' => 'Kozhikode, Kerala, India',
    ],
    'mail' => [
        'smtp_host' => env('SMTP_HOST', 'smtp.gmail.com'),
        'smtp_port' => (int) env('SMTP_PORT', 587),
        'smtp_secure' => env('SMTP_SECURE', 'tls'), // 'tls', 'ssl', or 'none'
        'smtp_timeout' => (int) env('SMTP_TIMEOUT', 15),
        'smtp_user' => env('SMTP_USER', ''),
        'smtp_pass' => env('SMTP_PASS', ''),
        'from_email' => env('MAIL_FROM_EMAIL', 'no-reply@akhilvv.com'),
        'from_name' => env('MAIL_FROM_NAME', 'Portfolio Contact Inquiry'),
        'to_email' => env('MAIL_TO_EMAIL', 'vvakhilkarun@gmail.com'),
        'to_name' => 'Akhil V V',
    ],
    'security' => [
        'csrf_token_name' => '_csrf_token',
        'honeypot_field' => 'hp_confirm_code_val', // Hidden bot trap field
        'rate_limit_seconds' => 10,
    ]
];
