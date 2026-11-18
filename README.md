# 📊 Acounting — Aplikasi Kasir & Manajemen Stok

**Acounting** adalah aplikasi manajemen stok dan kasir (Point-of-Sale) berbasis web yang dirancang untuk UMKM dan usaha kecil. Dibangun dengan arsitektur **MVC Native PHP** tanpa framework berat, namun dilengkapi sistem autentikasi berlapis, verifikasi identitas, dan AI Assistant.

---

## ✨ Fitur Utama

| Fitur | Deskripsi |
|-------|-----------|
| 🔐 **Autentikasi** | Registrasi, Login aman dengan session hardening & CSRF |
| ✅ **Verifikasi Ganda** | Verifikasi Email (link) + WhatsApp (OTP 6 digit) |
| 📊 **Dashboard** | Ringkasan statistik real-time (produk, stok, transaksi, penjualan) |
| 📦 **Manajemen Barang** | CRUD produk lengkap dengan pencarian, pagination, dan upload gambar menu (JPG/PNG/WEBP) |
| 🛍️ **Katalog Pelanggan** | Daftar menu dengan foto produk realistis, upload foto sendiri, harga, status stok, dan pencarian |
| 🛒 **Kasir (POS)** | Transaksi AJAX real-time, keranjang belanja, pengurangan stok otomatis |
| 📜 **Riwayat Transaksi** | Filter tanggal, pencarian, pagination, detail transaksi |
| 🤖 **AI Assistant** | Chat lokal-first dengan provider AI opsional (Gemini, OpenAI, DeepSeek, Claude, OpenRouter, OpenAI-compatible) |

---

## 🛡️ Fitur Keamanan

- **Session Fixation Protection** — `session_regenerate_id()` setelah login
- **CSRF Protection** — Token pada semua form POST dan AJAX
- **SQL Injection Prevention** — 100% Prepared Statements via PDO
- **XSS Prevention** — `htmlspecialchars()` pada semua output, `escapeHtml()` di JavaScript
- **Authorization** — Setiap resource divalidasi kepemilikan `user_id` di level query
- **Price Manipulation Prevention** — Harga diambil dari database server-side, bukan dari client
- **Secure Cookies** — `HttpOnly`, `SameSite=Lax`, `Secure` (jika HTTPS)
- **Error Disclosure** — PHP errors disembunyikan di mode production

---

## 🛠️ Teknologi

- **Backend**: PHP 8.0+ (Native MVC, tanpa framework)
- **Database**: MySQL 5.7+ / MariaDB 10.3+ via PDO
- **Frontend**: Bootstrap 5.3, Bootstrap Icons, Vanilla JS (ES6)
- **Font**: Google Fonts — Inter
- **Server**: Apache (XAMPP) dengan mod_rewrite

---

## 📁 Struktur Proyek

```
acounting/
├── .env                    ← Konfigurasi sensitif (JANGAN commit!)
├── .env.example            ← Template konfigurasi
├── .gitignore
├── README.md
├── database.sql            ← Skema database lengkap
│
├── app/
│   ├── Config/
│   │   └── config.php      ← Load .env & definisi konstanta
│   │
│   ├── Core/
│   │   ├── App.php         ← Router MVC
│   │   ├── Auth.php        ← Helper Auth::user()
│   │   ├── AuthHelper.php  ← Middleware: check(), checkVerified(), checkGuest()
│   │   ├── Controller.php  ← Base controller (view, model)
│   │   ├── CSRF.php        ← CSRF token generate & validate
│   │   └── Database.php    ← PDO wrapper (query, bind, execute, dll.)
│   │
│   ├── Controllers/
│   │   ├── AuthController.php          ← Login & Logout
│   │   ├── RegisterController.php      ← Registrasi akun
│   │   ├── VerificationController.php  ← Verifikasi email & WhatsApp OTP
│   │   ├── DashboardController.php     ← Halaman dashboard
│   │   ├── ProductController.php       ← CRUD barang & stok
│   │   ├── CashierController.php       ← Kasir / POS
│   │   ├── TransactionController.php   ← Riwayat transaksi
│   │   ├── AIController.php            ← AI Assistant
│   │   └── ErrorController.php         ← Halaman 404 & 500
│   │
│   ├── Models/
│   │   ├── User_model.php
│   │   ├── Verification_model.php
│   │   ├── Product_model.php
│   │   ├── Transaction_model.php
│   │   ├── TransactionDetail_model.php
│   │   ├── Dashboard_model.php
│   │   └── AIConversation_model.php
│   │
│   ├── Services/
│   │   ├── AI/
│   │   │   ├── AIProviderInterface.php  ← Interface provider AI
│   │   │   ├── AIService.php            ← Factory & facade
│   │   │   ├── MockAIProvider.php       ← Provider simulasi (development)
│   │   │   └── GeminiProvider.php       ← Google Gemini API (production)
│   │   ├── Email/
│   │   │   └── EmailService.php
│   │   └── WhatsApp/
│   │       └── WhatsAppService.php
│   │
│   └── Views/
│       ├── templates/
│       │   ├── auth_header.php / auth_footer.php
│       │   ├── header.php / footer.php
│       │   └── sidebar.php
│       ├── auth/          ← login.php
│       ├── register/      ← index.php, verify.php
│       ├── verification/  ← status.php, wa_otp.php
│       ├── dashboard/
│       ├── products/      ← index.php, create.php, edit.php
│       ├── cashier/
│       ├── transactions/  ← index.php, detail.php
│       ├── ai/
│       └── errors/        ← 404.php, 500.php
│
└── public/
    ├── .htaccess           ← URL Rewriting
    ├── index.php           ← Front Controller
    └── css/
        └── style.css
```

