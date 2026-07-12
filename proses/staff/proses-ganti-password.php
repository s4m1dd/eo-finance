<?php
/**
 * EO Finance — Proses ganti password staff_eo.
 *
 * Dipakai dalam dua skenario:
 *   A. first_login = 1 → staff baru wajib ganti password sementara
 *      sebelum bisa mengakses halaman manapun.
 *   B. Ganti password mandiri → staff yang sudah aktif ingin
 *      memperbarui passwordnya dari halaman profil/pengaturan.
 *
 * Perbedaan keduanya ada di langkah 6: skenario A tidak meminta
 * password lama (tidak ada yang diketahui selain password sementara
 * yang dibuat admin), skenario B wajib verifikasi password lama.
 *
 * Alur:
 *   1. Tolak selain POST
 *   2. Validasi CSRF
 *   3. Guard: harus login sebagai staff_eo (require_login + require_role)
 *   4. Ambil data user dari DB untuk verifikasi
 *   5. Validasi input password baru (panjang, konfirmasi)
 *   6. Jika bukan first_login: verifikasi password lama
 *   7. Pastikan password baru berbeda dari password lama
 *   8. Hash & update password + set first_login = 0
 *   9. Regenerasi session ID
 *  10. Redirect ke dashboard dengan pesan sukses
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// ── Helper redirect ───────────────────────────────────────────────────────────
$backToForm = static function (string $msg): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . BASE_URL . '/staff/ganti-password.php');
    exit();
};

// ════════════════════════════════════════════════════════════════════════════
// 1. HANYA TERIMA POST
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/staff/ganti-password.php');
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// 2. VALIDASI CSRF
// ════════════════════════════════════════════════════════════════════════════
$_csrfPost    = $_POST['csrf_token'] ?? '';
$_csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($_csrfSession) || empty($_csrfPost) || !hash_equals($_csrfSession, $_csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $backToForm('Permintaan tidak valid. Silakan coba lagi.');
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ════════════════════════════════════════════════════════════════════════════
// 3. GUARD: HARUS LOGIN SEBAGAI STAFF_EO
// require_login() redirect ke login jika belum login.
// require_role() redirect ke 403 jika bukan staff_eo.
// ════════════════════════════════════════════════════════════════════════════
require_login();
require_role('staff_eo');

$userId = get_current_user_id();
$eoId   = get_current_eo_id();

// ════════════════════════════════════════════════════════════════════════════
// 4. AMBIL DATA USER DARI DB
// Filter eo_id sebagai lapisan keamanan tenant — meski user_id sudah cukup
// unik, ini memastikan tidak ada manipulasi lintas EO.
// ════════════════════════════════════════════════════════════════════════════
$stmtUser = $mysqli->prepare("
    SELECT id, password, first_login, is_active
    FROM users
    WHERE id     = ?
      AND eo_id  = ?
      AND role   = 'staff_eo'
    LIMIT 1
");
$stmtUser->bind_param('ii', $userId, $eoId);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

// Seharusnya tidak terjadi (sudah lewat require_login), tapi defensive check
if ($user === null || (int) $user['is_active'] !== 1) {
    $_SESSION['__flash']['error'] = 'Akun tidak ditemukan atau tidak aktif.';
    header('Location: ' . BASE_URL . '/logout.php');
    exit();
}

$isFirstLogin = (int) $user['first_login'] === 1;

// ════════════════════════════════════════════════════════════════════════════
// 5. VALIDASI INPUT PASSWORD BARU
// ════════════════════════════════════════════════════════════════════════════
$passwordBaru       = $_POST['password_baru']       ?? '';
$konfirmasiPassword = $_POST['konfirmasi_password'] ?? '';

if ($passwordBaru === '') {
    $backToForm('Password baru wajib diisi.');
}

if (mb_strlen($passwordBaru) < PASSWORD_MIN_LENGTH) {
    $backToForm('Password baru minimal ' . PASSWORD_MIN_LENGTH . ' karakter.');
}

if (mb_strlen($passwordBaru) > 72) {
    $backToForm('Password terlalu panjang (maksimal 72 karakter).');
}

if ($konfirmasiPassword === '') {
    $backToForm('Konfirmasi password baru wajib diisi.');
}

if (!hash_equals($passwordBaru, $konfirmasiPassword)) {
    $backToForm('Password baru dan konfirmasi tidak sama.');
}

// ════════════════════════════════════════════════════════════════════════════
// 6. VERIFIKASI PASSWORD LAMA (hanya untuk skenario B — bukan first_login)
// Skenario A (first_login): admin yang membuat password sementara,
//   staff tidak tahu password "asli" sebelumnya → skip verifikasi lama.
// Skenario B (ganti mandiri): wajib tahu password sekarang sebelum bisa
//   menggantinya → mencegah orang lain ganti password jika sesi dibajak.
// ════════════════════════════════════════════════════════════════════════════
if (!$isFirstLogin) {
    $passwordLama = $_POST['password_lama'] ?? '';

    if ($passwordLama === '') {
        $backToForm('Password saat ini wajib diisi.');
    }

    if (!password_verify($passwordLama, $user['password'])) {
        $backToForm('Password saat ini tidak sesuai.');
    }

    // Bersihkan plaintext password lama dari memori
    unset($passwordLama);
}

// ════════════════════════════════════════════════════════════════════════════
// 7. PASTIKAN PASSWORD BARU BERBEDA DARI PASSWORD LAMA
// Membandingkan plaintext baru dengan hash lama menggunakan password_verify —
// ini tidak bocorkan hash lama dan tidak perlu menyimpan plaintext lama.
// ════════════════════════════════════════════════════════════════════════════
if (password_verify($passwordBaru, $user['password'])) {
    $backToForm('Password baru tidak boleh sama dengan password saat ini.');
}

// ════════════════════════════════════════════════════════════════════════════
// 8. HASH & UPDATE PASSWORD + SET first_login = 0
// Keduanya diupdate dalam satu query agar atomik tanpa perlu transaksi eksplisit
// (single-row UPDATE di InnoDB sudah bersifat atomik).
// ════════════════════════════════════════════════════════════════════════════
$passwordHash = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

// Bersihkan plaintext dari memori
unset($passwordBaru, $konfirmasiPassword);

$stmtUpdate = $mysqli->prepare("
    UPDATE users
    SET password    = ?,
        first_login = 0,
        updated_at  = NOW()
    WHERE id    = ?
      AND eo_id = ?
");
$stmtUpdate->bind_param('sii', $passwordHash, $userId, $eoId);
$stmtUpdate->execute();

if ($mysqli->affected_rows !== 1) {
    error_log(
        '[EO Finance] Ganti password gagal — affected_rows bukan 1. ' .
        'user_id: ' . $userId
    );
    $backToForm('Terjadi kesalahan saat menyimpan password. Silakan coba lagi.');
}

$stmtUpdate->close();

// ════════════════════════════════════════════════════════════════════════════
// 9. REGENERASI SESSION ID
// Password baru = perubahan kredensial = regenerasi ID untuk keamanan.
// Update juga nilai first_login di sesi agar navbar/guard tidak salah baca.
// ════════════════════════════════════════════════════════════════════════════
session_regenerate_id(true);
$_SESSION['first_login'] = 0;

// ════════════════════════════════════════════════════════════════════════════
// 10. REDIRECT KE DASHBOARD
// Pesan berbeda untuk dua skenario agar konteks jelas bagi user.
// ════════════════════════════════════════════════════════════════════════════
if ($isFirstLogin) {
    $_SESSION['__flash']['success'] =
        'Password berhasil dibuat! Selamat datang di ' . APP_NAME . '.';
} else {
    $_SESSION['__flash']['success'] =
        'Password berhasil diperbarui.';
}

header('Location: ' . BASE_URL . '/staff/dashboard.php');
exit();