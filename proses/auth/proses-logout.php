<?php
/**
 * EO Finance — Proses logout.
 *
 * Menghancurkan sesi aktif secara aman lalu redirect ke halaman login.
 * Hanya menerima request GET atau POST dari pengguna yang sedang login.
 *
 * Alur:
 *   1. Tolak akses jika belum login (tidak ada sesi aktif)
 *   2. Validasi CSRF token (POST) atau konfirmasi via GET
 *   3. Kosongkan semua variabel sesi
 *   4. Hapus cookie sesi dari browser
 *   5. Hancurkan data sesi di server
 *   6. Mulai sesi baru yang bersih untuk flash message
 *   7. Redirect ke login dengan pesan konfirmasi
 *
 * @package EOFinance
 */

define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';

// ════════════════════════════════════════════════════════════════════════════
// 1. CEK SESI AKTIF
// Jika tidak ada user_id di sesi, pengguna memang belum login —
// tidak perlu proses destroy, langsung arahkan ke login.
// ════════════════════════════════════════════════════════════════════════════
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// 2. VALIDASI CSRF TOKEN
// Logout yang sah harus membawa token yang cocok dengan sesi.
// Mencegah CSRF logout: penyerang tidak bisa paksa user logout
// hanya dengan membuat mereka mengunjungi URL tertentu.
// Token diambil dari POST (form) atau GET (link dengan token).
// ════════════════════════════════════════════════════════════════════════════
$csrfPost    = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';

// hash_equals() mencegah timing attack pada perbandingan string rahasia
if (
    empty($csrfSession) ||
    empty($csrfPost) ||
    !hash_equals($csrfSession, $csrfPost)
) {
    // Token tidak valid — kemungkinan CSRF atau link logout yang kadaluarsa.
    // Catat ke log untuk monitoring.
    error_log(
        '[EO Finance] CSRF token tidak valid pada logout. ' .
        'user_id: ' . ($_SESSION['user_id'] ?? 'unknown') .
        ', IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
    );

    // Jangan hancurkan sesi — pengguna mungkin masih sah, hanya tokennya salah.
    // Redirect kembali ke dashboard dengan pesan peringatan.
    $dashboardUrl = match ($_SESSION['role'] ?? '') {
        'admin_eo' => BASE_URL . '/admin/dashboard.php',
        'staff_eo' => BASE_URL . '/staff/dashboard.php',
        default    => BASE_URL . '/login.php',
    };

    $_SESSION['__flash']['error'] = 'Permintaan tidak valid. Silakan coba lagi.';
    header('Location: ' . $dashboardUrl);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// 3–5. HANCURKAN SESI SECARA AMAN
// Urutan ini penting: kosongkan dulu, hapus cookie, baru destroy.
// Jangan balik urutannya — session_destroy() sebelum $_SESSION = []
// bisa menyisakan data di beberapa konfigurasi PHP.
// ════════════════════════════════════════════════════════════════════════════

// 3. Kosongkan semua variabel sesi di memori
$_SESSION = [];

// 4. Hapus cookie PHPSESSID dari browser agar ID sesi lama tidak bisa dipakai ulang
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,         // waktu lampau → browser hapus cookie
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 5. Hancurkan data sesi di server (file sesi di disk/Redis/DB terhapus)
session_destroy();

// ════════════════════════════════════════════════════════════════════════════
// 6. BUAT SESI BARU YANG BERSIH UNTUK FLASH MESSAGE
// session_destroy() membuat sesi tidak bisa dipakai lagi.
// Kita butuh sesi baru agar pesan sukses bisa dibawa ke halaman login
// tanpa menyimpannya di URL (query string).
// ════════════════════════════════════════════════════════════════════════════
session_start();
session_regenerate_id(true); // ID sesi baru yang belum diketahui siapapun

$_SESSION['__flash']['success'] = 'Kamu berhasil keluar. Sampai jumpa!';

// ════════════════════════════════════════════════════════════════════════════
// 7. REDIRECT KE LOGIN
// ════════════════════════════════════════════════════════════════════════════
header('Location: ' . BASE_URL . '/login.php');
exit();