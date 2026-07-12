<?php
/**
 * EO Finance — Proses kirim OTP reset password (langkah 1 dari 3).
 *
 * Menerima POST dari lupa-password.php atau tombol "Kirim ulang" di
 * verifikasi-otp.php (ditandai dengan field tersembunyi resend=1).
 *
 * Alur:
 *   1. Tolak selain POST
 *   2. Validasi CSRF
 *   3. Validasi & cari user aktif dengan email tersebut
 *   4. Rate limit pengiriman OTP (maks 3 kirim per 15 menit per email)
 *   5. Nonaktifkan OTP lama yang belum dipakai untuk email ini
 *   6. Generate OTP baru, simpan ke tabel otp_reset
 *   7. Kirim OTP via email (mail() + fallback log untuk XAMPP lokal)
 *   8. Simpan reset_email & otp_sent_at ke sesi
 *   9. Redirect ke verifikasi-otp.php
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// ── Helpers redirect ──────────────────────────────────────────────────────────
$backToLupa = static function (string $msg): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . BASE_URL . '/lupa-password.php');
    exit();
};

$_backToVerif = static function (string $msg, string $type = 'error'): never {
    $_SESSION['__flash'][$type] = $msg;
    header('Location: ' . BASE_URL . '/verifikasi-otp.php');
    exit();
};

// ════════════════════════════════════════════════════════════════════════════
// 1. HANYA TERIMA POST
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/lupa-password.php');
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// 2. VALIDASI CSRF
// ════════════════════════════════════════════════════════════════════════════
$_csrfPost    = $_POST['csrf_token'] ?? '';
$_csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($_csrfSession) || empty($_csrfPost) || !hash_equals($_csrfSession, $_csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $backToLupa('Permintaan tidak valid. Silakan coba lagi.');
}

$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Flag kirim ulang (dari tombol "Kirim ulang" di verifikasi-otp.php) ────────
$isResend = isset($_POST['resend']) && $_POST['resend'] === '1';

// ════════════════════════════════════════════════════════════════════════════
// 3. VALIDASI INPUT & CARI USER AKTIF
// ════════════════════════════════════════════════════════════════════════════
$email = strtolower(trim($_POST['email'] ?? ''));

if ($email === '') {
    $backToLupa('Email wajib diisi.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $backToLupa('Format email tidak valid.');
}

// Cari user aktif dengan email ini
// Jika email sama ada di beberapa EO, ambil yang paling baru dibuat (ORDER BY DESC)
// — dalam praktiknya jarang terjadi, tapi harus ditangani secara deterministik
$stmtUser = $mysqli->prepare("
    SELECT u.id, u.nama, u.eo_id
    FROM users u
    INNER JOIN eo e ON u.eo_id = e.id
    WHERE u.email     = ?
      AND u.is_active = 1
      AND e.is_active = 1
    ORDER BY u.id DESC
    LIMIT 1
");
$stmtUser->bind_param('s', $email);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

// KEAMANAN: respons yang sama apakah email ditemukan atau tidak.
// Ini mencegah user enumeration — penyerang tidak bisa tahu email mana
// yang terdaftar dengan membandingkan respons.
$userFound = ($user !== null);

// ════════════════════════════════════════════════════════════════════════════
// 4. RATE LIMIT PENGIRIMAN OTP
// Maks 3 kali kirim per email per 15 menit — dicek dari tabel otp_reset.
// Menggunakan DB (bukan sesi) agar rate limit berlaku lintas browser/device.
// ════════════════════════════════════════════════════════════════════════════
if ($userFound) {
    $limitWindow  = 15 * 60;  // 15 menit dalam detik
    $maxOtpSend   = 3;
    $windowStart  = date('Y-m-d H:i:s', time() - $limitWindow);

    $stmtLimit = $mysqli->prepare("
        SELECT COUNT(*) AS jumlah
        FROM otp_reset
        WHERE user_id   = ?
          AND created_at >= ?
    ");
    $stmtLimit->bind_param('is', $user['id'], $windowStart);
    $stmtLimit->execute();
    $jumlahKirim = (int) $stmtLimit->get_result()->fetch_assoc()['jumlah'];
    $stmtLimit->close();

    if ($jumlahKirim >= $maxOtpSend) {
        // Hitung sisa waktu tunggu
        $stmtFirst = $mysqli->prepare("
            SELECT created_at FROM otp_reset
            WHERE user_id = ? AND created_at >= ?
            ORDER BY created_at ASC LIMIT 1
        ");
        $stmtFirst->bind_param('is', $user['id'], $windowStart);
        $stmtFirst->execute();
        $firstSent   = $stmtFirst->get_result()->fetch_assoc()['created_at'];
        $stmtFirst->close();

        $sisaDetik = $limitWindow - (time() - strtotime($firstSent));
        $sisaMenit = (int) ceil(max(0, $sisaDetik) / 60);

        $target = $isResend ? $_backToVerif : $backToLupa;
        $target(
            "Terlalu banyak permintaan OTP. Coba lagi dalam {$sisaMenit} menit.",
            'error'
        );
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 5. NONAKTIFKAN OTP LAMA (belum dipakai)
// Mencegah akumulasi OTP valid untuk satu user — hanya satu OTP aktif
// pada satu waktu. OTP yang dikirim ulang menginvalidasi yang sebelumnya.
// ════════════════════════════════════════════════════════════════════════════
if ($userFound) {
    $stmtInvalidate = $mysqli->prepare("
        UPDATE otp_reset
        SET is_used = 1
        WHERE user_id = ? AND is_used = 0
    ");
    $stmtInvalidate->bind_param('i', $user['id']);
    $stmtInvalidate->execute();
    $stmtInvalidate->close();
}

// ════════════════════════════════════════════════════════════════════════════
// 6. GENERATE & SIMPAN OTP BARU
// Hanya dijalankan jika user ditemukan — tapi langkah berikutnya
// (redirect ke verifikasi) tetap dilakukan meski user tidak ada,
// untuk menjaga respons konsisten (anti enumeration).
// ════════════════════════════════════════════════════════════════════════════
if ($userFound) {
    $kodeOtp   = generate_otp(OTP_LENGTH);
    $expiredAt = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));

    $stmtOtp = $mysqli->prepare("
        INSERT INTO otp_reset (user_id, kode_otp, expired_at, is_used, created_at)
        VALUES (?, ?, ?, 0, NOW())
    ");
    $stmtOtp->bind_param('iss', $user['id'], $kodeOtp, $expiredAt);
    $stmtOtp->execute();
    $stmtOtp->close();

    // ── Kirim OTP via email ───────────────────────────────────────────────────
    $namaUser  = $user['nama'];
    $subjek    = APP_NAME . ' — Kode Verifikasi Reset Password';
    $isiEmail  = "Halo {$namaUser},\n\n"
               . "Kamu meminta reset password untuk akun " . APP_NAME . ".\n\n"
               . "Kode verifikasi kamu:\n\n"
               . "  {$kodeOtp}\n\n"
               . "Kode berlaku selama " . OTP_EXPIRY_MINUTES . " menit.\n"
               . "Jangan bagikan kode ini kepada siapapun.\n\n"
               . "Jika kamu tidak meminta reset password, abaikan email ini.\n\n"
               . "— Tim " . APP_NAME;

    $header = "From: no-reply@eofinance.local\r\n"
            . "Reply-To: no-reply@eofinance.local\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "X-Mailer: PHP/" . phpversion();

    $mailSent = mail($email, $subjek, $isiEmail, $header);

    // ── Fallback untuk XAMPP lokal (mail() tidak berfungsi tanpa mail server) ─
    // Di development, tulis OTP ke error log agar bisa dicek tanpa email server.
    // HAPUS blok ini saat deploy ke production dengan mail server aktif.
    if (!$mailSent || APP_DEBUG) {
        error_log(
            "[EO Finance DEV] OTP untuk {$email}: {$kodeOtp} " .
            "(berlaku hingga {$expiredAt})"
        );
    }
}

// ════════════════════════════════════════════════════════════════════════════
// 8. SET SESI & REDIRECT
// reset_email dan otp_sent_at disimpan ke sesi untuk dipakai di langkah 2-3.
// Dilakukan selalu — baik user ditemukan maupun tidak (respons konsisten).
// ════════════════════════════════════════════════════════════════════════════
$_SESSION['reset_email']  = $email;
$_SESSION['otp_sent_at']  = time();
// Bersihkan flag verifikasi jika ada sisa dari flow sebelumnya
unset($_SESSION['reset_verified']);

$_SESSION['__flash']['success'] =
    'Jika email terdaftar, kode OTP akan segera dikirimkan. ' .
    'Periksa inbox atau folder spam kamu.';

header('Location: ' . BASE_URL . '/verifikasi-otp.php');
exit();