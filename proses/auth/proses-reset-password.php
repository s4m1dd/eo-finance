<?php
/**
 * EO Finance — Proses simpan password baru (langkah 3 dari 3).
 *
 * Menerima POST dari reset-password.php.
 * Hanya bisa dieksekusi jika reset_verified = true di sesi (langkah 2 selesai).
 *
 * Alur:
 *   1. Tolak selain POST
 *   2. Validasi CSRF
 *   3. Guard: reset_verified & reset_user_id harus ada di sesi
 *   4. Validasi password baru (panjang, konfirmasi cocok)
 *   5. Pastikan user masih aktif sebelum update
 *   6. Hash & update password di DB
 *   7. Invalidasi semua OTP sisa milik user ini
 *   8. Bersihkan seluruh variabel sesi reset
 *   9. Redirect ke login dengan pesan sukses
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

$backToReset = static function (string $msg): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . BASE_URL . '/reset-password.php');
    exit();
};

$backToLupa = static function (string $msg): never {
    $_SESSION['__flash']['error'] = $msg;
    unset(
        $_SESSION['reset_email'],
        $_SESSION['reset_verified'],
        $_SESSION['reset_user_id'],
        $_SESSION['otp_sent_at']
    );
    header('Location: ' . BASE_URL . '/lupa-password.php');
    exit();
};

// ════════════════════════════════════════════════════════════════════════════
// 1. HANYA TERIMA POST
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/reset-password.php');
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// 2. VALIDASI CSRF
// ════════════════════════════════════════════════════════════════════════════
$_csrfPost    = $_POST['csrf_token'] ?? '';
$_csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($_csrfSession) || empty($_csrfPost) || !hash_equals($_csrfSession, $_csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $backToReset('Permintaan tidak valid. Silakan coba lagi.');
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ════════════════════════════════════════════════════════════════════════════
// 3. GUARD: SESI VERIFIKASI HARUS ADA & VALID
// reset_verified membuktikan user sudah melewati langkah 2 (OTP valid).
// reset_user_id adalah ID user yang akan diupdate — diset di langkah 2.
// Tanpa keduanya, request ini tidak sah (bisa dari direct URL manipulation).
// ════════════════════════════════════════════════════════════════════════════
if (
    empty($_SESSION['reset_verified']) ||
    empty($_SESSION['reset_user_id'])  ||
    empty($_SESSION['reset_email'])
) {
    $backToLupa('Sesi reset password tidak valid atau sudah kadaluarsa. Silakan mulai ulang.');
}

$resetUserId = (int) $_SESSION['reset_user_id'];
$resetEmail  = $_SESSION['reset_email'];

// ════════════════════════════════════════════════════════════════════════════
// 4. VALIDASI PASSWORD BARU
// ════════════════════════════════════════════════════════════════════════════
$passwordBaru       = $_POST['password_baru']       ?? '';
$konfirmasiPassword = $_POST['konfirmasi_password'] ?? '';

if ($passwordBaru === '') {
    $backToReset('Password baru wajib diisi.');
}

if (mb_strlen($passwordBaru) < PASSWORD_MIN_LENGTH) {
    $backToReset('Password minimal ' . PASSWORD_MIN_LENGTH . ' karakter.');
}

if (mb_strlen($passwordBaru) > 72) {
    $backToReset('Password terlalu panjang (maksimal 72 karakter).');
}

if ($konfirmasiPassword === '') {
    $backToReset('Konfirmasi password wajib diisi.');
}

if (!hash_equals($passwordBaru, $konfirmasiPassword)) {
    $backToReset('Password baru dan konfirmasi tidak sama.');
}

// ════════════════════════════════════════════════════════════════════════════
// 5. PASTIKAN USER MASIH AKTIF
// Ada jeda waktu antara langkah 1-3; admin mungkin menonaktifkan
// akun di antara itu. Cek ulang is_active sebelum update.
// ════════════════════════════════════════════════════════════════════════════
$stmtCek = $mysqli->prepare(
    'SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1'
);
$stmtCek->bind_param('i', $resetUserId);
$stmtCek->execute();
$stmtCek->store_result();
$userAktif = $stmtCek->num_rows > 0;
$stmtCek->close();

if (!$userAktif) {
    $backToLupa('Akun tidak aktif. Hubungi Admin EO untuk bantuan.');
}

// ════════════════════════════════════════════════════════════════════════════
// 6. HASH & UPDATE PASSWORD
// Hash sebelum transaksi agar bcrypt tidak menahan lock DB.
// ════════════════════════════════════════════════════════════════════════════
$passwordHash = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

// Hapus plaintext dari memori sesegera mungkin
unset($passwordBaru, $konfirmasiPassword);

$mysqli->begin_transaction();

try {
    // Update password user
    $stmtUpdate = $mysqli->prepare(
        'UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmtUpdate->bind_param('si', $passwordHash, $resetUserId);
    $stmtUpdate->execute();

    if ($mysqli->affected_rows !== 1) {
        throw new RuntimeException('Update password tidak berhasil — baris tidak ditemukan.');
    }

    $stmtUpdate->close();

    // ── 7. INVALIDASI SEMUA OTP SISA ────────────────────────────────────────
    // Setelah password berhasil diganti, semua OTP yang mungkin masih valid
    // untuk user ini dinonaktifkan — mencegah reuse oleh pihak lain.
    $stmtInv = $mysqli->prepare(
        'UPDATE otp_reset SET is_used = 1 WHERE user_id = ? AND is_used = 0'
    );
    $stmtInv->bind_param('i', $resetUserId);
    $stmtInv->execute();
    $stmtInv->close();

    $mysqli->commit();

} catch (Throwable $e) {
    $mysqli->rollback();

    error_log(
        '[EO Finance] Reset password gagal — ' .
        'user_id: ' . $resetUserId .
        ', error: ' . $e->getMessage()
    );

    $backToReset('Terjadi kesalahan saat menyimpan password. Silakan coba lagi.');
}

// ════════════════════════════════════════════════════════════════════════════
// 8. BERSIHKAN SEMUA VARIABEL SESI RESET
// Pastikan tidak ada sisa data reset yang bisa dipakai ulang.
// ════════════════════════════════════════════════════════════════════════════
unset(
    $_SESSION['reset_email'],
    $_SESSION['reset_verified'],
    $_SESSION['reset_user_id'],
    $_SESSION['otp_sent_at']
);

// Putar session ID — user sudah menyelesaikan flow sensitif
session_regenerate_id(true);

// ════════════════════════════════════════════════════════════════════════════
// 9. REDIRECT KE LOGIN
// ════════════════════════════════════════════════════════════════════════════
$_SESSION['__flash']['success'] =
    'Password berhasil diperbarui! Silakan masuk dengan password baru kamu.';

header('Location: ' . BASE_URL . '/login.php');
exit();