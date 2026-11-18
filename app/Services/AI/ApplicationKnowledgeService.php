<?php

/**
 * ApplicationKnowledgeService — Sumber kebenaran dokumentasi, menu, alur fitur,
 * dan batasan resmi sistem Acounting.
 * Memastikan AI hanya menjelaskan fitur yang benar-benar ada dan menolak mengarang fitur.
 */
class ApplicationKnowledgeService {

    /**
     * Penjelasan umum aplikasi Acounting
     */
    public static function getAppOverview(): string
    {
        return "Aplikasi **Acounting** adalah sistem informasi kasir (Point of Sale / POS) dan manajemen inventaris stok barang berbasis web yang dirancang khusus untuk pelaku UMKM dan toko retail.\n\n"
             . "Sistem dibangun menggunakan arsitektur MVC (Model-View-Controller) Native PHP 8+, basis data MySQL (PDO Prepared Statements), antarmuka modern Bootstrap 5, Vanilla CSS, serta visualisasi Chart.js.";
    }

    /**
     * Daftar menu resmi dan rute URL aplikasi
     */
    public static function getOfficialMenus(): array
    {
        return [
            'dashboard' => [
                'name'  => 'Dashboard',
                'url'   => '/dashboard',
                'role'  => ['admin', 'kasir'],
                'desc'  => 'Halaman utama ringkasan performa toko: kartu statistik (omset, transaksi, varian produk, stok fisik), grafik tren penjualan 7 hari terakhir, donut chart status inventori, rekomendasi AI, dan nota transaksi terbaru.'
            ],
            'product' => [
                'name'  => 'Manajemen Barang',
                'url'   => '/product',
                'role'  => ['admin', 'kasir'],
                'desc'  => 'Katalog inventaris toko: melihat daftar produk, pencarian barang, filter stok, menambah barang baru (Admin), mengubah harga/stok (Admin), dan menghapus barang (Admin).'
            ],
            'cashier' => [
                'name'  => 'Transaksi Kasir (POS)',
                'url'   => '/cashier',
                'role'  => ['admin', 'kasir'],
                'desc'  => 'Stasiun penjualan kasir instan: pencarian produk cepat, keranjang belanja, kalkulasi otomatis subtotal & total, input nominal uang tunai bayar, hitung kembalian otomatis, validasi stok fisik ketat, dan cetak struk nota belanja.'
            ],
            'transaction' => [
                'name'  => 'Riwayat Transaksi',
                'url'   => '/transaction',
                'role'  => ['admin', 'kasir'],
                'desc'  => 'Histori nota penjualan: pencarian ID nota (#TRX-xxxx), filter rentang tanggal, lihat rincian detail produk yang dibeli, cetak ulang nota, serta pembatalan/penghapusan transaksi dengan Auto-Restock otomatis (Admin).'
            ],
            'sales' => [
                'name'  => 'Database Penjualan',
                'url'   => '/sales',
                'role'  => ['admin'],
                'desc'  => 'Laporan keuangan dan audit penjualan toko (khusus Admin): filter periode transaksi, ringkasan akumulasi omset harian/bulanan, dan rekap performa kasir.'
            ],
            'users' => [
                'name'  => 'Manajemen Pengguna',
                'url'   => '/users',
                'role'  => ['admin'],
                'desc'  => 'Pengelolaan akun pengguna (khusus Admin): menambahkan akun kasir baru, mengaktifkan/menonaktifkan akun staf, dan reset akses.'
            ],
            'catalog' => [
                'name'  => 'Katalog Barang',
                'url'   => '/catalog',
                'role'  => ['pelanggan', 'kasir', 'admin'],
                'desc'  => 'Katalog produk publik yang dapat diakses oleh Pelanggan untuk melihat daftar menu aktif, harga satuan, dan status ketersediaan barang (Tersedia / Habis).'
            ],
            'ai' => [
                'name'  => 'AI Assistant (Aca)',
                'url'   => '/ai',
                'role'  => ['admin', 'kasir', 'pelanggan'],
                'desc'  => 'Asisten Aca yang menjawab dari database Acounting terlebih dahulu (stok, harga, transaksi, laporan, panduan) sesuai hak akses. Provider AI (Gemini, OpenAI, DeepSeek, Claude, OpenRouter, atau OpenAI-compatible) hanya dipakai untuk pertanyaan dalam ruang lingkup Acounting yang belum terjawab lokal.'
            ],
            'ai_settings' => [
                'name'  => 'Pengaturan Aca',
                'url'   => '/aisettings',
                'role'  => ['admin'],
                'desc'  => 'Konfigurasi Aca khusus Admin: memilih provider dan model, menyimpan API key per provider secara terenkripsi, mengatur temperature/batas output, menguji koneksi nyata, menentukan provider cadangan, serta melihat audit log. Provider hanya aktif setelah uji koneksi berhasil.'
            ],
            'profile' => [
                'name'  => 'Profil Saya',
                'url'   => '/profile',
                'role'  => ['admin', 'kasir', 'pelanggan'],
                'desc'  => 'Pengaturan akun pengguna: melihat User ID, nama lengkap, email, nomor WhatsApp, status verifikasi ganda, serta opsi ubah profil dan password.'
            ],
        ];
    }

