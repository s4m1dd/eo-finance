<?php
/**
 * EO Finance — Proses update profil admin_eo.
 *
 * Aksi via POST field `action`:
 *   update_akun    → Update nama & email akun admin
 *   ganti_password → Verifikasi password lama, simpan password baru
 *   update_eo      → Update nama, telepon, alamat EO
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role('admin_eo');

$userId = get_current_user_id();
$eoId   = get_current_eo_id();

$profilUrl = BASE_URL . '/admin/profil.php';

$fail = static function (string $msg) use ($profilUrl): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . $profilUrl);
    exit();
};

$ok = static function (string $msg) use ($profilUrl): never {
    $_SESSION['__flash']['success'] = $msg;
    header('Location: ' . $profilUrl);
    exit();
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $profilUrl);
    exit();
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$csrfPost    = $_POST['csrf_token']    ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($csrfSession) || empty($csrfPost) || !hash_equals($csrfSession, $csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $fail('Token keamanan tidak valid. Silakan coba lagi.');
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$action = $_POST['action'] ?? '';

// ════════════════════════════════════════════════════════════════════════════
// AKSI: UPDATE AKUN (nama + email)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'update_akun') {
    $nama  = trim($_POST['nama']  ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($nama === '') {
        $fail('Nama lengkap wajib diisi.');
    }
    if (mb_strlen($nama) > 100) {
        $fail('Nama maksimal 100 karakter.');
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fail('Format email tidak valid.');
    }
    if (mb_strlen($email) > 150) {
        $fail('Email terlalu panjang.');
    }

    // Cek email unik dalam EO — kecuali milik akun ini sendiri
    $stmtCek = $mysqli->prepare(
        'SELECT id FROM users WHERE email = ? AND eo_id = ? AND id != ? LIMIT 1'
    );
    $stmtCek->bind_param('sii', $email, $eoId, $userId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows > 0) {
        $stmtCek->close();
        $fail('Email sudah digunakan oleh akun lain di EO ini.');
    }
    $stmtCek->close();

    $stmt = $mysqli->prepare(
        "UPDATE users
         SET nama = ?, email = ?, updated_at = NOW()
         WHERE id = ? AND eo_id = ? AND role = 'admin_eo'"
    );
    $stmt->bind_param('ssii', $nama, $email, $userId, $eoId);
    $stmt->execute();
    $stmt->close();

    // Sinkronkan sesi agar nama di sidebar ikut terupdate
    $_SESSION['nama']  = $nama;
    $_SESSION['email'] = $email;

    $ok('Data akun berhasil diperbarui.');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: GANTI PASSWORD
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'ganti_password') {
    $passwordLama       = $_POST['password_lama']       ?? '';
    $passwordBaru       = $_POST['password_baru']       ?? '';
    $konfirmasiPassword = $_POST['konfirmasi_password'] ?? '';

    if ($passwordLama === '') {
        $fail('Password saat ini wajib diisi.');
    }
    if ($passwordBaru === '') {
        $fail('Password baru wajib diisi.');
    }
    if (mb_strlen($passwordBaru) < PASSWORD_MIN_LENGTH) {
        $fail('Password baru minimal ' . PASSWORD_MIN_LENGTH . ' karakter.');
    }
    if (mb_strlen($passwordBaru) > 72) {
        $fail('Password terlalu panjang (maksimal 72 karakter).');
    }
    if ($konfirmasiPassword === '') {
        $fail('Konfirmasi password wajib diisi.');
    }
    if (!hash_equals($passwordBaru, $konfirmasiPassword)) {
        $fail('Konfirmasi password tidak sama dengan password baru.');
    }

    // Ambil hash password lama dari DB
    $stmtOld = $mysqli->prepare(
        "SELECT password FROM users
         WHERE id = ? AND eo_id = ? AND role = 'admin_eo' LIMIT 1"
    );
    $stmtOld->bind_param('ii', $userId, $eoId);
    $stmtOld->execute();
    $hashLama = $stmtOld->get_result()->fetch_assoc()['password'] ?? '';
    $stmtOld->close();

    if (!password_verify($passwordLama, $hashLama)) {
        $fail('Password saat ini tidak sesuai.');
    }

    // Pastikan password baru berbeda dari yang lama
    if (password_verify($passwordBaru, $hashLama)) {
        $fail('Password baru tidak boleh sama dengan password saat ini.');
    }

    $hashBaru = password_hash($passwordBaru, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    unset($passwordLama, $passwordBaru, $konfirmasiPassword);

    $stmt = $mysqli->prepare(
        "UPDATE users
         SET password = ?, updated_at = NOW()
         WHERE id = ? AND eo_id = ? AND role = 'admin_eo'"
    );
    $stmt->bind_param('sii', $hashBaru, $userId, $eoId);
    $stmt->execute();
    $stmt->close();

    // Regenerasi session ID setelah perubahan kredensial
    session_regenerate_id(true);

    $ok('Password berhasil diperbarui.');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: UPDATE DATA EO
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'update_eo') {
    $namaEO  = trim($_POST['nama_eo'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat  = trim($_POST['alamat']  ?? '');

    if ($namaEO === '') {
        $fail('Nama EO wajib diisi.');
    }
    if (mb_strlen($namaEO) > 150) {
        $fail('Nama EO maksimal 150 karakter.');
    }
    if ($telepon !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $telepon)) {
        $fail('Format nomor telepon tidak valid.');
    }

    $teleponDb = $telepon !== '' ? $telepon : null;
    $alamatDb  = $alamat  !== '' ? $alamat  : null;

    $stmt = $mysqli->prepare(
        'UPDATE eo SET nama_eo = ?, telepon = ?, alamat = ?, updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->bind_param('sssi', $namaEO, $teleponDb, $alamatDb, $eoId);
    $stmt->execute();
    $stmt->close();

    // Sinkronkan sesi agar nama EO di sidebar ikut terupdate
    $_SESSION['nama_eo'] = $namaEO;

    $ok('Data EO berhasil diperbarui.');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TOGGLE 2FA
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'toggle_2fa') {
    $stmt = $mysqli->prepare(
        "UPDATE users
         SET two_fa_enabled = 1 - two_fa_enabled
         WHERE id = ? AND eo_id = ? AND role = 'admin_eo'"
    );
    $stmt->bind_param('ii', $userId, $eoId);
    $stmt->execute();
    $stmt->close();

    // Baca status baru
    $stmtCek = $mysqli->prepare(
        'SELECT two_fa_enabled FROM users WHERE id = ? LIMIT 1'
    );
    $stmtCek->bind_param('i', $userId);
    $stmtCek->execute();
    $statusBaru = (int) $stmtCek->get_result()->fetch_assoc()['two_fa_enabled'];
    $stmtCek->close();

    $pesanStatus = $statusBaru === 1
        ? '2FA berhasil diaktifkan. Login berikutnya akan memerlukan kode OTP.'
        : '2FA dinonaktifkan.';

    require_once __DIR__ . '/../../includes/functions.php';
    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'updated', 'users', $userId,
        $statusBaru === 1 ? 'Mengaktifkan 2FA' : 'Menonaktifkan 2FA'
    );

    $ok($pesanStatus);
}

$fail('Aksi tidak dikenali.');