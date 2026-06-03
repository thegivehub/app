<?php
require_once __DIR__ . '/Cache.php';

class Security {
    private static $logFile = __DIR__ . '/../logs/security.log';

    public static function sendHeaders() {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header("Content-Security-Policy: default-src 'self'");
    }

    public static function logEvent($type, array $context = []) {
        $entry = array_merge([
            'timestamp' => date('c'),
            'type' => $type,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], $context);
        $dir = dirname(self::$logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(self::$logFile, json_encode($entry) . PHP_EOL, FILE_APPEND);
    }

    public static function rateLimit($key, $max, $window) {
        $cacheKey = 'rate:' . $key;
        $record = Cache::get($cacheKey) ?: ['count' => 0, 'expires' => time() + $window];
        if ($record['expires'] < time()) {
            $record = ['count' => 0, 'expires' => time() + $window];
        }
        $record['count'] += 1;
        Cache::set($cacheKey, $record, $window);
        if ($record['count'] > $max) {
            self::logEvent('rate_limit_exceeded', ['key' => $key]);
            return false;
        }
        return true;
    }

    public static function enforceSessionCookiePolicy() {
        // Always set HttpOnly and a sane SameSite default
        @ini_set('session.cookie_httponly', '1');
        if (!ini_get('session.cookie_samesite')) {
            @ini_set('session.cookie_samesite', 'Lax');
        }
        // Enforce secure cookies in production or when behind HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        if ($isHttps || getenv('APP_ENV') === 'production') {
            @ini_set('session.cookie_secure', '1');
        }
    }
}
