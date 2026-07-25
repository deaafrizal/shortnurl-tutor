# DEASHORTN — Pemendek URL

[![Deploy ShortnURL](https://github.com/deaafrizal/shortnurl-tutor/actions/workflows/deploy.yml/badge.svg)](https://github.com/deaafrizal/shortnurl-tutor/actions/workflows/deploy.yml)

> **⚠️ Disclaimer:** Seluruh kode dalam repositori ini dibuat secara otomatis oleh **AI coding agent** (OpenCode) yang dioperasikan oleh **Dea Afrizal**. Proyek ini adalah hasil kolaborasi antara manusia dan AI untuk tujuan pembelajaran keamanan aplikasi web.

Aplikasi pemendek URL berbasis PHP dengan fokus keamanan tinggi, siap produksi.

---

## Fitur

| Kategori | Fitur |
|---|---|
| 🎯 **Fungsional** | Buat URL pendek, lacak klik, lihat & hapus URL |
| 🔒 **CSRF** | Token dengan rotasi setiap request |
| 🛡️ **XSS** | Semua output di-escape dengan `htmlspecialchars()` |
| 🗄️ **SQL Injection** | Prepared statement + `EMULATE_PREPARES=false` |
| ⏱️ **Rate Limiting** | 30 URL/jam/IP + 50 total/IP + 5 req/detik via nginx |
| 👤 **Privasi** | IP dianonimkan (IPv4: `192.168.1.xxx`, IPv6: 64-bit terakhir) |
| 🌐 **Anti Spoofing** | Hanya percaya `CF-Connecting-IP` jika dari Cloudflare |
| 🍪 **Session Aman** | HttpOnly + Secure + SameSite=Strict |
| 📋 **CSP** | `script-src` terbatas, `form-action 'self'`, anti-clickjacking |
| 🚀 **CI/CD** | GitHub Actions → test → deploy otomatis ke VPS |

---

## Persyaratan

- PHP 8.0+
- MySQL / MariaDB 5.7+
- Web server (Nginx / Apache)

---

## Instalasi

### 1. Clone repositori

```bash
git clone https://github.com/deaafrizal/shortnurl-tutor.git
cd shortnurl-tutor
```

### 2. Buat file `.env`

```bash
cp .env.example .env
```

Isi kredensial database:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=shortnurl
DB_USERNAME=shortnurl
DB_PASSWORD=your_strong_password
BASE_URL=http://localhost:8000
```

> Semua variabel `DB_*` **WAJIB** diisi. Aplikasi akan gagal jika ada yang kosong.

### 3. Buat database & tabel

```sql
CREATE DATABASE shortnurl;
USE shortnurl;

CREATE TABLE urls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_url TEXT NOT NULL,
    short_code VARCHAR(10) UNIQUE NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    click_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_short_code (short_code),
    INDEX idx_ip_address (ip_address)
);
```

### 4. Atur izin file

```bash
chmod 600 .env
chmod 755 public/
```

### 5. Jalankan aplikasi

**Server built-in PHP:**

```bash
cd public
php -S localhost:8000
```

Buka `http://localhost:8000`.

**Nginx / Apache:**

Arahkan `document root` ke folder `public/`.

---

## Keamanan

### 1. Validasi Kredensial Database
- Gagal total jika `.env` tidak ditemukan
- Semua kredensial database harus diisi eksplisit
- Tidak ada *fallback* hardcoded

### 2. Perlindungan CSRF
- Semua request POST divalidasi dengan token CSRF
- Token dibuat dengan `random_bytes()` (64 karakter hex)
- Validasi pakai `hash_equals()` — timing-safe
- **Token di-rotasi** setiap POST sukses — cegah *replay attack*

### 3. Perlindungan XSS
- Semua *output* data user melewati `htmlspecialchars()`
- Tidak ada *output* user yang mentah ke HTML

### 4. Anti SQL Injection
- Semua query database pakai *prepared statement*
- `PDO::ATTR_EMULATE_PREPARES = false` — paksakan *native prepared statement*
- Input user **tidak pernah** digabung langsung ke SQL

### 5. Rate Limiting

| Level | Batas | Konfigurasi |
|---|---|---|
| Aplikasi (hourly) | 30 URL/IP/jam | `src/RateLimiter.php` |
| Aplikasi (lifetime) | 50 URL/IP | `src/Shorten.php` |
| Nginx | 5 req/detik/IP | `nginx.conf` |
| iptables (SSH) | 4 koneksi/30 detik/IP | `before.rules` |

### 6. Anonimisasi IP
- IP di halaman "Active Users" dianonimkan
- IPv4: oktet terakhir diganti `xxx` (contoh: `192.168.1.xxx`)
- IPv6: 64 bit terakhir diganti `xxxx` (contoh: `2001:db8:xxxx:xxxx`)
- IP asli tetap tersimpan di DB, hanya tampilan publik yang dianonimkan

### 7. Anti IP Spoofing
- Header `CF-Connecting-IP` hanya dipercaya jika request benar dari Cloudflare
- 15 range IP Cloudflare dicek sebelum mempercayai header
- Header `X-Forwarded-From` **diabaikan** dari proxy tidak dikenal
- *Fallback* ke `REMOTE_ADDR` (IP koneksi TCP asli)
- Mencegah *bypass* otorisasi berbasis IP

### 8. Konfigurasi Session Aman
| Parameter | Nilai | Fungsi |
|---|---|---|
| `httponly` | `true` | Cookie tidak bisa diakses JavaScript |
| `secure` | `true` | Cookie hanya dikirim lewat HTTPS |
| `samesite` | `Strict` | Cookie tidak dikirim dari situs lain |
| `lifetime` | `0` | Session hangus saat browser ditutup |

### 9. Content Security Policy (CSP)
```
default-src 'self'
script-src 'self' https://cdn.tailwindcss.com
style-src 'self' 'unsafe-inline'
form-action 'self'
frame-ancestors 'none'
base-uri 'self'
```
- Blokir semua sumber daya dari domain tidak dikenal
- Hanya script dari `cdn.tailwindcss.com` yang diizinkan
- Form hanya bisa submit ke *origin* sendiri
- Cegah clickjacking via `frame-ancestors 'none'`

### 10. HTTP Security Headers

| Header | Nilai | Kegunaan |
|---|---|---|
| `X-Frame-Options` | `SAMEORIGIN` | Anti-clickjacking |
| `X-Content-Type-Options` | `nosniff` | Cegah MIME sniffing |
| `X-XSS-Protection` | `1; mode=block` | Filter XSS lawas |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Kontrol kebocoran referrer |

---

## CI/CD Pipeline

Setiap *push* ke branch `main` memicu *deploy* otomatis:

```
 1. Checkout kode
 2. Setup PHP 8.3 + ekstensi
 3. Validasi composer.json
 4. Cache dependensi Composer
 5. Install dependensi
 6. Cek sintaks PHP semua file
 7. Jalankan PHPUnit (17+ test case)
 8. Deploy ke VPS via SSH
```

**Secrets yang dibutuhkan di GitHub:**

| Secret | Deskripsi |
|---|---|
| `SSH_HOST` | IP VPS |
| `SSH_USER` | User SSH (root) |
| `SSH_PRIVATE_KEY` | *Private key* Ed25519 deploy |
| `DEPLOY_PATH` | Path absolut project di VPS |

---

## Struktur File

```
shortnurl-tutor/
├── .github/workflows/
│   └── deploy.yml              # Workflow CI/CD GitHub Actions
├── config/
│   └── database.php            # Koneksi & konfigurasi database
├── public/
│   └── index.php               # Entry point utama (front controller)
├── src/
│   ├── Csrf.php                # Token CSRF + konfigurasi session
│   ├── IpAnonymizer.php        # Anonimisasi alamat IP
│   ├── NotFoundException.php   # Exception 404 custom
│   ├── RateLimiter.php         # Logika rate limiting
│   ├── Redirect.php            # Logika redirect URL
│   └── Shorten.php             # Pemendek URL + validasi IP
├── tests/
│   ├── ShortenTest.php         # Unit test Shorten
│   └── RedirectTest.php        # Unit test Redirect
├── .env.example                # Template environment variables
├── .gitignore
├── composer.json
└── README.md
```

---

## Penggunaan

### Membuat URL Pendek

1. Buka halaman utama aplikasi
2. Masukkan URL panjang di kolom input
3. Klik **Shorten**
4. Salin URL pendek yang dihasilkan

### Melihat URL Anda

1. Klik **My URLs** di navigasi atas
2. Semua URL yang dibuat dari IP Anda akan tampil
3. Lihat jumlah klik & waktu pembuatan

### Menghapus URL

1. Buka **My URLs**
2. Cari URL yang ingin dihapus
3. Klik **Delete**
4. Konfirmasi penghapusan

---

## Environment Variables

```dotenv
# Aplikasi
APP_NAME              Nama aplikasi (default: DEASHORTN)
APP_ENV               Mode environment (default: production)
APP_DEBUG             Mode debug (default: false)

# Database — SEMUA WAJIB DIISI
DB_HOST               Host database
DB_PORT               Port database
DB_DATABASE           Nama database
DB_USERNAME           User database
DB_PASSWORD           Password database

# Aplikasi
BASE_URL              Base URL untuk tautan pendek (default: http://localhost)
```

---

## Batasan (Rate Limits)

| Batasan | Nilai | File Konfigurasi |
|---|---|---|
| Per jam | 30 URL/IP | `src/RateLimiter.php:9` |
| Seumur hidup | 50 URL/IP | `src/Shorten.php:9` |
| Nginx | 5 request/detik/IP | `nginx.conf` |
| SSH | 4 koneksi/30 detik/IP | `before.rules` |

Pesan error akan menampilkan sisa kuota yang tersedia.

---

## Yang Dipelajari dari Project Ini

| # | Materi | Status |
|---|---|---|
| 1 | Manajemen kredensial via `.env` | ✅ |
| 2 | Proteksi CSRF dengan rotasi token | ✅ |
| 3 | Pencegahan XSS via *output escaping* | ✅ |
| 4 | Pencegahan SQL Injection via *prepared statement* | ✅ |
| 5 | Rate limiting (aplikasi + nginx + iptables) | ✅ |
| 6 | Anonimisasi IP untuk privasi pengguna | ✅ |
| 7 | Anti IP spoofing dengan validasi Cloudflare | ✅ |
| 8 | Konfigurasi session aman (HttpOnly + Secure + SameSite) | ✅ |
| 9 | Content Security Policy (CSP) headers | ✅ |
| 10 | CI/CD otomatis via GitHub Actions | ✅ |
| 11 | Hardening SSH server (fail2ban, key-only auth) | ✅ |
| 12 | *Error handling* yang baik | ✅ |

---

## Masalah Umum

| Masalah | Solusi |
|---|---|
| `FATAL ERROR: .env file not found` | Jalankan `cp .env.example .env` dan isi kredensial |
| `Missing required database environment variables` | Pastikan semua `DB_*` terisi di `.env` |
| `Database connection failed` | Cek kredensial database & pastikan MySQL berjalan |
| `Rate limit exceeded` | Terlalu banyak membuat URL dalam 1 jam. Coba lagi nanti |

---

## Rekomendasi Tambahan

✅ = sudah diimplementasikan, ☐ = masih perlu ditambahkan.

```
✅ HTTPS/SSL enforcement (redirect nginx)
✅ Security headers (CSP, X-Frame-Options, dll)
✅ CSRF protection dengan token rotation
✅ Session hardening (HttpOnly, Secure, SameSite)
✅ Rate limiting (aplikasi + nginx + iptables)
✅ Fail2ban integration
✅ IP anonymization (privasi)
☐ User authentication (login/register)
☐ Quota berbasis user (bukan IP)
☐ Audit logging (monitoring file)
☐ Backup dan disaster recovery
☐ Enkripsi database at rest
☐ Web Application Firewall (WAF)
☐ Subresource Integrity (SRI) untuk CDN
☐ Penetration testing
```

---

## Lisensi

MIT License — lihat file `LICENSE` untuk detail.

## Kontribusi

Project ini bersifat edukasi. Kontribusi dan masukan sangat diterima!

## Dukungan

Untuk issue atau pertanyaan, silakan buka GitHub issue.

## Pembuat

Dibuat oleh [@deaafrizal](https://github.com/deaafrizal) sebagai sumber belajar.
