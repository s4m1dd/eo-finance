<?php
/**
 * EO Finance — Konstanta global aplikasi.
 *
 * Berisi semua nilai konfigurasi yang dipakai di seluruh sistem.
 * Ubah nilai-nilai di sini sesuai environment tanpa menyentuh logika bisnis.
 *
 * Dependensi: tidak ada (file ini tidak memerlukan include lain).
 *
 * @package EOFinance
 */

// ── Keamanan: blokir akses langsung ke file ini via browser ──────────────────
// Konstanta ini didefinisikan di setiap entry point (index.php, login.php, dll).
// File konfigurasi tidak boleh diakses sebagai halaman mandiri.
if (!defined('EO_FINANCE_APP')) {
    http_response_code(403);
    exit('Akses langsung ke file konfigurasi tidak diizinkan.');
}

// ════════════════════════════════════════════════════════════════════════════
// IDENTITAS APLIKASI
// ════════════════════════════════════════════════════════════════════════════

/** Nama aplikasi — ditampilkan di title, navbar, email, dan pesan sistem. */
define('APP_NAME', 'EO Finance');

/** Versi aplikasi — gunakan semantic versioning (MAJOR.MINOR.PATCH). */
define('APP_VERSION', '1.0.0');

// ════════════════════════════════════════════════════════════════════════════
// URL & PATH
// ════════════════════════════════════════════════════════════════════════════

/**
 * URL dasar aplikasi tanpa trailing slash.
 * Diambil dari .env (BASE_URL). Ubah ke URL production di .env saat deploy
 * (misal: https://eofinance.id) — jangan hardcode di sini.
 */
require_once __DIR__ . '/env.php';
define('BASE_URL', env('BASE_URL', 'http://localhost:8888/eo-finance'));

/**
 * Path absolut root project di filesystem server.
 * Digunakan untuk require_once, include, dan operasi file (upload, export).
 * dirname(__DIR__) → satu level di atas folder config/ = root project.
 */
define('BASE_PATH', dirname(__DIR__));

// UPLOAD_PATH dan UPLOAD_URL dihapus — bukti transaksi kini disimpan
// langsung di database sebagai BLOB (kolom bukti_data di tabel transaksi).
// Tidak ada folder upload yang diperlukan di filesystem.

// ════════════════════════════════════════════════════════════════════════════
// PAGINATION
// ════════════════════════════════════════════════════════════════════════════

/**
 * Jumlah baris data per halaman di semua tabel (event, transaksi, klien, dll).
 * Ubah nilai ini untuk menyesuaikan kepadatan data yang diinginkan.
 */
define('ITEMS_PER_PAGE', 10);

// ════════════════════════════════════════════════════════════════════════════
// SISTEM OTP — RESET PASSWORD
// ════════════════════════════════════════════════════════════════════════════

/**
 * Masa berlaku kode OTP dalam satuan MENIT.
 * Setelah waktu ini, kode OTP dianggap kadaluarsa dan harus diminta ulang.
 * Standar industri: 5–15 menit. Jangan terlalu panjang untuk alasan keamanan.
 */
define('OTP_EXPIRY_MINUTES', 5);

/**
 * Panjang kode OTP yang digenerate (jumlah digit angka).
 * 6 digit = 1.000.000 kemungkinan — cukup aman untuk expiry 5 menit.
 */
define('OTP_LENGTH', 6);

/**
 * Jumlah maksimum percobaan verifikasi OTP yang salah sebelum kode diinvalidasi.
 * Mencegah brute-force kode OTP 6 digit.
 */
define('OTP_MAX_ATTEMPTS', 5);

// ════════════════════════════════════════════════════════════════════════════
// UPLOAD FILE
// ════════════════════════════════════════════════════════════════════════════

/**
 * Ukuran maksimum file upload dalam BYTES.
 * 2 * 1024 * 1024 = 2 MB — cukup untuk bukti transaksi (foto/scan/PDF kecil).
 * Pastikan php.ini: upload_max_filesize dan post_max_size >= nilai ini.
 */
define('MAX_UPLOAD_SIZE_BYTES', 2 * 1024 * 1024);

/**
 * Tipe MIME yang diizinkan untuk upload bukti transaksi.
 * Validasi MIME dilakukan server-side (bukan hanya ekstensi file).
 */
define('ALLOWED_UPLOAD_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/pdf',
]);

// ════════════════════════════════════════════════════════════════════════════
// KEAMANAN PASSWORD
// ════════════════════════════════════════════════════════════════════════════

/**
 * Cost factor bcrypt untuk password_hash().
 * Nilai 12 adalah titik tengah yang baik: cukup lambat untuk brute-force,
 * cukup cepat untuk server low-end seperti XAMPP lokal.
 * Setiap kenaikan 1 = dua kali lebih lambat. Rekomendasi production: 12–14.
 */
define('BCRYPT_COST', 12);

/**
 * Panjang minimum password yang diterima sistem.
 * Berlaku untuk admin_eo (register mandiri) dan staff_eo (ganti password pertama).
 */
define('PASSWORD_MIN_LENGTH', 8);

// ════════════════════════════════════════════════════════════════════════════
// TIMEZONE & LOCALE
// ════════════════════════════════════════════════════════════════════════════

/**
 * Timezone default untuk seluruh aplikasi.
 * Semua operasi tanggal/waktu (transaksi, OTP, laporan) mengacu ke timezone ini.
 */
define('APP_TIMEZONE', 'Asia/Jakarta');

// Terapkan timezone ke PHP runtime sekarang
date_default_timezone_set(APP_TIMEZONE);

// ════════════════════════════════════════════════════════════════════════════
// MODE APLIKASI
// ════════════════════════════════════════════════════════════════════════════

/**
 * Mode debug aplikasi.
 * TRUE  → tampilkan error detail (development/XAMPP).
 * FALSE → sembunyikan error dari user, log ke file saja (production).
 *
 * Diambil dari .env (APP_DEBUG). Default FALSE jika .env tidak ada/tidak diset
 * — ini penting supaya kalau file .env lupa dibuat di server production,
 * aplikasi tetap "gagal aman" (fail-safe) dan tidak membocorkan detail error.
 * Untuk development lokal, set APP_DEBUG=true di file .env kamu.
 */
define('APP_DEBUG', env('APP_DEBUG', false));

// Konfigurasi error reporting sesuai mode debug

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}