<?php

/**
 * MenuImageService — Menentukan gambar menu/produk untuk katalog pelanggan.
 *
 * Prioritas:
 * 1. Gambar yang diunggah admin/kasir melalui Manajemen Barang.
 * 2. Foto lokal bawaan sesuai nama produk (agar katalog tetap menarik).
 * 3. Ilustrasi default untuk produk yang belum dikenali.
 */
class MenuImageService
{
    private const MARKETING_IMAGES = [
        'americano'    => 'americano.jpg',
        'butterscoth'  => 'butterscotch.jpg',
        'butterscotch' => 'butterscotch.jpg',
        'kopi susu'    => 'kopi-susu.jpg',
        'matcha'       => 'matcha.jpg',
        'roti bakar'   => 'roti-bakar.jpg',
        'roti'         => 'roti-bakar.jpg',
        'toast'        => 'roti-bakar.jpg',
        'croissant'    => 'croissant-butter.jpg',
        'mix platter'  => 'mix-platter.jpg',
        'platter'      => 'mix-platter.jpg',
        'dimsum'       => 'dimsum.jpg',
        'red velvet drink' => 'red-velvet-drink.jpg',
        'red velvet'   => 'red-velvet.jpg',
        'kentang'      => 'kentang.jpg',
        'fries'        => 'kentang.jpg',
        'mineral'      => 'mineral.jpg',
        'air'          => 'mineral.jpg',
        'chocohazelnut'=> 'chocohazelnut.jpg',
        'chocolate'    => 'chocohazelnut.jpg',
        'coklat'       => 'chocohazelnut.jpg',
        'choco'        => 'chocohazelnut.jpg',
        // Pencocokan umum diletakkan terakhir agar tidak menimpa nama yang lebih spesifik.
        'kopi'         => 'kopi-susu.jpg',
        'latte'        => 'kopi-susu.jpg',
    ];

    /**
     * @return array{url:string,is_fallback:bool}
     */
    public static function resolve(?string $productName, ?string $image): array
    {
        $image = trim((string)$image);

        if ($image !== '') {
            $path = self::publicUploadsDir() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($image, '/\\'));

            if (is_file($path)) {
                return [
                    'url'         => BASEURL . '/uploads/' . self::encodeRelativePath($image),
                    'is_fallback' => false,
                ];
            }
        }

        $fallback = self::fallbackFile($productName);
        if (!is_file(self::publicMenuDir() . DIRECTORY_SEPARATOR . $fallback)) {
            $fallback = 'default.svg';
        }

        return [
            'url'         => BASEURL . '/img/menu/' . $fallback,
            'is_fallback' => true,
        ];
    }

    public static function fallbackFile(?string $productName): string
    {
        $name = strtolower(trim((string)$productName));

        foreach (self::MARKETING_IMAGES as $needle => $file) {
            if ($name !== '' && str_contains($name, $needle)) {
                return $file;
            }
        }

        return 'default.svg';
    }

    public static function publicUploadsDir(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
    }

    public static function publicMenuDir(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'menu';
    }

    private static function encodeRelativePath(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $path))));
    }
}
