<?php
/**
 * EO Finance — Proses update profil staff_eo.
 *
 * Aksi via POST field `action`:
 *   update_akun → Update nama & email akun staff
 *
 * Ganti password ditangani oleh proses/staff/proses-ganti-password.php.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role('staff_eo');

$userId = get_current_user_id();
$eoId   = get_current_eo_id();

$profilUrl = BASE_URL . '/staff/profil.php';

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
        $fail('Email terlalu panjang (maksimal 150 karakter).');
    }

    // Cek email unik dalam EO — kecuali milik akun staff ini sendiri
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
         WHERE id = ? AND eo_id = ? AND role = 'staff_eo'"
    );
    $stmt->bind_param('ssii', $nama, $email, $userId, $eoId);
    $stmt->execute();
    $stmt->close();

    // Sinkronkan sesi agar nama di sidebar terupdate langsung
    $_SESSION['nama']  = $nama;
    $_SESSION['email'] = $email;

    $ok('Data akun berhasil diperbarui.');
}

$fail('Aksi tidak dikenali.');