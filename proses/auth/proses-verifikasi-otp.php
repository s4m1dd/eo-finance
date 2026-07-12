<?php
/**
 * EO Finance — Proses verifikasi kode OTP (langkah 2 dari 3).
 *
 * Menerima POST dari verifikasi-otp.php.
 *
 * Alur:
 *   1. Tolak selain POST
 *   2. Validasi CSRF
 *   3. Guard: reset_email harus ada di sesi
 *   4. Sanitasi & validasi format kode OTP
 *   5. Ambil OTP valid dari DB (tidak expired, belum dipakai)
 *   6. Cek batas percobaan salah (OTP_MAX_ATTEMPTS)
 *   7. Cocokkan kode — timing-safe comparison
 *   8. Tandai OTP sebagai is_used = 1
 *   9. Set reset_verified ke sesi, redirect ke langkah 3
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$backToVerif = static function (string $msg): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . BASE_URL . '/verifikasi-otp.php');
    exit();
};

$backToLupa = static function (string $msg): never {
    $_SESSION['__flash']['error'] = $msg;
    // Bersihkan sesi reset agar user mulai dari awal
    unset($_SESSION['reset_email'], $_SESSION['otp_sent_at'], $_SESSION['reset_verified']);
    header('Location: ' . BASE_URL . '/lupa-password.php');
    exit();
};

// ════════════════════════════════════════════════════════════════════════════
// 1. HANYA TERIMA POST
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/verifikasi-otp.php');
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// 2. VALIDASI CSRF
// ════════════════════════════════════════════════════════════════════════════
$_csrfPost    = $_POST['csrf_token'] ?? '';
$_csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($_csrfSession) || empty($_csrfPost) || !hash_equals($_csrfSession, $_csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $backToVerif('Permintaan tidak valid. Silakan coba lagi.');
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ════════════════════════════════════════════════════════════════════════════
// 3. GUARD: SESI RESET HARUS ADA
// ════════════════════════════════════════════════════════════════════════════
$resetEmail = $_SESSION['reset_email'] ?? '';

if ($resetEmail === '') {
    $backToLupa('Sesi reset password tidak valid. Silakan mulai ulang.');
}

// ════════════════════════════════════════════════════════════════════════════
// 4. SANITASI & VALIDASI FORMAT KODE OTP
// ════════════════════════════════════════════════════════════════════════════
$kodeInput = trim($_POST['kode_otp'] ?? '');

if ($kodeInput === '') {
    $backToVerif('Kode OTP wajib diisi.');
}

// Hanya boleh berisi angka dengan panjang tepat OTP_LENGTH
if (!preg_match('/^\d{' . OTP_LENGTH . '}$/', $kodeInput)) {
    $backToVerif('Kode OTP harus berupa ' . OTP_LENGTH . ' digit angka.');
}

// ════════════════════════════════════════════════════════════════════════════
// 5. AMBIL OTP VALID DARI DB
// Cari OTP yang: (a) milik user dengan email ini, (b) belum dipakai,
// (c) belum kadaluarsa. Jika tidak ada → OTP salah atau kadaluarsa.
// ════════════════════════════════════════════════════════════════════════════
$now = date('Y-m-d H:i:s');

$stmtOtp = $mysqli->prepare("
    SELECT o.id, o.kode_otp, o.user_id
    FROM otp_reset o
    INNER JOIN users u ON o.user_id = u.id
    WHERE u.email      = ?
      AND o.is_used    = 0
      AND o.expired_at > ?
    ORDER BY o.created_at DESC
    LIMIT 1
");
$stmtOtp->bind_param('ss', $resetEmail, $now);
$stmtOtp->execute();
$otpRow = $stmtOtp->get_result()->fetch_assoc();
$stmtOtp->close();

// ════════════════════════════════════════════════════════════════════════════
// 6. BATAS PERCOBAAN SALAH — dicek di sesi
// Setiap kali kode dimasukkan salah (OTP valid ada tapi kode tidak cocok),
// counter naik. Jika mencapai OTP_MAX_ATTEMPTS, OTP diinvalidasi dan
// user harus minta kode baru.
// ════════════════════════════════════════════════════════════════════════════
$_attemptKey  = 'otp_attempts_' . md5($resetEmail);
$_otpAttempts = (int) ($_SESSION[$_attemptKey] ?? 0);

if ($otpRow === null) {
    // OTP tidak ditemukan (kadaluarsa atau tidak pernah dikirim)
    unset($_SESSION[$_attemptKey]);
    $backToLupa('Kode OTP sudah kadaluarsa atau tidak valid. Silakan minta kode baru.');
}

// ════════════════════════════════════════════════════════════════════════════
// 7. COCOKKAN KODE — hash_equals untuk timing-safe comparison
// Meski OTP berupa angka pendek (bukan secret kriptografis yang panjang),
// hash_equals adalah praktik standar untuk perbandingan nilai otentikasi.
// ════════════════════════════════════════════════════════════════════════════
if (!hash_equals($otpRow['kode_otp'], $kodeInput)) {
    // Kode salah — naikkan counter
    $_otpAttempts++;
    $_SESSION[$_attemptKey] = $_otpAttempts;

    $sisaCoba = max(0, OTP_MAX_ATTEMPTS - $_otpAttempts);

    if ($_otpAttempts >= OTP_MAX_ATTEMPTS) {
        // Invalidasi OTP — user harus minta kode baru
        $stmtInv = $mysqli->prepare(
            'UPDATE otp_reset SET is_used = 1 WHERE id = ?'
        );
        $stmtInv->bind_param('i', $otpRow['id']);
        $stmtInv->execute();
        $stmtInv->close();

        unset($_SESSION[$_attemptKey]);
        $backToLupa(
            'Terlalu banyak percobaan salah. Kode OTP telah dinonaktifkan. ' .
            'Silakan minta kode baru.'
        );
    }

    $backToVerif(
        "Kode OTP tidak sesuai. Sisa percobaan: {$sisaCoba}."
    );
}

// ════════════════════════════════════════════════════════════════════════════
// 8. TANDAI OTP SEBAGAI SUDAH DIPAKAI
// is_used = 1 memastikan kode tidak bisa dipakai ulang meski belum expired.
// ════════════════════════════════════════════════════════════════════════════
$stmtUse = $mysqli->prepare(
    'UPDATE otp_reset SET is_used = 1 WHERE id = ?'
);
$stmtUse->bind_param('i', $otpRow['id']);
$stmtUse->execute();
$stmtUse->close();

// ════════════════════════════════════════════════════════════════════════════
// 9. VERIFIKASI BERHASIL — set sesi & redirect ke langkah 3
// ════════════════════════════════════════════════════════════════════════════
$_SESSION['reset_verified'] = true;
$_SESSION['reset_user_id']  = (int) $otpRow['user_id'];

// Bersihkan counter percobaan
unset($_SESSION[$_attemptKey]);

// Putar session ID setelah verifikasi identitas berhasil (privilege change)
session_regenerate_id(true);

$_SESSION['__flash']['success'] =
    'Identitas berhasil diverifikasi. Silakan buat password baru kamu.';

header('Location: ' . BASE_URL . '/reset-password.php');
exit();