<?php

/**
 * Interface untuk semua AI Provider.
 * Setiap provider (Gemini, OpenAI, Mock, dll) wajib mengimplementasikan interface ini.
 */
interface AIProviderInterface {
    /**
     * Mengajukan pertanyaan ke model AI dan mengembalikan jawabannya.
     * 
     * @param string $question Pertanyaan dari pengguna.
     * @param array  $history  Riwayat percakapan sebelumnya (opsional, format: [['role'=>'user','content'=>'...'],['role'=>'model','content'=>'...']])
     * @return string Jawaban dari AI.
     */
    public function ask(string $question, array $history = []): string;
}
