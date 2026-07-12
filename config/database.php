<?php
/**
 * EO Finance — Koneksi database MySQL via MySQLi.
 *
 * Menyediakan variabel $mysqli yang siap digunakan di seluruh aplikasi.
 * File ini menghasilkan koneksi bersifat "singleton-like" — jika di-include
 * berkali-kali, koneksi tidak dibuat ulang (sudah ada di memori PHP).
 *
 * Dependensi:
 *   - config/session.php  (sudah di-include sebelumnya)
 *   - config/config.php   (konstanta APP_DEBUG harus sudah terdefinisi)
 *
 * Penggunaan di file PHP manapun:
 *   require_once __DIR__ . '/../config/database.php';
 *   // $mysqli langsung tersedia
 *   $stmt = $mysqli->prepare("SELECT ...");
 *
 * @package EOFinance
 */

// ── Keamanan: blokir akses langsung ke file ini via browser ──────────────────
if (!defined('EO_FINANCE_APP')) {
    http_response_code(403);
    exit('Akses langsung ke file konfigurasi tidak diizinkan.');
}

// ── Cegah koneksi ganda jika file di-include lebih dari sekali ───────────────
// Jika $mysqli sudah ada dan masih terhubung, tidak perlu buat koneksi baru.
if (isset($mysqli) && $mysqli instanceof mysqli && $mysqli->ping()) {
    return;
}

// ════════════════════════════════════════════════════════════════════════════
// PARAMETER KONEKSI
// Nilai diambil dari environment variable (.env), BUKAN hardcoded di sini.
// Copy .env.example -> .env lalu isi nilai sesuai konfigurasi MySQL/XAMPP kamu.
// ════════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/env.php';

define('DB_HOST',    env('DB_HOST', 'localhost'));
define('DB_USER',    env('DB_USER', 'root'));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_NAME',    env('DB_NAME', 'eo_finance'));
define('DB_PORT',    (int) env('DB_PORT', 3306));
define('DB_CHARSET', 'utf8mb4');   // Harus sama dengan charset di CREATE DATABASE

// ════════════════════════════════════════════════════════════════════════════
// BUAT KONEKSI
// ════════════════════════════════════════════════════════════════════════════

// Aktifkan laporan error MySQLi untuk menangkap masalah saat development.
// mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT) membuat MySQLi
// melempar exception (mysqli_sql_exception) alih-alih hanya set errno —
// sehingga query gagal tidak pernah "diam-diam" lolos tanpa penanganan.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

    // ── Set charset ke utf8mb4 ────────────────────────────────────────────────
    // Penting: charset koneksi HARUS cocok dengan charset database dan tabel.
    // utf8mb4 mendukung emoji dan karakter Unicode penuh (utf8 biasa tidak).
    // set_charset() lebih aman daripada SET NAMES via query karena tidak
    // bisa di-inject dan memastikan library mysqli juga mengetahui charset-nya.
    if (!$mysqli->set_charset(DB_CHARSET)) {
        throw new RuntimeException(
            'Gagal mengatur charset koneksi database: ' . $mysqli->error
        );
    }

    // ── Optimasi koneksi ──────────────────────────────────────────────────────
    // Paksa MySQL menggunakan timezone yang sama dengan PHP (APP_TIMEZONE).
    // Tanpa ini, fungsi MySQL seperti NOW() bisa menghasilkan waktu berbeda
    // dengan date() PHP jika timezone server MySQL berbeda dari APP_TIMEZONE.
    $mysqli->query("SET time_zone = '+07:00'"); // WIB (Asia/Jakarta)

    // ── Paksa mode SQL ketat ──────────────────────────────────────────────────
    // STRICT_TRANS_TABLES: MySQL menolak data tidak valid (misal string di kolom INT)
    //   alih-alih menyimpan nilai default diam-diam — lebih aman dan mudah di-debug.
    // NO_ZERO_IN_DATE, NO_ZERO_DATE: tanggal seperti '0000-00-00' dianggap error.
    // ERROR_FOR_DIVISION_BY_ZERO: bagi nol menghasilkan error, bukan NULL.
    $mysqli->query("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO'");

} catch (mysqli_sql_exception $e) {
    // ── Penanganan error koneksi ──────────────────────────────────────────────
    // Catat error lengkap (termasuk detail teknis) ke log server untuk debugging.
    // Jangan pernah tampilkan detail koneksi (host, user, dsb.) ke browser —
    // informasi ini berguna untuk penyerang.
    error_log(
        '[EO Finance] Koneksi database gagal — ' .
        'Host: ' . DB_HOST . ', DB: ' . DB_NAME . ' | ' .
        'Error: ' . $e->getMessage()
    );

    // Tampilkan pesan ramah ke user. Detail teknis disembunyikan di production.
    if (defined('APP_DEBUG') && APP_DEBUG === true) {
        // Mode development: tampilkan pesan error teknis untuk memudahkan debugging
        $errorDetail = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        $errorMessage = "
            <p><strong>Detail error (hanya tampil di mode debug):</strong></p>
            <pre style='background:#f3f4f6;padding:12px;border-radius:6px;font-size:13px;'>{$errorDetail}</pre>
            <p>Pastikan XAMPP MySQL sudah berjalan dan database <code>eo_finance</code> sudah dibuat.</p>
        ";
    } else {
        // Mode production: pesan generik tanpa informasi sistem
        $errorMessage = '<p>Silakan coba beberapa saat lagi atau hubungi administrator.</p>';
    }

    // Hentikan eksekusi dan tampilkan halaman error yang informatif
    http_response_code(503);
    echo '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koneksi Bermasalah — ' . APP_NAME . '</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 24px;
            color: #111827;
        }
        .error-box {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 48px 40px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,.06);
        }
        .error-icon {
            width: 56px;
            height: 56px;
            background: #fef2f2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 24px;
        }
        h1 { font-size: 20px; font-weight: 600; margin-bottom: 12px; }
        p  { font-size: 15px; color: #6b7280; line-height: 1.6; margin-bottom: 8px; }
        .detail { margin-top: 20px; text-align: left; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="error-box">
        <div class="error-icon">🔌</div>
        <h1>Tidak Dapat Terhubung ke Database</h1>
        <p>Sistem mengalami masalah koneksi ke database.</p>
        <div class="detail">' . $errorMessage . '</div>
    </div>
</body>
</html>';
    exit;

} catch (RuntimeException $e) {
    // Error charset — tidak mungkin terjadi di setup normal, tapi ditangani eksplisit
    error_log('[EO Finance] Error konfigurasi database: ' . $e->getMessage());
    http_response_code(500);
    exit('Terjadi kesalahan konfigurasi sistem. Hubungi administrator.');
}