---

## ⚙️ Konfigurasi & Instalasi

### Prasyarat
- XAMPP (PHP 8.0+, MySQL 5.7+, Apache)
- Browser modern (Chrome, Firefox, Edge)

### Langkah 1: Clone / Salin Proyek

```bash
# Salin folder acounting ke dalam htdocs XAMPP
# Contoh: C:\xampp\htdocs\acounting
```

### Langkah 2: Setup Database

1. Buka **phpMyAdmin** di `http://localhost/phpmyadmin`
2. Buat database baru dengan nama `acounting_db`
3. Import file `database.sql`:
   - Klik tab **Import**
   - Pilih file `database.sql`
   - Klik **Go**

### Langkah 3: Konfigurasi Environment

Salin file `.env.example` menjadi `.env`:

```bash
# Windows
copy .env.example .env

# Linux/Mac
cp .env.example .env
```

Edit file `.env` sesuai konfigurasi lokal Anda:

```env
APP_ENV=development
BASEURL=http://localhost/xampp/acounting/public

DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=acounting_db
```

### Langkah 4: Aktifkan mod_rewrite Apache

Pastikan `mod_rewrite` aktif di Apache:
1. Buka **XAMPP Control Panel**
2. Klik **Config** pada Apache → `httpd.conf`
3. Cari dan pastikan baris ini tidak dikomentari:
   ```
   LoadModule rewrite_module modules/mod_rewrite.so
   ```

### Langkah 5: Akses Aplikasi

Buka browser dan akses:
```
http://localhost/xampp/acounting/public
```

---

## 🔧 Environment Variables

| Variabel | Deskripsi | Default |
|----------|-----------|---------|
| `APP_ENV` | Mode aplikasi (`development`/`production`) | `development` |
| `BASEURL` | URL dasar aplikasi | `http://localhost/xampp/acounting/public` |
| `DB_HOST` | Host database | `localhost` |
| `DB_USER` | Username database | `root` |
| `DB_PASS` | Password database | *(kosong)* |
| `DB_NAME` | Nama database | `acounting_db` |
| `SMTP_HOST` | Host SMTP untuk email | — |
| `SMTP_USER` | Username SMTP | — |
| `SMTP_PASS` | Password SMTP | — |
| `SMTP_PORT` | Port SMTP | `587` |
| `WA_API_KEY` | API key layanan WhatsApp | — |
| `WA_API_URL` | URL endpoint API WhatsApp | — |
| `AI_PROVIDER` | Provider default jika konfigurasi database belum ada | `mock` |
| `AI_API_KEY` | API key Gemini (fallback environment) | — |
| `OPENAI_API_KEY` | API key OpenAI (fallback environment) | — |
| `DEEPSEEK_API_KEY` | API key DeepSeek (fallback environment) | — |
| `ANTHROPIC_API_KEY` | API key Anthropic Claude (fallback environment) | — |
| `OPENROUTER_API_KEY` | API key OpenRouter (fallback environment) | — |
| `CUSTOM_AI_API_KEY` | API key provider OpenAI-compatible custom | — |
| `AI_ENCRYPTION_KEY` | Kunci enkripsi API key di database (wajib) | — |

