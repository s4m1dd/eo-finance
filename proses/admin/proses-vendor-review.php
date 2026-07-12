<?php
/**
 * EO Finance — Proses rating/catatan performa vendor.
 * Aksi: simpan (tambah atau update), hapus.
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

// ── Helper redirect ────────────────────────────────────────────────────────────
$ok   = function (string $msg, string $to): never {
    $_SESSION['__flash']['success'] = $msg;
    header('Location: ' . $to); exit();
};
$fail = function (string $msg, string $to): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . $to); exit();
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/vendor/index.php'); exit();
}

// CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    $fail('Token keamanan tidak valid.', BASE_URL . '/admin/vendor/index.php');
}

$action   = trim($_POST['action'] ?? '');
$vendorId = (int) ($_POST['vendor_id'] ?? 0);
$backTo   = $_POST['kembali_ke'] ?? BASE_URL . '/admin/vendor/index.php';

if ($vendorId <= 0) {
    $fail('Vendor tidak valid.', BASE_URL . '/admin/vendor/index.php');
}

// Pastikan vendor milik EO ini
$stmtCekV = $mysqli->prepare('SELECT id, nama FROM vendor WHERE id = ? AND eo_id = ? LIMIT 1');
$stmtCekV->bind_param('ii', $vendorId, $eoId);
$stmtCekV->execute();
$vendor = $stmtCekV->get_result()->fetch_assoc();
$stmtCekV->close();
if ($vendor === null) {
    $fail('Vendor tidak ditemukan.', BASE_URL . '/admin/vendor/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: SIMPAN (tambah atau update)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'simpan_review') {
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $rating  = max(1, min(5, (int) ($_POST['rating'] ?? 3)));
    $catatan = trim($_POST['catatan'] ?? '');

    if ($eventId <= 0) {
        $fail('Event tidak valid.', $backTo);
    }

    // Pastikan event milik EO & vendor pernah terlibat di event ini
    $stmtCekE = $mysqli->prepare(
        "SELECT e.id FROM events e
         INNER JOIN transaksi t ON t.event_id = e.id AND t.vendor_id = ?
         WHERE e.id = ? AND e.eo_id = ?
         LIMIT 1"
    );
    $stmtCekE->bind_param('iii', $vendorId, $eventId, $eoId);
    $stmtCekE->execute();
    $evOk = $stmtCekE->get_result()->fetch_assoc();
    $stmtCekE->close();

    if ($evOk === null) {
        $fail('Vendor tidak terlibat di event ini atau event tidak ditemukan.', $backTo);
    }

    $catatanDb = $catatan !== '' ? $catatan : null;

    // UPSERT: kalau sudah ada review untuk vendor+event yang sama, update saja
    $stmt = $mysqli->prepare(
        "INSERT INTO vendor_review (eo_id, vendor_id, event_id, created_by, rating, catatan)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             rating     = VALUES(rating),
             catatan    = VALUES(catatan),
             updated_at = NOW()"
    );
    $stmt->bind_param('iiiiis', $eoId, $vendorId, $eventId, $userId, $rating, $catatanDb);
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'created', 'vendor_review', $vendorId,
        sprintf('Memberi ulasan vendor "%s" rating %d/5 untuk event #%d', $vendor['nama'], $rating, $eventId)
    );

    $ok('Ulasan vendor berhasil disimpan.', $backTo);
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: HAPUS
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'hapus_review') {
    $reviewId = (int) ($_POST['review_id'] ?? 0);
    if ($reviewId <= 0) {
        $fail('ID ulasan tidak valid.', $backTo);
    }

    $stmt = $mysqli->prepare(
        'DELETE FROM vendor_review WHERE id = ? AND vendor_id = ? AND eo_id = ?'
    );
    $stmt->bind_param('iii', $reviewId, $vendorId, $eoId);
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'deleted', 'vendor_review', $vendorId,
        sprintf('Menghapus ulasan #%d vendor "%s"', $reviewId, $vendor['nama'])
    );

    $ok('Ulasan dihapus.', $backTo);
}

$fail('Aksi tidak dikenali.', BASE_URL . '/admin/vendor/index.php');