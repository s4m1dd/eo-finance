<?php
/**
 * EO Finance — Proses login.
 *
 * Menerima POST dari login.php, memvalidasi kredensial,
 * membangun sesi, dan mengarahkan ke dashboard yang sesuai.
 *
 * Alur:
 *   1. Tolak selain POST
 *   2. Validasi CSRF token
 *   3. Cek rate limit (maks 5 percobaan per 15 menit per IP)
 *   4. Sanitasi & validasi input dasar
 *   5. Query user by email — ambil semua kandidat (email bisa ada
 *      di beberapa EO berbeda karena unique per eo_id, bukan global)
 *   6. Cocokkan password_verify() terhadap setiap kandidat
 *   7. Cek is_active
 *   8. Bangun sesi, reset counter, redirect
 *   9. Tangani first_login (staff wajib ganti password dulu)
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// ════════════════════════════════════════════════════════════════════════════
// 1. HANYA TERIMA POST
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

// ── Helper redirect dengan flash message ──────────────────────────────────────
// Didefinisikan setelah cek POST. Tidak pakai static agar bisa akses superglobal.
$backToLogin = function (string $msg): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . BASE_URL . '/login.php');
    exit();
};

// ════════════════════════════════════════════════════════════════════════════
// 2. VALIDASI CSRF TOKEN
// Token dibuat di login.php saat form dirender; harus cocok dengan sesi.
// ════════════════════════════════════════════════════════════════════════════
$_csrfPost    = $_POST['csrf_token'] ?? '';
$_csrfSession = $_SESSION['csrf_token'] ?? '';

// hash_equals() mencegah timing attack pada perbandingan string rahasia
if (
    empty($_csrfSession) ||
    empty($_csrfPost) ||
    !hash_equals($_csrfSession, $_csrfPost)
) {
    // Regenerasi token setelah invalid agar penyerang tidak bisa reuse
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $backToLogin('Permintaan tidak valid. Silakan coba lagi.');
}

// Putar token CSRF setelah tiap submit yang valid — one-time use
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ════════════════════════════════════════════════════════════════════════════
// 3. RATE LIMITING — maks 5 percobaan gagal per IP per 15 menit
// Disimpan di sesi (sederhana, cukup untuk XAMPP lokal).
// Di production, gunakan Redis atau tabel DB untuk rate limit lintas sesi.
// ════════════════════════════════════════════════════════════════════════════
$_ip          = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$_rateLimitKey = 'login_attempts_' . md5($_ip);
$_windowSec    = 15 * 60;   // 15 menit
$_maxAttempts  = 5;

$_attempts = $_SESSION[$_rateLimitKey] ?? ['count' => 0, 'first_at' => time()];

// Reset window jika sudah lewat 15 menit sejak percobaan pertama
if ((time() - $_attempts['first_at']) > $_windowSec) {
    $_attempts = ['count' => 0, 'first_at' => time()];
}

if ($_attempts['count'] >= $_maxAttempts) {
    $sisaMenit = (int) ceil(($_windowSec - (time() - $_attempts['first_at'])) / 60);
    $backToLogin(
        "Terlalu banyak percobaan login. Coba lagi dalam {$sisaMenit} menit."
    );
}

// ════════════════════════════════════════════════════════════════════════════
// 4. SANITASI & VALIDASI INPUT
// ════════════════════════════════════════════════════════════════════════════
$emailInput    = trim($_POST['email']    ?? '');
$passwordInput = $_POST['password']      ?? '';   // password: trim JANGAN — spasi mungkin sengaja

if ($emailInput === '' || $passwordInput === '') {
    $backToLogin('Email dan password wajib diisi.');
}

if (!filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
    $backToLogin('Format email tidak valid.');
}

if (strlen($passwordInput) < PASSWORD_MIN_LENGTH) {
    $backToLogin('Password minimal ' . PASSWORD_MIN_LENGTH . ' karakter.');
}

// ════════════════════════════════════════════════════════════════════════════
// 5. QUERY USER BY EMAIL
// Email unik per EO (bukan global), sehingga satu email bisa milik beberapa
// akun di EO yang berbeda. Ambil semua kandidat lalu cocokkan password.
// JOIN ke tabel eo untuk mendapatkan nama_eo yang akan disimpan ke sesi.
// ════════════════════════════════════════════════════════════════════════════
$stmt = $mysqli->prepare("
    SELECT
        u.id,
        u.eo_id,
        u.nama,
        u.email,
        u.password,
        u.role,
        u.first_login,
        u.is_active,
        u.two_fa_enabled,
        e.nama_eo
    FROM users u
    INNER JOIN eo e ON u.eo_id = e.id
    WHERE u.email = ?
      AND e.is_active = 1
    ORDER BY u.id ASC
");
$stmt->bind_param('s', $emailInput);
$stmt->execute();
$result = $stmt->get_result();

// ════════════════════════════════════════════════════════════════════════════
// 6. COCOKKAN PASSWORD TERHADAP SETIAP KANDIDAT
// Iterasi semua row dengan email yang sama; cocokkan hash bcrypt.
// Ini menangani kasus satu email dipakai di dua EO berbeda.
// ════════════════════════════════════════════════════════════════════════════
$matchedUser = null;

while ($row = $result->fetch_assoc()) {
    if (password_verify($passwordInput, $row['password'])) {
        $matchedUser = $row;
        break;   // ambil match pertama — cukup
    }
}

$stmt->close();

// Tidak ada user yang cocok — tambah counter rate limit
if ($matchedUser === null) {
    $_attempts['count']++;
    $_SESSION[$_rateLimitKey] = $_attempts;

    // Pesan sengaja generik: tidak memberi tahu apakah email atau password yang salah
    // (mencegah user enumeration attack)
    $backToLogin('Email atau password tidak sesuai.');
}

// ════════════════════════════════════════════════════════════════════════════
// 7. CEK STATUS AKUN
// ════════════════════════════════════════════════════════════════════════════
if ((int) $matchedUser['is_active'] === 0) {
    // Catat ke log server untuk monitoring
    error_log(
        '[EO Finance] Login ditolak — akun nonaktif. ' .
        'user_id: ' . $matchedUser['id'] .
        ', email: ' . $emailInput
    );
    $backToLogin('Akun kamu tidak aktif. Hubungi Admin EO untuk mengaktifkan kembali.');
}

// ════════════════════════════════════════════════════════════════════════════
// 8. BANGUN SESI — login berhasil
// ════════════════════════════════════════════════════════════════════════════

// Regenerasi session ID: mencegah session fixation setelah privilege escalation
session_regenerate_id(true);

// Isi variabel sesi yang dipakai di seluruh aplikasi
$_SESSION['user_id']  = (int) $matchedUser['id'];
$_SESSION['eo_id']    = (int) $matchedUser['eo_id'];
$_SESSION['nama']     = $matchedUser['nama'];
$_SESSION['nama_eo']  = $matchedUser['nama_eo'];
$_SESSION['role']     = $matchedUser['role'];
$_SESSION['email']    = $matchedUser['email'];
$_SESSION['first_login'] = (int) $matchedUser['first_login'];

// Tandai User-Agent sesi (untuk validasi di session.php pada request berikutnya)
$_SESSION['__user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';

// Reset counter rate limit setelah login berhasil
unset($_SESSION[$_rateLimitKey]);

// Periksa apakah password perlu di-rehash (jika cost factor di config berubah)
// password_needs_rehash() aman dipanggil setiap login — ringan
if (password_needs_rehash($matchedUser['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST])) {
    $newHash = password_hash($passwordInput, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    $stmtRehash = $mysqli->prepare(
        'UPDATE users SET password = ? WHERE id = ?'
    );
    $stmtRehash->bind_param('si', $newHash, $matchedUser['id']);
    $stmtRehash->execute();
    $stmtRehash->close();
}

// ════════════════════════════════════════════════════════════════════════════
// 9. TENTUKAN TUJUAN REDIRECT
// ════════════════════════════════════════════════════════════════════════════

// Staff dengan first_login = 1 WAJIB ganti password sebelum bisa akses lain
if ($matchedUser['role'] === 'staff_eo' && (int) $matchedUser['first_login'] === 1) {
    $_SESSION['__flash']['warning'] =
        'Ini adalah login pertamamu. Silakan buat password baru sebelum melanjutkan.';
    header('Location: ' . BASE_URL . '/staff/ganti-password.php');
    exit();
}

// Intended URL: jika user sebelumnya mencoba akses halaman tertentu
// lalu diarahkan ke login, kembalikan ke halaman itu setelah login berhasil.
$intendedUrl = $_SESSION['__intended_url'] ?? '';
unset($_SESSION['__intended_url']);

// Validasi intended URL: hanya terima URL internal (tidak boleh redirect ke domain lain)
// Strip base path dari REQUEST_URI jika ada, karena REQUEST_URI = '/eo-finance/admin/...'
// sedangkan BASE_URL sudah mengandung '/eo-finance' — mencegah double path:
// Contoh salah: http://localhost/eo-finance + /eo-finance/admin/ = double /eo-finance/
$basePath = rtrim(parse_url(BASE_URL, PHP_URL_PATH) ?? '', '/');
if ($basePath !== '' && str_starts_with($intendedUrl, $basePath)) {
    $intendedUrl = substr($intendedUrl, strlen($basePath));
}

$isValidIntended =
    $intendedUrl !== '' &&
    !str_contains($intendedUrl, '..') &&           // cegah path traversal
    !str_starts_with($intendedUrl, '//') &&        // bukan protocol-relative URL
    (
        str_starts_with($intendedUrl, '/admin/') || // hanya halaman dalam project
        str_starts_with($intendedUrl, '/staff/')
    );

if ($isValidIntended) {
    header('Location: ' . BASE_URL . $intendedUrl);
    exit();
}

// Redirect default ke dashboard sesuai role
$dashboard = match ($matchedUser['role']) {
    'admin_eo' => BASE_URL . '/admin/dashboard.php',
    'staff_eo' => BASE_URL . '/staff/dashboard.php',
    default    => BASE_URL . '/login.php',
};

// ── 2FA: jika admin_eo mengaktifkan 2FA, tahan sesi & minta verifikasi ────────
if (
    $matchedUser['role'] === 'admin_eo'
    && (int) ($matchedUser['two_fa_enabled'] ?? 0) === 1
) {
    // Simpan data sesi yang sudah disiapkan ke "pending" — belum benar-benar login
    $pendingData = [];
    foreach (['user_id','eo_id','nama','nama_eo','role','email','first_login','__user_agent'] as $k) {
        $pendingData[$k] = $_SESSION[$k] ?? null;
        unset($_SESSION[$k]);   // Hapus dari sesi aktif — belum boleh akses
    }
    $_SESSION['__2fa_user_id'] = (int) $matchedUser['id'];
    $_SESSION['__2fa_data']    = $pendingData;

    // Buat & "kirim" OTP (require file helper murni fungsi, BUKAN
    // proses-verifikasi-2fa.php — file itu punya kode eksekusi CSRF/OTP
    // di level atas yang akan langsung jalan & exit() kalau di-require di sini)
    require_once __DIR__ . '/../../includes/otp-2fa.php';
    kirim_otp_2fa($mysqli, (int) $matchedUser['id']);

    header('Location: ' . BASE_URL . '/verifikasi-2fa.php');
    exit();
}

header('Location: ' . $dashboard);
exit();