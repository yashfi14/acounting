<?php

/**
 * AICryptoService - Enkripsi simetris untuk kredensial AI (API key per provider).
 *
 * - Algoritma : AES-256-GCM (authenticated encryption)
 * - Kunci     : dibaca dari AI_ENCRYPTION_KEY (environment), jika kosong
 *               dibuat otomatis di storage/ai_encryption.key (di luar database
 *               dan di luar public web root).
 * - Tidak pernah mencatat plaintext / ciphertext / kunci ke log.
 */
class AICryptoService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            throw new InvalidArgumentException('Nilai yang dienkripsi tidak boleh kosong.');
        }

        $key = self::getKey();
        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException('Gagal mengenkripsi kredensial AI.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): ?string
    {
        if ($payload === '') {
            return null;
        }

        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return null;
        }

        $iv         = substr($raw, 0, self::IV_LENGTH);
        $tag        = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::getKey(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return $plaintext === false ? null : $plaintext;
    }

    /**
     * Kunci 32 byte mentah.
     */
    private static function getKey(): string
    {
        $envKey = defined('AI_ENCRYPTION_KEY') ? trim((string)AI_ENCRYPTION_KEY) : '';
        if ($envKey !== '') {
            $decoded = base64_decode($envKey, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
            // Dukung kunci 32 karakter mentah (bukan base64).
            if (strlen($envKey) === 32) {
                return $envKey;
            }
            throw new RuntimeException('AI_ENCRYPTION_KEY tidak valid. Gunakan 32 byte base64.');
        }

        return self::getOrCreateKeyFile();
    }

    private static function getOrCreateKeyFile(): string
    {
        $storageDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage';
        $keyFile    = $storageDir . DIRECTORY_SEPARATOR . 'ai_encryption.key';

        if (is_file($keyFile)) {
            $raw = trim((string)file_get_contents($keyFile));
            $decoded = base64_decode($raw, true);
            if ($decoded !== false && strlen($decoded) === 32) {
                return $decoded;
            }
            throw new RuntimeException('File kunci enkripsi AI tidak valid.');
        }

        if (!is_dir($storageDir) && !@mkdir($storageDir, 0700, true) && !is_dir($storageDir)) {
            throw new RuntimeException('Folder storage tidak dapat dibuat.');
        }

        $key = random_bytes(32);
        $written = @file_put_contents($keyFile, base64_encode($key), LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('Kunci enkripsi AI tidak dapat disimpan. Periksa izin folder storage.');
        }
        @chmod($keyFile, 0600);

        return $key;
    }
}
