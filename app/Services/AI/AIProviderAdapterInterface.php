<?php

/**
 * Kontrak adapter provider AI.
 *
 * Setiap provider wajib menangani autentikasi, base URL, format request,
 * format response, batas token, dan pemetaan error secara mandiri.
 */
interface AIProviderAdapterInterface
{
    /**
     * Daftar model yang tersedia untuk API key ini.
     *
     * @return array{success:bool,code:string,message:string,models:array}
     */
    public function listModels(array $config): array;

    /**
     * Uji koneksi nyata dengan mengirim request ke model, bukan hanya cek key.
     *
     * @return array{success:bool,code:string,message:string,model?:string,duration_ms?:int,suggested_model?:?string}
     */
    public function testConnection(array $config): array;

    /**
     * Hasilkan jawaban. Melempar RuntimeException dengan pesan yang sudah aman
     * (tanpa API key) jika gagal.
     */
    public function generate(string $question, array $config, array $context = [], array $history = []): string;
}
