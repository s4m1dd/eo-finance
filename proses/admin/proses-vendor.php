<?php
/**
 * EO Finance — Proses CRUD vendor admin.
 *
 * Aksi via POST field `action`:
 *   tambah → INSERT vendor baru
 *   edit   → UPDATE data vendor
 *   hapus  → DELETE vendor (FK transaksi.vendor_id ON DELETE SET NULL)
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

// ── Helpers redirect ──────────────────────────────────────────────────────────
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

// ── POST guard ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/vendor/index.php');
    exit();
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$csrfPost    = $_POST['csrf_token']    ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($csrfSession) || empty($csrfPost) || !hash_equals($csrfSession, $csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $fail('Token keamanan tidak valid.', BASE_URL . '/admin/vendor/index.php');
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$action = $_POST['action'] ?? '';

// ── Helper: ambil dan bersihkan field dari POST ───────────────────────────────
$parseFields = static function (): array {
    return [
        'nama'             => trim($_POST['nama']             ?? ''),
        'kategori_layanan' => trim($_POST['kategori_layanan'] ?? ''),
        'email'            => strtolower(trim($_POST['email'] ?? '')),
        'telepon'          => trim($_POST['telepon']          ?? ''),
        'alamat'           => trim($_POST['alamat']           ?? ''),
        'catatan'          => trim($_POST['catatan']          ?? ''),
    ];
};

// ── Helper: validasi field wajib ──────────────────────────────────────────────
$validateFields = static function (array $f, string $backUrl) use ($fail): void {
    if ($f['nama'] === '') {
        $_SESSION['__old_input'] = $_POST;
        $fail('Nama vendor wajib diisi.', $backUrl);
    }
    if (mb_strlen($f['nama']) > 150) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Nama vendor maksimal 150 karakter.', $backUrl);
    }
    if ($f['email'] !== '' && !filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Format email tidak valid.', $backUrl);
    }
    if ($f['telepon'] !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $f['telepon'])) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Format nomor telepon tidak valid.', $backUrl);
    }
    if (mb_strlen($f['kategori_layanan']) > 100) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Kategori layanan maksimal 100 karakter.', $backUrl);
    }
};

// ── Helper: null untuk string kosong (kolom nullable) ─────────────────────────
$n = static fn(string $val): ?string => $val !== '' ? $val : null;

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TAMBAH
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'tambah') {
    $backUrl = BASE_URL . '/admin/vendor/tambah.php';
    $f       = $parseFields();
    $validateFields($f, $backUrl);

    $kategoriLayanan = $n($f['kategori_layanan']);
    $email           = $n($f['email']);
    $telepon         = $n($f['telepon']);
    $alamat          = $n($f['alamat']);
    $catatan         = $n($f['catatan']);

    $stmt = $mysqli->prepare("
        INSERT INTO vendor
            (eo_id, nama, kategori_layanan, email, telepon,
             alamat, catatan, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param(
        'issssss',
        $eoId,
        $f['nama'],
        $kategoriLayanan,
        $email,
        $telepon,
        $alamat,
        $catatan
    );
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'created', 'vendor', (int) $mysqli->insert_id,
        'Menambah vendor: ' . $f['nama']
    );

    $ok('Vendor berhasil ditambahkan.', BASE_URL . '/admin/vendor/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: EDIT
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'edit') {
    $vendorId = (int) ($_POST['id'] ?? 0);
    $backUrl  = BASE_URL . '/admin/vendor/edit.php?id=' . $vendorId;

    if ($vendorId <= 0) {
        $fail('ID vendor tidak valid.', BASE_URL . '/admin/vendor/index.php');
    }

    // Validasi kepemilikan — mencegah edit vendor EO lain
    $stmtCek = $mysqli->prepare(
        'SELECT id FROM vendor WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtCek->bind_param('ii', $vendorId, $eoId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows === 0) {
        $stmtCek->close();
        $fail('Vendor tidak ditemukan.', BASE_URL . '/admin/vendor/index.php');
    }
    $stmtCek->close();

    $f = $parseFields();
    $validateFields($f, $backUrl);

    $kategoriLayanan = $n($f['kategori_layanan']);
    $email           = $n($f['email']);
    $telepon         = $n($f['telepon']);
    $alamat          = $n($f['alamat']);
    $catatan         = $n($f['catatan']);

    $stmt = $mysqli->prepare("
        UPDATE vendor
        SET nama = ?, kategori_layanan = ?, email = ?, telepon = ?,
            alamat = ?, catatan = ?, updated_at = NOW()
        WHERE id = ? AND eo_id = ?
    ");
    $stmt->bind_param(
        'ssssssii',
        $f['nama'],
        $kategoriLayanan,
        $email,
        $telepon,
        $alamat,
        $catatan,
        $vendorId,
        $eoId
    );
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'updated', 'vendor', $vendorId,
        'Memperbarui vendor: ' . $f['nama']
    );

    $ok('Data vendor berhasil diperbarui.', BASE_URL . '/admin/vendor/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: HAPUS
// transaksi.vendor_id ON DELETE SET NULL — transaksi tidak ikut terhapus.
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'hapus') {
    $vendorId = (int) ($_POST['id'] ?? 0);

    if ($vendorId <= 0) {
        $fail('ID vendor tidak valid.', BASE_URL . '/admin/vendor/index.php');
    }

    $stmt = $mysqli->prepare(
        'DELETE FROM vendor WHERE id = ? AND eo_id = ?'
    );
    $stmt->bind_param('ii', $vendorId, $eoId);
    $stmt->execute();
    $affected = $mysqli->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        $fail('Vendor tidak ditemukan atau sudah dihapus.',
              BASE_URL . '/admin/vendor/index.php');
    }

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'deleted', 'vendor', $vendorId,
        'Menghapus vendor #' . $vendorId
    );

    $ok('Vendor berhasil dihapus.', BASE_URL . '/admin/vendor/index.php');
}

$fail('Aksi tidak dikenali.', BASE_URL . '/admin/vendor/index.php');