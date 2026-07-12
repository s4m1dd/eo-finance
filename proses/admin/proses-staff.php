<?php
/**
 * EO Finance — Proses manajemen staff admin.
 *
 * Aksi via POST field `action`:
 *   tambah           → Buat akun staff baru + password sementara
 *   edit             → Update nama & email staff
 *   hapus            → Hapus akun staff (gagal jika ada transaksi)
 *   toggle_aktif     → Aktifkan / nonaktifkan akun
 *   reset_password   → Set first_login = 1 + set password sementara baru
 *   tambah_penugasan → INSERT ke tabel penugasan
 *   lepas_penugasan  → DELETE dari tabel penugasan
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_role('admin_eo');

$eoId   = get_current_eo_id();
$userId = get_current_user_id(); // ID admin yang sedang login

$fail = static function (string $msg, string $to): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . $to);
    exit();
};

$ok = static function (string $msg, string $to): never {
    $_SESSION['__flash']['success'] = $msg;
    header('Location: ' . $to);
    exit();
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/staff/index.php');
    exit();
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$csrfPost    = $_POST['csrf_token']    ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($csrfSession) || empty($csrfPost) || !hash_equals($csrfSession, $csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $fail('Token keamanan tidak valid.', BASE_URL . '/admin/staff/index.php');
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$action = $_POST['action'] ?? '';

// ── Helper: pastikan staff ID valid & milik EO ini ────────────────────────────
$getStaff = static function (int $staffId) use ($mysqli, $eoId, $fail): array {
    $stmt = $mysqli->prepare(
        "SELECT id, nama, email, is_active, first_login
         FROM users
         WHERE id = ? AND eo_id = ? AND role = 'staff_eo'
         LIMIT 1"
    );
    $stmt->bind_param('ii', $staffId, $eoId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row === null) {
        $fail('Staff tidak ditemukan.', BASE_URL . '/admin/staff/index.php');
    }
    return $row;
};

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TAMBAH
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'tambah') {
    $backUrl = BASE_URL . '/admin/staff/tambah.php';

    $nama             = trim($_POST['nama']              ?? '');
    $email            = strtolower(trim($_POST['email'] ?? ''));
    $passwordSementara = $_POST['password_sementara']    ?? '';

    if ($nama === '') {
        $_SESSION['__old_input'] = $_POST;
        $fail('Nama wajib diisi.', $backUrl);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Email tidak valid.', $backUrl);
    }
    if (mb_strlen($passwordSementara) < PASSWORD_MIN_LENGTH) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Password sementara minimal ' . PASSWORD_MIN_LENGTH . ' karakter.', $backUrl);
    }

    // Cek email unik dalam EO ini
    $stmtCek = $mysqli->prepare(
        'SELECT id FROM users WHERE email = ? AND eo_id = ? LIMIT 1'
    );
    $stmtCek->bind_param('si', $email, $eoId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows > 0) {
        $stmtCek->close();
        $_SESSION['__old_input'] = $_POST;
        $fail('Email sudah digunakan oleh akun lain di EO ini.', $backUrl);
    }
    $stmtCek->close();

    $hash = password_hash($passwordSementara, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    // Simpan plaintext sementara untuk ditampilkan sekali ke admin
    $plain = $passwordSementara;
    unset($passwordSementara);

    $stmt = $mysqli->prepare("
        INSERT INTO users
            (eo_id, nama, email, password, role, first_login, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'staff_eo', 1, 1, NOW(), NOW())
    ");
    $stmt->bind_param('isss', $eoId, $nama, $email, $hash);
    $stmt->execute();
    $newStaffId = (int) $mysqli->insert_id;
    $stmt->close();

    // Tampilkan password sementara sekali di halaman tambah (via flash sesi)
    $_SESSION['__temp_password'] = $plain;
    $_SESSION['__flash']['success'] = 'Akun staff berhasil dibuat.';

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'created', 'users', $newStaffId,
        'Membuat akun staff baru: ' . $nama . ' (' . $email . ')'
    );

    kirim_notifikasi(
        $mysqli, $eoId, $newStaffId,
        'staff_baru',
        'Selamat Datang di EO Finance!',
        'Akun kamu sudah aktif. Segera login dan ganti password sementara.',
        '/staff/dashboard.php'
    );

    header('Location: ' . BASE_URL . '/admin/staff/tambah.php');
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: EDIT
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'edit') {
    $staffId = (int) ($_POST['id'] ?? 0);
    $backUrl  = BASE_URL . '/admin/staff/edit.php?id=' . $staffId;

    if ($staffId <= 0) {
        $fail('ID tidak valid.', BASE_URL . '/admin/staff/index.php');
    }

    $getStaff($staffId); // validasi kepemilikan

    $nama  = trim($_POST['nama']  ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($nama === '') {
        $_SESSION['__old_input'] = $_POST;
        $fail('Nama wajib diisi.', $backUrl);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Email tidak valid.', $backUrl);
    }

    // Cek email unik dalam EO (kecuali milik staff ini sendiri)
    $stmtCek = $mysqli->prepare(
        'SELECT id FROM users WHERE email = ? AND eo_id = ? AND id != ? LIMIT 1'
    );
    $stmtCek->bind_param('sii', $email, $eoId, $staffId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows > 0) {
        $stmtCek->close();
        $_SESSION['__old_input'] = $_POST;
        $fail('Email sudah digunakan oleh akun lain di EO ini.', $backUrl);
    }
    $stmtCek->close();

    $stmt = $mysqli->prepare(
        "UPDATE users SET nama = ?, email = ?, updated_at = NOW()
         WHERE id = ? AND eo_id = ? AND role = 'staff_eo'"
    );
    $stmt->bind_param('ssii', $nama, $email, $staffId, $eoId);
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'updated', 'users', $staffId,
        'Memperbarui data staff #' . $staffId . ': ' . $nama
    );

    $ok('Data staff berhasil diperbarui.', $backUrl);
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: HAPUS
// Akan gagal jika staff masih punya transaksi (ON DELETE RESTRICT di FK).
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'hapus') {
    $staffId = (int) ($_POST['id'] ?? 0);

    if ($staffId <= 0) {
        $fail('ID tidak valid.', BASE_URL . '/admin/staff/index.php');
    }
    if ($staffId === $userId) {
        $fail('Tidak bisa menghapus akun sendiri.', BASE_URL . '/admin/staff/index.php');
    }

    $getStaff($staffId);

    try {
        $stmt = $mysqli->prepare(
            "DELETE FROM users WHERE id = ? AND eo_id = ? AND role = 'staff_eo'"
        );
        $stmt->bind_param('ii', $staffId, $eoId);
        $stmt->execute();
        $affected = $mysqli->affected_rows;
        $stmt->close();

        if ($affected === 0) {
            $fail('Staff tidak ditemukan.', BASE_URL . '/admin/staff/index.php');
        }
    } catch (mysqli_sql_exception $e) {
        // Kode 1451: FK constraint — staff masih punya transaksi
        if ($e->getCode() === 1451) {
            $fail(
                'Staff tidak bisa dihapus karena masih memiliki transaksi tercatat. ' .
                'Nonaktifkan akun sebagai gantinya.',
                BASE_URL . '/admin/staff/edit.php?id=' . $staffId
            );
        }
        throw $e;
    }

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'deleted', 'users', $staffId,
        'Menghapus akun staff #' . $staffId
    );

    $ok('Akun staff berhasil dihapus.', BASE_URL . '/admin/staff/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TOGGLE AKTIF
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'toggle_aktif') {
    $staffId = (int) ($_POST['id'] ?? 0);

    if ($staffId <= 0) {
        $fail('ID tidak valid.', BASE_URL . '/admin/staff/index.php');
    }

    $sf = $getStaff($staffId);

    $newStatus = (int)$sf['is_active'] === 1 ? 0 : 1;
    $label     = $newStatus === 1 ? 'diaktifkan' : 'dinonaktifkan';

    $stmt = $mysqli->prepare(
        "UPDATE users SET is_active = ?, updated_at = NOW()
         WHERE id = ? AND eo_id = ? AND role = 'staff_eo'"
    );
    $stmt->bind_param('iii', $newStatus, $staffId, $eoId);
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'updated', 'users', $staffId,
        'Mengubah status akun staff #' . $staffId . ': ' . $label
    );

    $ok("Akun staff berhasil {$label}.", BASE_URL . '/admin/staff/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: RESET PASSWORD
// Set first_login = 1 + password sementara baru → staff wajib ganti saat login
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'reset_password') {
    $staffId = (int) ($_POST['id'] ?? 0);

    if ($staffId <= 0) {
        $fail('ID tidak valid.', BASE_URL . '/admin/staff/index.php');
    }

    $getStaff($staffId);

    // Generate password sementara baru (8 karakter acak alphanumeric)
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $tempPass = '';
    for ($i = 0; $i < 8; $i++) {
        $tempPass .= $chars[random_int(0, strlen($chars) - 1)];
    }

    $hash = password_hash($tempPass, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

    $stmt = $mysqli->prepare(
        "UPDATE users
         SET password = ?, first_login = 1, updated_at = NOW()
         WHERE id = ? AND eo_id = ? AND role = 'staff_eo'"
    );
    $stmt->bind_param('sii', $hash, $staffId, $eoId);
    $stmt->execute();
    $stmt->close();

    // Tampilkan password baru sekali di halaman edit
    $_SESSION['__temp_password'] = $tempPass;
    $_SESSION['__flash']['success'] =
        'Password staff berhasil direset. Catat password sementara baru di bawah.';

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'updated', 'users', $staffId,
        'Reset password staff #' . $staffId
    );

    header('Location: ' . BASE_URL . '/admin/staff/edit.php?id=' . $staffId);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TAMBAH PENUGASAN
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'tambah_penugasan') {
    $staffId        = (int) ($_POST['user_id']  ?? 0);
    $eventId        = (int) ($_POST['event_id'] ?? 0);
    $roleDalamEvent = trim($_POST['role_dalam_event'] ?? '');

    $backUrl = BASE_URL . '/admin/staff/edit.php?id=' . $staffId;

    if ($staffId <= 0 || $eventId <= 0) {
        $fail('Data penugasan tidak valid.', BASE_URL . '/admin/staff/index.php');
    }

    $getStaff($staffId);

    // Pastikan event milik EO ini
    $stmtEv = $mysqli->prepare(
        'SELECT id FROM events WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtEv->bind_param('ii', $eventId, $eoId);
    $stmtEv->execute();
    $stmtEv->store_result();
    if ($stmtEv->num_rows === 0) {
        $stmtEv->close();
        $fail('Event tidak valid.', $backUrl);
    }
    $stmtEv->close();

    // UNIQUE constraint (event_id, user_id) mencegah duplikasi di DB,
    // tapi cek dulu agar pesan error lebih informatif
    $stmtCek = $mysqli->prepare(
        'SELECT id FROM penugasan WHERE event_id = ? AND user_id = ? LIMIT 1'
    );
    $stmtCek->bind_param('ii', $eventId, $staffId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows > 0) {
        $stmtCek->close();
        $fail('Staff sudah ditugaskan ke event ini.', $backUrl);
    }
    $stmtCek->close();

    $roleDb = $roleDalamEvent !== '' ? $roleDalamEvent : null;

    $stmt = $mysqli->prepare("
        INSERT INTO penugasan
            (eo_id, event_id, user_id, role_dalam_event, created_at, updated_at)
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param('iiis', $eoId, $eventId, $staffId, $roleDb);
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'created', 'penugasan', (int) $mysqli->insert_id,
        sprintf('Menugaskan staff #%d ke event #%d', $staffId, $eventId)
    );

    $ok('Staff berhasil ditugaskan ke event.', $backUrl);
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: LEPAS PENUGASAN
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'lepas_penugasan') {
    $staffId = (int) ($_POST['user_id']  ?? 0);
    $eventId = (int) ($_POST['event_id'] ?? 0);

    $backUrl = BASE_URL . '/admin/staff/edit.php?id=' . $staffId;

    if ($staffId <= 0 || $eventId <= 0) {
        $fail('Data tidak valid.', BASE_URL . '/admin/staff/index.php');
    }

    $getStaff($staffId);

    $stmt = $mysqli->prepare(
        'DELETE FROM penugasan WHERE event_id = ? AND user_id = ? AND eo_id = ?'
    );
    $stmt->bind_param('iii', $eventId, $staffId, $eoId);
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'deleted', 'penugasan', null,
        sprintf('Melepas penugasan staff #%d dari event #%d', $staffId, $eventId)
    );

    $ok('Penugasan berhasil dilepas.', $backUrl);
}

$fail('Aksi tidak dikenali.', BASE_URL . '/admin/staff/index.php');