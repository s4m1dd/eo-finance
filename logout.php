<?php
/**
 * EO Finance — Logout.
 *
 * Menghancurkan sesi aktif secara aman lalu redirect ke login.
 * Tidak menampilkan HTML — murni logika backend.
 *
 * Alur keamanan:
 *   1. Kosongkan semua variabel sesi ($_SESSION = [])
 *   2. Hapus cookie sesi dari browser
 *   3. Hancurkan data sesi di server (session_destroy)
 *   4. Redirect ke login dengan pesan konfirmasi
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/config.php';

// ── Hanya proses jika memang ada sesi aktif ───────────────────────────────────
// Jika sudah logout (tidak ada user_id), langsung redirect ke login
// tanpa melakukan proses destroy yang tidak perlu.
if (empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// ── Kosongkan semua data sesi ─────────────────────────────────────────────────
$_SESSION = [];

// ── Hapus cookie sesi dari browser ───────────────────────────────────────────
// Ini menghapus cookie PHPSESSID dari sisi client sehingga browser tidak
// menyimpan ID sesi lama yang bisa dipakai ulang.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,          // waktu lampau → browser hapus cookie
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// ── Hancurkan data sesi di server ─────────────────────────────────────────────
session_destroy();

// ── Mulai sesi baru yang bersih untuk flash message ──────────────────────────
// Kita butuh sesi baru agar pesan "berhasil logout" bisa dibawa ke halaman login.
session_start();
session_regenerate_id(true);

$_SESSION['__flash']['success'] = 'Kamu berhasil keluar. Sampai jumpa!';

// ── Redirect ke login ─────────────────────────────────────────────────────────
header('Location: ' . BASE_URL . '/login.php');
exit();