<?php

/**
 * ProductImageService — Validasi dan penyimpanan gambar produk.
 *
 * File disimpan di public/uploads/products dan nama file yang disimpan
 * di kolom products.image berbentuk "products/namafile.ext".
 */
class ProductImageService
{
    private const MAX_BYTES = 3 * 1024 * 1024; // 3 MB
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * @return array{0:?string,1:?string} [relative path, error message]
     */
    public static function store(array $file): array
    {
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return [null, 'Gagal mengunggah gambar. Silakan coba lagi.'];
        }

        $tmp = $file['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return [null, 'File gambar tidak valid.'];
        }

        if ((int)($file['size'] ?? 0) > self::MAX_BYTES) {
            return [null, 'Ukuran gambar maksimal 3 MB.'];
        }

        $mime = null;
        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime  = $finfo->file($tmp) ?: null;
        }

        if ($mime === null) {
            $info = @getimagesize($tmp);
            $mime = $info['mime'] ?? null;
        }

        if (!isset(self::ALLOWED_MIME[$mime])) {
            return [null, 'Format gambar harus JPG, PNG, atau WEBP.'];
        }

        if (@getimagesize($tmp) === false) {
            return [null, 'File yang diunggah bukan gambar yang valid.'];
        }

        $dir = self::uploadsDir() . DIRECTORY_SEPARATOR . 'products';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return [null, 'Folder penyimpanan gambar tidak dapat dibuat.'];
        }

        $filename = 'product_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . self::ALLOWED_MIME[$mime];
        $target   = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmp, $target)) {
            return [null, 'Gambar gagal disimpan. Periksa izin folder uploads.'];
        }

        return ['products/' . $filename, null];
    }

    public static function delete(?string $relativePath): void
    {
        $relativePath = trim((string)$relativePath);
        if ($relativePath === '') {
            return;
        }

        $root = realpath(self::uploadsDir());
        if ($root === false) {
            return;
        }

        $candidate = realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\')));
        if ($candidate === false || !is_file($candidate)) {
            return;
        }

        $rootPrefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($candidate, $rootPrefix)) {
            @unlink($candidate);
        }
    }

    public static function uploadsDir(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
    }
}
