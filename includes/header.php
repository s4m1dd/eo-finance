<?php
/**
 * EO Finance — Header HTML global.
 *
 * Berisi DOCTYPE, <html>, dan seluruh konten <head> termasuk meta tag
 * dan link CSS yang dipilih otomatis berdasarkan $page_type.
 *
 * CARA PAKAI — di setiap halaman sebelum include file ini:
 *
 *   define('EO_FINANCE_APP', true);
 *
 *   // Opsional — default: APP_NAME
 *   $page_type   = 'admin';          // 'admin' | 'staff' | 'public'
 *   $pageTitle   = 'Dashboard';      // judul tab browser
 *   $pageDesc    = 'Deskripsi...';   // meta description
 *
 *   require_once __DIR__ . '/../includes/header.php';
 *
 * CATATAN: file ini sudah men-include ketiga config.
 * Jangan include ulang session/config/database di halaman
 * yang sudah memakai header ini.
 *
 * @package EOFinance
 */

// ── Guard: blokir akses langsung via browser ─────────────────────────────────
if (!defined('EO_FINANCE_APP')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

// ── Tentukan path root project dari lokasi file ini ──────────────────────────
// __DIR__ selalu merujuk ke /includes, berapapun kedalaman halaman pemanggil.
$_eoRoot = dirname(__DIR__);

// ── Include config berurutan ─────────────────────────────────────────────────
// require_once aman dari double-include — PHP tidak akan load ulang file yang sama.
// session.php wajib pertama — session_start() harus sebelum output HTML apapun.
require_once $_eoRoot . '/config/session.php';
require_once $_eoRoot . '/config/config.php';
require_once $_eoRoot . '/config/database.php';

// ── Normalisasi $page_type ────────────────────────────────────────────────────
// Baca dari scope pemanggil; fallback 'public' jika tidak diset.
$_pageType = strtolower(trim($page_type ?? 'public'));
if (!in_array($_pageType, ['admin', 'staff', 'public'], true)) {
    $_pageType = 'public';
}

// ── Pilih CSS berdasarkan $page_type ─────────────────────────────────────────
// style.css dimuat pertama (global foundation), lalu file spesifik role.
// Urutan ini penting agar aturan di admin/staff.css bisa override style.css.
$_cssFiles = [BASE_URL . '/assets/css/style.css'];

if ($_pageType === 'admin') {
    $_cssFiles[] = BASE_URL . '/assets/css/admin.css';
} elseif ($_pageType === 'staff') {
    $_cssFiles[] = BASE_URL . '/assets/css/staff.css';
}

// ── Bangun judul halaman ──────────────────────────────────────────────────────
// Format: "Nama Halaman — EO Finance" | fallback: "EO Finance"
$_htmlTitle = !empty($pageTitle)
    ? htmlspecialchars(trim($pageTitle), ENT_QUOTES, 'UTF-8') . ' — ' . APP_NAME
    : APP_NAME;

// ── Bangun meta description ───────────────────────────────────────────────────
$_htmlDesc = !empty($pageDesc)
    ? htmlspecialchars(trim($pageDesc), ENT_QUOTES, 'UTF-8')
    : 'Platform manajemen keuangan event organizer yang modern, aman, dan terpusat.';

// ── Role aktif dari sesi untuk class <body> ───────────────────────────────────
// Class role-{role} memungkinkan CSS melakukan styling per-role tanpa JS.
$_bodyRole = htmlspecialchars($_SESSION['role'] ?? 'guest', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= $_htmlDesc ?>">

  <!--
    Cegah browser menyimpan halaman dalam cache setelah logout.
    Tanpa ini, tombol Back browser bisa menampilkan data keuangan lama.
  -->
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">

  <!-- Jangan bocorkan URL ke situs eksternal melalui header Referer -->
  <meta name="referrer" content="strict-origin-when-cross-origin">

  <title><?= $_htmlTitle ?></title>

  <!--
    CSS dimuat berdasarkan $page_type.
    Urutan: style.css (global) → admin.css atau staff.css (spesifik role).
  -->
  <?php foreach ($_cssFiles as $_css) : ?>
  <link rel="stylesheet"
        href="<?= htmlspecialchars($_css, ENT_QUOTES, 'UTF-8') ?>">
  <?php endforeach; ?>

  <link rel="icon"
        type="image/svg+xml"
        href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body class="role-<?= $_bodyRole ?>">
<!--
  Class role-{role} di <body>:
    .role-admin_eo → dipakai di admin.css untuk styling spesifik admin
    .role-staff_eo → dipakai di staff.css untuk styling spesifik staff
    .role-guest    → halaman publik (login, register, error)
-->