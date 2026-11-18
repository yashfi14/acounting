<?php

/**
 * HTTP client kecil untuk adapter provider AI.
 *
 * API key hanya dikirim lewat header oleh adapter, tidak pernah ditaruh di URL
 * dan tidak pernah dicatat ke log oleh class ini.
 */
class AIHttpClient
{
    public static function request(
        string $method,
        string $url,
        array $headers,
        ?array $payload = null,
        int $timeout = 25,
        int $connectTimeout = 8
    ): array {
        if (!function_exists('curl_init')) {
            return [
                'ok' => false,
                'http' => 0,
                'json' => [],
                'body' => '',
                'error' => 'Ekstensi cURL tidak tersedia di server.',
            ];
        }

        $ch = curl_init($url);
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => max(5, min(120, $timeout)),
            CURLOPT_CONNECTTIMEOUT => max(3, min(30, $connectTimeout)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Acounting-Aca/1.0 (+PHP cURL)',
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlNo = (int)curl_errno($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            $message = 'Koneksi ke provider AI gagal. Periksa jaringan server.';
            if (in_array($curlNo, [28, CURLE_OPERATION_TIMEDOUT], true)) {
                $message = 'Koneksi ke provider AI melewati batas waktu.';
            }
            return [
                'ok' => false,
                'http' => 0,
                'json' => [],
                'body' => '',
                'error' => $message,
            ];
        }

        $json = json_decode((string)$body, true);
        if (!is_array($json)) {
            $json = [];
        }

        return [
            'ok' => $http >= 200 && $http < 300,
            'http' => $http,
            'json' => $json,
            'body' => (string)$body,
            'error' => '',
        ];
    }

    public static function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $scheme = strtolower((string)$parts['scheme']);
        if ($scheme !== 'https' && $scheme !== 'http') {
            return '';
        }
        // Jangan izinkan kredensial, query, atau fragment di base URL.
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return '';
        }
        return rtrim($url, '/');
    }

    public static function errorCode(array $response): string
    {
        $http = (int)($response['http'] ?? 0);
        if ($http === 0) {
            return 'connection_error';
        }

        $body = mb_strtolower((string)($response['body'] ?? ''));
        if ($http === 401
            || strpos($body, 'invalid api key') !== false
            || strpos($body, 'incorrect api key') !== false
            || strpos($body, 'unauthorized') !== false) {
            return 'invalid_key';
        }
        if ($http === 403) {
            if (strpos($body, 'billing') !== false
                || strpos($body, 'quota') !== false
                || strpos($body, 'permission') !== false) {
                return 'permission_denied';
            }
            return 'permission_denied';
        }
        if ($http === 404) {
            return 'model_unavailable';
        }
        if ($http === 429) {
            return 'rate_limited';
        }
        if ($http === 400) {
            if (strpos($body, 'model') !== false
                && (strpos($body, 'not found') !== false
                    || strpos($body, 'does not exist') !== false
                    || strpos($body, 'invalid model') !== false)) {
                return 'model_unavailable';
            }
            return 'api_error';
        }
        if ($http >= 500) {
            return 'server_error';
        }
        return 'api_error';
    }

    public static function errorMessage(array $response, string $providerLabel): string
    {
        $http = (int)($response['http'] ?? 0);
        $error = (string)($response['error'] ?? '');
        if ($error !== '') {
            return $error;
        }

        $body = (string)($response['body'] ?? '');
        $json = json_decode($body, true);
        $detail = '';
        if (is_array($json)) {
            $detail = (string)($json['error']['message'] ?? $json['error']['detail'] ?? $json['message'] ?? '');
        }
        if ($detail === '') {
            $detail = trim(strip_tags(substr($body, 0, 300)));
        }

        // Jangan pernah mengembalikan pola API key ke UI/log.
        $detail = preg_replace('/\b(sk|AIza|sk-ant|sk-or)[A-Za-z0-9_\-\.]{8,}\b/i', '[REDACTED]', $detail);
        $detail = preg_replace('/key=[^\s&]+/i', 'key=[REDACTED]', $detail);
        $detail = mb_substr($detail, 0, 240);

        $code = self::errorCode($response);
        $prefix = match ($code) {
            'invalid_key' => 'API key ditolak',
            'permission_denied' => 'Akses/billing provider ditolak',
            'model_unavailable' => 'Model tidak tersedia',
            'rate_limited' => 'Kuota/rate limit habis',
            'connection_error' => 'Koneksi gagal',
            'server_error' => 'Server provider bermasalah',
            default => 'Request API gagal',
        };

        return $prefix . ' (' . $providerLabel . ', HTTP ' . $http . ')' . ($detail !== '' ? ': ' . $detail : '.');
    }

    public static function sanitizeModel(string $model): string
    {
        $model = trim($model);
        $model = preg_replace('#^models/#', '', $model);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{0,119}$/', $model)) {
            return '';
        }
        return $model;
    }
}
