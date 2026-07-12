<?php
/**
 * EO Finance — Inisialisasi dan konfigurasi keamanan sesi PHP.
 *
 * File ini WAJIB di-include PERTAMA sebelum file config lainnya
 * di setiap halaman yang membutuhkan sesi (hampir semua halaman).
 *
 * Urutan include yang benar di setiap halaman:
 *   require_once __DIR__ . '/../config/session.php';
 *   require_once __DIR__ . '/../config/config.php';
 *   require_once __DIR__ . '/../config/database.php';
 *
 * @package EOFinance
 */

// ── Jangan jalankan ulang jika sesi sudah aktif ──────────────────────────────
if (session_status() === PHP_SESSION_ACTIVE) {
    return;
}

// ── Konfigurasi cookie sesi sebelum session_start() ──────────────────────────
// Semua parameter harus diset VOR session_start() agar berlaku efektif.

// HttpOnly: cookie tidak bisa dibaca lewat JavaScript (mitigasi XSS cookie theft)
ini_set('session.cookie_httponly', 1);

// SameSite=Strict: cookie tidak dikirim pada request lintas-situs (mitigasi CSRF)
ini_set('session.cookie_samesite', 'Strict');

// Secure: hanya kirim cookie lewat HTTPS.
// Di XAMPP (HTTP lokal) ini dinonaktifkan agar sesi tetap berjalan saat development.
// WAJIB diaktifkan (set ke 1) saat deploy ke production dengan HTTPS.
ini_set('session.cookie_secure', 0); // Ubah ke 1 di production

// Larang akses cookie sesi lewat URL (?PHPSESSID=...) — hanya lewat cookie
ini_set('session.use_only_cookies', 1);

// Gunakan mode ketat: session ID yang tidak dikenali server akan ditolak,
// bukan dibuat baru secara diam-diam (mencegah session fixation via URL)
ini_set('session.use_strict_mode', 1);

// Masa aktif cookie sesi: 0 = hilang saat browser ditutup (bukan persistent)
ini_set('session.cookie_lifetime', 0);

// Probabilitas garbage collection sesi kadaluarsa (1/100 = 1% setiap request)
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);

// Waktu idle maksimum sesi sebelum dianggap kadaluarsa oleh GC: 2 jam
ini_set('session.gc_maxlifetime', 7200);

// ── Mulai sesi ────────────────────────────────────────────────────────────────
session_start();

// ── Regenerasi ID sesi untuk mencegah Session Fixation ───────────────────────
// Session fixation: penyerang memberi korban session ID yang sudah diketahui,
// lalu menunggu korban login — setelah login, penyerang "ikut" terautentikasi.
//
// Strategi: regenerasi ID sesi sekali per sesi baru (belum punya flag regenerated).
// Tidak dilakukan setiap request agar tidak merusak tab yang dibuka paralel.
if (empty($_SESSION['__session_initialized'])) {
    session_regenerate_id(true); // true = hapus file sesi lama di server
    $_SESSION['__session_initialized'] = true;
}

// ── Validasi User-Agent untuk mendeteksi session hijacking ───────────────────
// Jika User-Agent berubah di tengah sesi, kemungkinan besar sesi sedang dibajak.
// Ini bukan proteksi mutlak (UA bisa dipalsukan), tapi menambah lapisan pertahanan.
if (isset($_SESSION['__user_agent'])) {
    if ($_SESSION['__user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        // User-Agent berbeda dari awal sesi: hancurkan sesi dan paksa login ulang
        session_unset();
        session_destroy();
        session_start();
        session_regenerate_id(true);
        // Redirect ke login ditangani oleh auth.php (includes/auth.php),
        // bukan di sini — session.php hanya bertanggung jawab pada sesi itu sendiri.
    }
} else {
    // Pertama kali: catat User-Agent untuk validasi request berikutnya
    $_SESSION['__user_agent'] = $_SERVER['HTTP_USER_AGENT'];
}