    /**
     * Panduan operasional langkah-demi-langkah fitur nyata
     */
    public static function getGuides(): array
    {
        return [
            'tambah_barang' => [
                'title' => 'Cara Menambahkan Barang Baru',
                'menu'  => 'Manajemen Barang',
                'steps' => [
                    "Buka menu **Manajemen Barang** di sidebar kiri.",
                    "Klik tombol **+ Tambah Barang** (tombol berwarna di kanan atas tabel).",
                    "Isi formulir barang:\n   - **Nama Barang**: Nama produk yang ingin dijual.\n   - **Stok Awal**: Jumlah kuantitas fisik awal (angka bulat, minimal 0).\n   - **Harga Satuan**: Nilai jual per unit dalam Rupiah (tanpa titik/koma).",
                    "Klik tombol **Simpan Barang**.",
                    "Barang baru otomatis tersimpan di database dan langsung siap dijual di menu Kasir maupun ditampilkan di Katalog."
                ]
            ],
            'edit_barang' => [
                'title' => 'Cara Mengubah Data / Stok / Harga Barang',
                'menu'  => 'Manajemen Barang',
                'steps' => [
                    "Buka menu **Manajemen Barang** di sidebar.",
                    "Cari barang yang ingin diubah menggunakan kolom pencarian.",
                    "Klik tombol ✏️ **Edit** pada baris barang tersebut.",
                    "Perbarui nama barang, kuantitas stok fisik, atau harga satuannya.",
                    "Klik tombol **Update Barang** untuk menyimpan pembaruan ke sistem."
                ]
            ],
            'hapus_barang' => [
                'title' => 'Cara Menghapus Barang dari Toko',
                'menu'  => 'Manajemen Barang',
                'steps' => [
                    "Buka menu **Manajemen Barang** di sidebar.",
                    "Temukan barang yang hendak dihapus.",
                    "Klik tombol 🗑️ **Hapus** (warna merah) di baris barang tersebut.",
                    "Konfirmasi dialog peringatan yang muncul.",
                    "Barang akan dihapus dari inventori toko secara permanen."
                ]
            ],
            'transaksi_kasir' => [
                'title' => 'Cara Melakukan Transaksi Penjualan di Kasir (POS)',
                'menu'  => 'Transaksi Kasir',
                'steps' => [
                    "Buka menu **Transaksi Kasir** di sidebar kiri.",
                    "Cari barang yang dipesan pelanggan melalui kolom pencarian atau klik produk yang tertera.",
                    "Klik pada produk untuk memasukkannya ke dalam tabel **Keranjang Belanja**.",
                    "Sesuaikan jumlah kuantitas (*Qty*) yang dibeli pelanggan.",
                    "Sistem secara otomatis menghitung subtotal tiap item dan total tagihan belanja secara real-time.",
                    "Masukkan nominal uang tunai yang diterima dari pembeli pada kolom **Uang Diterima / Bayar** untuk otomatis menghitung uang kembalian.",
                    "Klik tombol hijau **✅ Selesaikan Pembayaran**.",
                    "Setelah berhasil, stok barang di gudang otomatis terpotong dan nota resmi tercatat dengan nomor `#TRX-xxxx`."
                ]
            ],
            'cetak_struk' => [
                'title' => 'Cara Mencetak Struk & Nota Belanja',
                'menu'  => 'Transaksi Kasir & Riwayat Transaksi',
                'steps' => [
                    "**Saat Transaksi Selesai**: Tepat setelah menekan tombol *Selesaikan Pembayaran*, jendela pop-up berhasil akan menampilkan tombol **🖨️ Cetak Struk**.",
                    "**Cetak Ulang dari Riwayat**: Jika ingin mencetak ulang nota lama, buka menu **Riwayat Transaksi**.",
                    "Temukan nota yang diinginkan, lalu klik tombol 📄 **Detail**.",
                    "Di halaman rincian transaksi, tekan tombol **Cetak Struk** atau gunakan kombinasi keyboard `Ctrl + P`.",
                    "Format struk Acounting kompatibel dengan printer kasir thermal ukuran **58mm** dan **80mm**, serta printer kertas dokumen biasa."
                ]
            ],
            'hapus_transaksi' => [
                'title' => 'Cara Membatalkan / Menghapus Transaksi & Fitur Auto-Restock',
                'menu'  => 'Riwayat Transaksi',
                'steps' => [
                    "Buka menu **Riwayat Transaksi** dari sidebar.",
                    "Cari transaksi yang ingin dibatalkan (bisa menggunakan filter tanggal atau input ID nota).",
                    "Klik tombol 🗑️ **Hapus** (warna merah) pada baris transaksi tersebut.",
                    "Konfirmasi pembatalan pada kotak dialog yang muncul.",
                    "**Keunggulan Auto-Restock**: Sistem secara cerdas akan langsung **mengembalikan jumlah kuantitas stok barang** yang sebelumnya terjual ke gudang toko secara otomatis, sehingga inventori tetap akurat!"
                ]
            ],
            'laporan_penjualan' => [
                'title' => 'Cara Melihat Laporan Penjualan (Khusus Admin)',
                'menu'  => 'Database Penjualan',
                'steps' => [
                    "Pastikan Anda login sebagai **Administrator**.",
                    "Buka menu **Database Penjualan** di sidebar kiri bagian *Laporan & Manajemen*.",
                    "Gunakan filter rentang tanggal (*Tanggal Mulai* dan *Tanggal Selesai*) untuk menyaring periode laporan tertentu.",
                    "Sistem akan menampilkan rekap total omset kotor, daftar transaksi lengkap, nama kasir pelaksana, dan rincian item terjual."
                ]
            ],
            'tambah_kasir' => [
                'title' => 'Cara Menambahkan Akun Kasir Baru (Khusus Admin)',
                'menu'  => 'Manajemen Pengguna',
                'steps' => [
                    "Login sebagai **Administrator**.",
                    "Buka menu **Manajemen Pengguna** di sidebar kiri.",
                    "Klik tombol **+ Tambah Pengguna**.",
                    "Isi data kasir: Nama Lengkap, Alamat Email, Nomor WhatsApp aktif, Password, dan pilih Role: **Kasir**.",
                    "Klik **Simpan**.",
                    "Akun kasir dapat langsung digunakan login untuk mengoperasikan kasir POS toko Anda."
                ]
            ],
            'verifikasi_akun' => [
                'title' => 'Sistem Verifikasi Akun Ganda (Email & WhatsApp OTP)',
                'menu'  => 'Verifikasi Akun',
                'steps' => [
                    "Saat mendaftar akun baru, sistem menerapkan keamanan verifikasi ganda.",
                    "**Verifikasi Email**: Link aktivasi dikirim otomatis ke email pendaftar. Klik link tersebut untuk memverifikasi email.",
                    "**Verifikasi WhatsApp**: Kode OTP 6 digit dikirimkan ke nomor WhatsApp Anda. Masukkan kode 6 digit pada halaman verifikasi.",
                    "Tersedia tombol *Kirim Ulang OTP* dengan jeda waktu tunggu (cooldown) 60 detik jika pesan belum terkirim."
                ]
            ]
        ];
    }

