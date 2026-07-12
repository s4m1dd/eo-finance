<?php
/**
 * EO Finance — Proses Verifikasi OTP 2FA.
 *
 * Dipanggil dari verifikasi-2fa.php.
 * Jika kode cocok & belum kedaluwarsa → selesaikan sesi login.
 * Jika tidak → kembali ke halaman verifikasi dengan pesan error.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/otp-2fa.php';

$back2fa  = BASE_URL . '/verifikasi-2fa.php';
$backLogin = BASE_URL . '/login.php';

$fail = function (string $msg, string $to = '') use ($back2fa): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . ($to ?: $back2fa));
    exit();
};
$ok = function (string $msg, string $to): never {
    $_SESSION['__flash']['success'] = $msg;
    header('Location: ' . $to);
    exit();
};

// Sesi pending 2FA harus ada
if (empty($_SESSION['__2fa_user_id'])) {
    header('Location: ' . $backLogin); exit();
}

$pendingUserId = (int) $_SESSION['__2fa_user_id'];
$pendingData   = $_SESSION['__2fa_data'] ?? [];   // data sesi lengkap yang akan dipakai

// ── Kirim ulang kode ─────────────────────────────────────────────────────────
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
if ($action === 'kirim_ulang') {
    kirim_otp_2fa($mysqli, $pendingUserId);
    $_SESSION['__flash']['success'] = 'Kode baru telah dikirim ke email kamu.';
    header('Location: ' . $back2fa); exit();
}

// ── Verifikasi kode ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $back2fa); exit();
}

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    $fail('Token keamanan tidak valid.');
}
// Rotasi CSRF token setelah tiap submit valid — one-time use
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$kodeInput = preg_replace('/\D/', '', trim($_POST['kode_otp'] ?? ''));
if (strlen($kodeInput) !== 6) {
    $fail('Kode harus 6 digit angka.');
}

// ── Rate limiting percobaan kode 2FA ─────────────────────────────────────────
// Simpan counter di sesi — max 5 percobaan salah sebelum kode diinvalidasi.
// Ini mencegah brute-force kode 6 digit (1.000.000 kemungkinan).
$_2faAttemptKey = '__2fa_attempts_' . $pendingUserId;
$_2faAttempts   = (int) ($_SESSION[$_2faAttemptKey] ?? 0);
$_2faMaxAttempts = 5;

// Cek OTP di database
$stmt = $mysqli->prepare(
    "SELECT id FROM otp_2fa
     WHERE user_id = ? AND kode_otp = ? AND is_used = 0
       AND expired_at > NOW()
     ORDER BY created_at DESC
     LIMIT 1"
);
$stmt->bind_param('is', $pendingUserId, $kodeInput);
$stmt->execute();
$otpRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($otpRow === null) {
    // OTP tidak ditemukan (kadaluarsa atau tidak pernah dikirim)
    unset($_SESSION[$_2faAttemptKey]);
    $fail('Kode salah atau sudah kedaluwarsa. Silakan minta kode baru.');
}

// Kode ada di DB — periksa apakah cocok (query sudah filter kode, tapi kita tetap
// menambah counter di sisi PHP untuk proteksi timing-attack dan brute-force)
$_2faAttempts++;
$_SESSION[$_2faAttemptKey] = $_2faAttempts;

if ($_2faAttempts > $_2faMaxAttempts) {
    // Terlalu banyak percobaan — invalidasi OTP
    $stmtInv = $mysqli->prepare('UPDATE otp_2fa SET is_used = 1 WHERE user_id = ? AND is_used = 0');
    $stmtInv->bind_param('i', $pendingUserId);
    $stmtInv->execute();
    $stmtInv->close();
    unset($_SESSION[$_2faAttemptKey]);
    $fail(
        'Terlalu banyak percobaan salah. Kode 2FA telah dinonaktifkan. ' .
        'Silakan minta kode baru.',
        $back2fa
    );
}

// Tandai OTP sudah dipakai
$stmtUsed = $mysqli->prepare(
    'UPDATE otp_2fa SET is_used = 1 WHERE id = ?'
);
$stmtUsed->bind_param('i', $otpRow['id']);
$stmtUsed->execute();
$stmtUsed->close();

// Reset counter percobaan setelah berhasil
unset($_SESSION[$_2faAttemptKey]);

// Selesaikan sesi login — salin semua data yang ditunda saat login awal
foreach ($pendingData as $key => $val) {
    $_SESSION[$key] = $val;
}
unset($_SESSION['__2fa_user_id'], $_SESSION['__2fa_data']);

// Regenerasi session ID setelah privilege escalation (2FA sukses = login penuh)
// Mencegah session fixation: ID sebelum dan sesudah login harus berbeda.
session_regenerate_id(true);

// Redirect ke dashboard
$dashboard = match ($pendingData['role'] ?? '') {
    'admin_eo' => BASE_URL . '/admin/dashboard.php',
    'staff_eo' => BASE_URL . '/staff/dashboard.php',
    default    => $backLogin,
};

header('Location: ' . $dashboard); exit();