> **Catatan Development**: Jika `APP_ENV=development`, sistem otomatis menampilkan link verifikasi email dan kode OTP WhatsApp langsung di halaman (tanpa perlu konfigurasi SMTP/WA).

---

## 🤖 Konfigurasi AI Assistant

### Mode Mock (Default — tanpa konfigurasi)
AI menggunakan rule-based responses untuk menjawab pertanyaan seputar penggunaan aplikasi. Tidak butuh API key.

```env
AI_PROVIDER=mock
```

### Multi-provider (disarankan)

Provider yang tersedia: **Relink AI Gateway**, **Google Gemini**, **OpenAI**, **DeepSeek**, **Anthropic Claude**, **OpenRouter**, dan **OpenAI-compatible custom**.

1. Jalankan migrasi:
   ```bash
   mysql -u root acounting_db < database/migration_ai_assistant.sql
   mysql -u root acounting_db < database/migration_ai_multi_provider.sql
   ```
2. Login sebagai Admin, buka **Pengaturan Aca**.
3. Pilih provider, isi API key, lalu klik **Muat Model** (daftar model diambil langsung dari provider; jika provider tidak menyediakan daftar model, isi nama model secara manual).
4. Klik **Uji & Aktifkan**. Provider utama hanya diganti jika request nyata ke model berhasil.
5. Untuk provider yang sedang aktif, perubahan API key, model, atau base URL harus melalui **Uji & Aktifkan**. Tombol **Simpan Konfigurasi** menolak perubahan tersebut agar konfigurasi yang belum diuji tidak langsung dipakai.
6. Untuk cadangan, isi bagian **Provider Cadangan** lalu klik **Uji & Simpan Cadangan**. Cadangan hanya dipakai bila provider utama gagal.
7. Status per provider, sumber key, dan hasil uji dapat dilihat pada tabel **Status Semua Provider** dan **Audit Log**.

API key disimpan per provider secara terenkripsi AES-256-GCM di tabel `aca_ai_provider_configs`. API key tidak pernah dikirim ke browser atau dicatat di log.

Fallback environment juga didukung (jika database belum memiliki key):

```env
AI_PROVIDER=gemini
AI_API_KEY=AIzaSy...key_anda
RELINK_API_KEY=sk-relink-...
OPENAI_API_KEY=sk-...
DEEPSEEK_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
OPENROUTER_API_KEY=sk-or-...
CUSTOM_AI_API_KEY=...
AI_MODEL=gemini-flash-lite-latest
```

> **Catatan model:** daftar model dari provider dapat memuat model yang sudah pensiun. Karena itu model aktif selalu diverifikasi dengan request nyata, bukan hanya karena API key tersimpan.

### Menambah provider baru

1. Tambahkan metadata di `app/Services/AI/AIProviderCatalog.php`.
2. Buat adapter yang mengimplementasikan `AIProviderAdapterInterface` di `app/Services/AI/Providers/`.
3. Daftarkan adapter di `AIProviderManager::makeAdapter()`.
4. Tidak perlu mengubah `AIService` atau halaman pengaturan; provider baru otomatis muncul di UI.

### Status Relink

**Relink AI Gateway** didukung sebagai provider OpenAI-compatible dengan base URL default `https://api.relink-gateway.biz.id/v1` dan model default `deepseek-v4-flash`. Tidak ada endpoint Relink terpisah yang di-hardcode; request memakai format `/models` dan `/chat/completions` seperti provider OpenAI-compatible lainnya. API key dapat disimpan terenkripsi di database atau melalui fallback environment `RELINK_API_KEY`. Jika gateway Anda memakai format API yang berbeda, sesuaikan adapter di `app/Services/AI/Providers/OpenAICompatibleProvider.php` atau tambahkan adapter khusus.

---

## ✅ Checklist Pengujian Manual

### 🔐 Autentikasi & Registrasi

