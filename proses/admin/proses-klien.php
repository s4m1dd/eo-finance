<?php
/**
 * EO Finance — Proses CRUD klien admin.
 *
 * Aksi via POST field `action`:
 *   tambah → INSERT klien baru
 *   edit   → UPDATE data klien
 *   hapus  → DELETE klien (ON DELETE SET NULL di events.klien_id)
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
$userId = get_current_user_id();

$_fail = static function (string $msg, string $to): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . $to);
    exit();
};

$_ok = static function (string $msg, string $to): never {
    $_SESSION['__flash']['success'] = $msg;
    header('Location: ' . $to);
    exit();
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/klien/index.php');
    exit();
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$_csrfPost    = $_POST['csrf_token']    ?? '';
$_csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($_csrfSession) || empty($_csrfPost) || !hash_equals($_csrfSession, $_csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_fail('Token keamanan tidak valid.', BASE_URL . '/admin/klien/index.php');
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$action = $_POST['action'] ?? '';

// ── Helper: sanitasi field klien ──────────────────────────────────────────────
// Mengembalikan array field yang sudah bersih dan siap untuk DB.
// null untuk field opsional yang kosong (sesuai kolom nullable di DB).
$_parseFields = static function (): array {
    return [
        'nama'    => trim($_POST['nama']    ?? ''),
        'email'   => strtolower(trim($_POST['email']   ?? '')),
        'telepon' => trim($_POST['telepon'] ?? ''),
        'alamat'  => trim($_POST['alamat']  ?? ''),
        'catatan' => trim($_POST['catatan'] ?? ''),
    ];
};

// ── Helper: validasi field wajib ──────────────────────────────────────────────
$_validateFields = static function (array $fields, string $backUrl) use (&$_fail): void {
    if ($fields['nama'] === '') {
        $_SESSION['__old_input'] = $_POST;
        $_fail('Nama klien wajib diisi.', $backUrl);
    }
    if (mb_strlen($fields['nama']) > 150) {
        $_SESSION['__old_input'] = $_POST;
        $_fail('Nama klien maksimal 150 karakter.', $backUrl);
    }
    if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $_SESSION['__old_input'] = $_POST;
        $_fail('Format email tidak valid.', $backUrl);
    }
    if ($fields['telepon'] !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $fields['telepon'])) {
        $_SESSION['__old_input'] = $_POST;
        $_fail('Format nomor telepon tidak valid.', $backUrl);
    }
};

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TAMBAH
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'tambah') {
    $backUrl = BASE_URL . '/admin/klien/tambah.php';
    $fields  = $_parseFields();

    $_validateFields($fields, $backUrl);

    $stmt = $mysqli->prepare("
        INSERT INTO klien
            (eo_id, nama, email, telepon, alamat, catatan, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $emailDb   = $fields['email']   !== '' ? $fields['email']   : null;
    $teleponDb = $fields['telepon'] !== '' ? $fields['telepon'] : null;
    $alamatDb  = $fields['alamat']  !== '' ? $fields['alamat']  : null;
    $catatanDb = $fields['catatan'] !== '' ? $fields['catatan'] : null;

    $stmt->bind_param(
        'isssss',
        $eoId,
        $fields['nama'], $emailDb, $teleponDb, $alamatDb, $catatanDb
    );
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'created', 'klien', (int) $mysqli->insert_id,
        'Menambah klien: ' . $fields['nama']
    );

    $_ok('Klien berhasil ditambahkan.', BASE_URL . '/admin/klien/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: EDIT
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'edit') {
    $klienId = (int) ($_POST['id'] ?? 0);
    $backUrl  = BASE_URL . '/admin/klien/edit.php?id=' . $klienId;

    if ($klienId <= 0) {
        $_fail('ID klien tidak valid.', BASE_URL . '/admin/klien/index.php');
    }

    // Validasi kepemilikan
    $stmtCek = $mysqli->prepare(
        'SELECT id FROM klien WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtCek->bind_param('ii', $klienId, $eoId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows === 0) {
        $stmtCek->close();
        $_fail('Klien tidak ditemukan.', BASE_URL . '/admin/klien/index.php');
    }
    $stmtCek->close();

    $fields = $_parseFields();
    $_validateFields($fields, $backUrl);

    $emailDb   = $fields['email']   !== '' ? $fields['email']   : null;
    $teleponDb = $fields['telepon'] !== '' ? $fields['telepon'] : null;
    $alamatDb  = $fields['alamat']  !== '' ? $fields['alamat']  : null;
    $catatanDb = $fields['catatan'] !== '' ? $fields['catatan'] : null;

    $stmt = $mysqli->prepare("
        UPDATE klien
        SET nama = ?, email = ?, telepon = ?, alamat = ?,
            catatan = ?, updated_at = NOW()
        WHERE id = ? AND eo_id = ?
    ");
    $stmt->bind_param(
        'sssssii',
        $fields['nama'], $emailDb, $teleponDb, $alamatDb, $catatanDb,
        $klienId, $eoId
    );
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'updated', 'klien', $klienId,
        'Memperbarui klien: ' . $fields['nama']
    );

    $_ok('Data klien berhasil diperbarui.', BASE_URL . '/admin/klien/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: HAPUS
// FK events.klien_id → ON DELETE SET NULL, jadi event tidak ikut terhapus.
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'hapus') {
    $klienId = (int) ($_POST['id'] ?? 0);

    if ($klienId <= 0) {
        $_fail('ID klien tidak valid.', BASE_URL . '/admin/klien/index.php');
    }

    $stmt = $mysqli->prepare(
        'DELETE FROM klien WHERE id = ? AND eo_id = ?'
    );
    $stmt->bind_param('ii', $klienId, $eoId);
    $stmt->execute();
    $affected = $mysqli->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        $_fail('Klien tidak ditemukan atau sudah dihapus.',
              BASE_URL . '/admin/klien/index.php');
    }

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'deleted', 'klien', $klienId,
        'Menghapus klien #' . $klienId
    );

    $_ok('Klien berhasil dihapus.', BASE_URL . '/admin/klien/index.php');
}

$_fail('Aksi tidak dikenali.', BASE_URL . '/admin/klien/index.php');