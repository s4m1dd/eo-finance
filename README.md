# EO Finance

Aplikasi manajemen keuangan event organizer (EO) berbasis PHP native + MySQL.

## Fitur

- **Autentikasi** — register, login, lupa/reset password, verifikasi OTP, 2FA (TOTP)
- **Peran ganda** — Admin EO (kelola staff, klien, penugasan, laporan) & Staff (input transaksi, kelola event)
- **Transaksi** — tambah/edit, approval admin, struk, bukti pembayaran tersimpan di DB
- **Event & klien** — CRUD event + invoice + kalender, kelola klien
- **Laporan** — rekap, cetak, export
- **Audit log** — jejak aktivitas admin

## Teknologi

PHP 8+ (tanpa framework), MySQL/MariaDB, Bootstrap via `assets/`. Tanpa Composer — konfigurasi dibaca dari file `.env` dengan loader native (`config/env.php`).

## Menjalankan

Prasyarat: PHP 8+ dengan ekstensi `mysqli`, MySQL/MariaDB.

```bash
# 1. Buat database + isi skema
mysql -u root -p < Database/eo_finance.sql

# 2. Konfigurasi environment
cp .env.example .env
#    edit .env → isi DB_PASS, sesuaikan BASE_URL

# 3. Jalankan server lokal
php -S localhost:8888
#    buka http://localhost:8888/
```

## Struktur

```
admin/            # panel admin EO
staff/            # panel staff
proses/           # handler form (auth, admin, staff)
includes/         # auth, header/footer, functions, sidebar
config/           # config, database, session, env loader
errors/           # halaman 403/404/500
Database/         # skema SQL lengkap
```

## Keamanan

- Password di-hash, session regenerate on login
- Prepared statements di seluruh query
- Bukti pembayaran disajikan lewat `serve-bukti.php` dengan scope per-role + allowlist MIME
- File `.env` tidak pernah di-commit (lihat `.gitignore`)