- [ ] Buka `/register` — form muncul dengan benar
- [ ] Coba daftar dengan field kosong → harus muncul pesan error
- [ ] Coba daftar dengan email format tidak valid → error
- [ ] Coba daftar dengan password < 8 karakter → error
- [ ] Daftar dengan data valid → redirect ke halaman verifikasi
- [ ] Coba login dengan email/password salah → muncul pesan error umum
- [ ] Login dengan kredensial benar → redirect ke dashboard (atau verifikasi jika belum)
- [ ] Coba akses `/dashboard` tanpa login → redirect ke login
- [ ] Logout → sesi terhapus, redirect ke login
- [ ] Coba akses `/dashboard` setelah logout → redirect ke login

### ✅ Verifikasi Akun

- [ ] Setelah registrasi, halaman status verifikasi muncul
- [ ] Klik "Kirim Link Email" → link muncul di halaman (mode dev) atau dikirim via email
- [ ] Klik link verifikasi email → status email berubah terverifikasi ✅
- [ ] Klik "Kirim OTP WhatsApp" → OTP muncul di halaman (mode dev)
- [ ] Masukkan OTP yang benar → status WhatsApp terverifikasi ✅
- [ ] Coba kirim OTP berkali-kali → cooldown aktif (tombol disabled)
- [ ] Setelah keduanya terverifikasi → otomatis redirect ke dashboard

### 📊 Dashboard

- [ ] Dashboard menampilkan: total produk, total stok, jumlah transaksi, total penjualan
- [ ] Widget "Stok Menipis" muncul jika ada produk ≤ 5 unit
- [ ] "Transaksi Terbaru" menampilkan maks 5 transaksi
- [ ] Semua angka hanya milik akun yang sedang login

### 📦 Manajemen Barang

- [ ] Daftar barang kosong menampilkan empty state + tombol tambah
- [ ] Tambah barang dengan nama kosong → error validasi
- [ ] Tambah barang dengan stok negatif → error validasi
- [ ] Tambah barang dengan harga bukan angka → error validasi
- [ ] Tambah barang valid → muncul di daftar + flash success
- [ ] Edit barang → data lama ter-populate di form
- [ ] Update barang → data berubah di daftar
- [ ] Hapus barang → muncul konfirmasi, lalu barang terhapus
- [ ] Cari barang menggunakan keyword → hanya produk yang cocok ditampilkan
- [ ] **Security**: Coba akses `/product/edit/1` dengan akun lain → redirect, tidak bisa edit

### 🛒 Kasir

- [ ] Ketik nama barang di pencarian → hasil muncul real-time
- [ ] Klik barang → masuk ke keranjang
- [ ] Ubah kuantitas → subtotal dan total berubah otomatis
- [ ] Coba masukkan qty melebihi stok → peringatan muncul
- [ ] Selesaikan pembayaran → transaksi tersimpan, stok berkurang, keranjang kosong
- [ ] Cek di Manajemen Barang → stok sudah berkurang sesuai
- [ ] **Security**: Manipulasi harga via browser dev tools → server tetap menggunakan harga dari DB

### 📜 Riwayat Transaksi

- [ ] Daftar transaksi menampilkan data milik user sendiri
- [ ] Filter tanggal berfungsi
- [ ] Pencarian ID transaksi berfungsi
- [ ] Klik "Lihat Detail" → muncul daftar barang dalam transaksi
- [ ] **Security**: Coba akses `/transaction/detail/1` dengan akun lain → redirect, tidak tampil data

### 🤖 AI Assistant

- [ ] Halaman chat muncul dengan welcome message
- [ ] Kirim pertanyaan → AI merespons dengan typing indicator
- [ ] Riwayat percakapan tersimpan dan muncul saat reload
- [ ] Suggestion chips dapat diklik dan mengirim pertanyaan otomatis
- [ ] Hapus riwayat → chat dibersihkan

### 🛡️ Keamanan

- [ ] Form login memiliki `csrf_token` tersembunyi
- [ ] Form registrasi memiliki `csrf_token` tersembunyi
- [ ] Manipulasi `csrf_token` → request ditolak
- [ ] Akses URL acak (misal `/randompage`) → halaman 404 tampil
- [ ] Error 404 menampilkan tombol sesuai status login

---

## 📝 Lisensi

Proyek ini dibuat untuk keperluan edukasi dan demo. Bebas digunakan dan dimodifikasi.
