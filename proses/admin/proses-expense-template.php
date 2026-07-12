<?php
/**
 * EO Finance — Proses Expense Template.
 *
 * Aksi: tambah | toggle (aktif/nonaktif) | hapus | api_list (JSON untuk AJAX).
 * Endpoint api_list dipakai oleh form tambah transaksi untuk mengisi
 * field otomatis saat template dipilih.
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
$action = trim($_REQUEST['action'] ?? '');

// ── Endpoint JSON: daftar template aktif (dipanggil via fetch() dari form) ────
if ($action === 'api_list') {
    header('Content-Type: application/json; charset=utf-8');
    $stmt = $mysqli->prepare(
        "SELECT id, nama_template, tipe, kategori, jumlah_estimasi, deskripsi
         FROM expense_template
         WHERE eo_id = ? AND is_active = 1
         ORDER BY tipe ASC, nama_template ASC"
    );
    $stmt->bind_param('i', $eoId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit();
}

// ── Semua aksi lain hanya terima POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/transaksi/template.php'); exit();
}

$backTo = BASE_URL . '/admin/transaksi/template.php';

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    $_SESSION['__flash']['error'] = 'Token keamanan tidak valid.';
    header('Location: ' . $backTo); exit();
}

$ok = function (string $msg) use ($backTo): never {
    $_SESSION['__flash']['success'] = $msg;
    header('Location: ' . $backTo); exit();
};
$fail = function (string $msg) use ($backTo): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . $backTo); exit();
};

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TAMBAH
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'tambah') {
    $nama     = trim($_POST['nama_template']   ?? '');
    $tipe     = trim($_POST['tipe']            ?? '');
    $kategori = trim($_POST['kategori']        ?? '');
    $deskripsi = trim($_POST['deskripsi']      ?? '');
    $jumlahRaw = trim($_POST['jumlah_estimasi'] ?? '');

    if ($nama === '')     $fail('Nama template wajib diisi.');
    if (!in_array($tipe, ['pemasukan','pengeluaran'], true)) $fail('Tipe tidak valid.');
    if ($kategori === '') $fail('Kategori wajib diisi.');

    // Bersihkan angka — hapus titik ribuan, ganti koma desimal
    $jumlahDb = $jumlahRaw !== ''
        ? (float) str_replace(['.', ','], ['', '.'], $jumlahRaw)
        : null;
    $deskripsiDb = $deskripsi !== '' ? $deskripsi : null;

    $stmt = $mysqli->prepare(
        "INSERT INTO expense_template
             (eo_id, nama_template, tipe, kategori, jumlah_estimasi, deskripsi, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isssdsi',
        $eoId, $nama, $tipe, $kategori, $jumlahDb, $deskripsiDb, $userId
    );
    $stmt->execute();
    $newId = $mysqli->insert_id;
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'created', 'expense_template', $newId,
        sprintf('Menambah template "%s" (%s / %s)', $nama, $tipe, $kategori)
    );

    $ok('Template berhasil disimpan.');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TOGGLE (aktif ↔ nonaktif)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) $fail('ID tidak valid.');

    $stmt = $mysqli->prepare(
        'UPDATE expense_template SET is_active = 1 - is_active WHERE id = ? AND eo_id = ?'
    );
    $stmt->bind_param('ii', $id, $eoId);
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'updated', 'expense_template', $id,
        sprintf('Toggle status template #%d', $id)
    );

    $ok('Status template diperbarui.');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: HAPUS
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'hapus') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) $fail('ID tidak valid.');

    $stmt = $mysqli->prepare(
        'DELETE FROM expense_template WHERE id = ? AND eo_id = ?'
    );
    $stmt->bind_param('ii', $id, $eoId);
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'deleted', 'expense_template', $id,
        sprintf('Menghapus template #%d', $id)
    );

    $ok('Template dihapus.');
}

$fail('Aksi tidak dikenali.');