    /**
     * Fitur-fitur yang BELUM ADA / BELUM DIDUKUNG di Acounting saat ini.
     * AI WAJIB jujur menyatakan fitur ini belum tersedia jika pengguna menanyakannya.
     */
    public static function getUnsupportedFeatures(): array
    {
        return [
            'multi_cabang' => [
                'keywords' => ['multi cabang', 'banyak cabang', 'cabang lain', 'multi outlet', 'toko cabang'],
                'explanation' => "Fitur **Multi-Cabang / Multi-Outlet terpisah** saat ini belum tersedia di Acounting. Sistem saat ini dioptimalkan untuk pengelolaan 1 toko/gerai UMKM per akun administrator."
            ],
            'qris_otomatis' => [
                'keywords' => ['qris otomatis', 'payment gateway', 'midtrans', 'xendit', 'edc otomatis', 'debit otomatis'],
                'explanation' => "Integrasi **Payment Gateway otomatis (seperti QRIS dinamis otomatis atau EDC bank)** belum tersedia di sistem kasir. Saat ini kasir Acounting beroperasi dengan metode pembayaran tunai/manual kasir dengan pencatatan otomatis."
            ],
            'voucher_diskon' => [
                'keywords' => ['kupon diskon', 'voucher promo', 'kode promo', 'potongan voucher'],
                'explanation' => "Fitur **Kupon / Voucher Diskon Otomatis** saat ini belum tersedia di sistem kasir. Anda dapat menyesuaikan harga promo secara langsung melalui menu Manajemen Barang."
            ],
            'refund_parsial' => [
                'keywords' => ['refund sebagian', 'retur 1 barang', 'batal sebagian', 'retur parsial'],
                'explanation' => "Fitur **Retur / Refund Parsial (sebagian item saja)** dalam satu nota belum didukung. Pembatalan transaksi saat ini dilakukan per nota belanja lengkap melalui menu Riwayat Transaksi dengan pengembalian stok penuh otomatis (*Auto-Restock*)."
            ],
            'aplikasi_mobile' => [
                'keywords' => ['aplikasi android', 'aplikasi ios', 'playstore', 'appstore', 'download apk'],
                'explanation' => "Aplikasi mobile native di Google Play Store atau Apple App Store belum tersedia. Namun, Acounting dirancang sepenuhnya **responsif mobile**, sehingga dapat diakses dengan sangat nyaman melalui browser smartphone atau tablet Anda."
            ],
            'barcode_hardware' => [
                'keywords' => ['scanner bluetooth', 'alat scanner barcode', 'hardware barcode', 'alat scan'],
                'explanation' => "Dukungan hardware alat scanner barcode fisik belum diintegrasikan secara khusus. Anda dapat mencari produk secara instan dan cepat dengan mengetikkan nama barang pada kolom pencarian kasir POS."
            ],
            'riwayat_stok' => [
                'keywords' => ['riwayat stok', 'histori stok', 'mutasi stok', 'stok masuk', 'stok keluar', 'kartu stok'],
                'explanation' => "Acounting belum memiliki **riwayat pergerakan stok** (stok masuk/keluar per tanggal). Sistem menyimpan stok terkini dan memotong stok otomatis saat transaksi kasir berhasil."
            ],
            'metode_pembayaran' => [
                'keywords' => ['metode pembayaran', 'payment method', 'tunai qris', 'pembayaran qris'],
                'explanation' => "Sistem belum menyimpan **metode pembayaran** per transaksi. Kasir mencatat nominal uang tunai yang diterima dan menghitung kembalian; tidak ada kolom tunai/QRIS/debit pada nota."
            ]
        ];
    }

