<?php

class CSRF {
    
    /**
     * Generate or retrieve the CSRF token
     */
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Alias for token() — used in views via CSRF::generate()
     */
    public static function generate(): string
    {
        return self::token();
    }

    /**
     * Validate the provided CSRF token against the session token
     */
    public static function validate($token)
    {
        // Token harus string; array/objek dari input JSON tidak boleh
        // menyebabkan TypeError pada hash_equals().
        if (!is_string($token) || $token === '') {
            return false;
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