    /**
     * Format rangkuman pengetahuan sistem untuk System Prompt AI
     */
    public static function getKnowledgePromptSnippet(): string
    {
        $text = "DOKUMENTASI FITUR RESMI ACOUNTING (WAJIB DIIKUTI):\n";
        $text .= "1. DASHBOARD (/dashboard): Stat card (Omset, Transaksi, Varian Produk, Stok Fisik), Grafik Tren Penjualan 7 Hari (Chart.js), Donut Chart Status Stok, dan AI Insight.\n";
        $text .= "2. MANAJEMEN BARANG (/product): Daftar barang, pencarian, tambah barang baru, edit harga/stok, hapus barang (khusus Admin).\n";
        $text .= "3. KASIR POS (/cashier): Pencarian instan, keranjang, kalkulasi otomatis, input bayar & kembalian, validasi stok, selesaikan transaksi, cetak struk nota.\n";
        $text .= "4. RIWAYAT TRANSAKSI (/transaction): Filter rentang tanggal, cari no nota #TRX-xxxx, rincian detail belanja, dan pembatalan transaksi dengan Auto-Restock otomatis.\n";
        $text .= "5. DATABASE PENJUALAN (/sales): Laporan omset lengkap & audit penjualan (HANYA UNTUK ADMIN).\n";
        $text .= "6. MANAJEMEN PENGGUNA (/users): Tambah kasir baru, atur status aktif/nonaktif (HANYA UNTUK ADMIN).\n";
        $text .= "7. KATALOG BARANG (/catalog): Halaman menu produk aktif untuk Pelanggan (harga & status ketersediaan).\n";
        $text .= "8. PROFIL SAYA (/profile): Cek status akun, email, WA, verifikasi ganda, ubah password.\n";
        $text .= "9. PENGATURAN ACA (/aisettings): Khusus Admin — pilih provider (Gemini/OpenAI/DeepSeek/Claude/OpenRouter/OpenAI-compatible), atur model dan parameter, simpan/hapus API key terenkripsi per provider, muat daftar model, uji koneksi nyata, atur provider cadangan, dan lihat audit log. Provider hanya aktif setelah uji koneksi berhasil.\n\n";

        $text .= "FITUR YANG BELUM ADA DI ACOUNTING (JANGAN MENGARANG):\n";
        $text .= "- Belum ada: Multi-cabang/outlet terpisah, Payment gateway QRIS dinamis otomatis, Kupon voucher diskon otomatis, Retur parsial per item, Aplikasi Android/iOS di Playstore.\n";
        $text .= "- Belum ada: Riwayat/mutasi stok (stok masuk-keluar per tanggal), kolom metode pembayaran per transaksi, perhitungan pajak/PPN, ekspor/impor data, cetak laporan PDF, dan data pelanggan per nota.\n";
        $text .= "- Jika pengguna bertanya tentang fitur di atas, jelaskan dengan jujur dan sopan bahwa fitur tersebut belum tersedia saat ini.\n";

        return $text;
    }
}
