<?php
/**
 * EO Finance — Proses penugasan staff ke event.
 *
 * Aksi via POST field `action`:
 *   tambah → INSERT baris baru ke tabel penugasan
 *   lepas  → DELETE penugasan (staff dilepas dari event)
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

$eoId = get_current_eo_id();

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

$indexUrl = BASE_URL . '/admin/penugasan/index.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $indexUrl);
    exit();
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$csrfPost    = $_POST['csrf_token']    ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($csrfSession) || empty($csrfPost) || !hash_equals($csrfSession, $csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $fail('Token keamanan tidak valid.', $indexUrl);
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$action = $_POST['action'] ?? '';

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TAMBAH
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'tambah') {
    $eventId        = (int) ($_POST['event_id'] ?? 0);
    $staffId        = (int) ($_POST['user_id']  ?? 0);
    $roleDalamEvent = trim($_POST['role_dalam_event'] ?? '');

    if ($eventId <= 0 || $staffId <= 0) {
        $fail('Event dan staff wajib dipilih.', $indexUrl);
    }

    // Validasi event milik EO ini dan masih aktif/draft
    $stmtEv = $mysqli->prepare(
        "SELECT id FROM events
         WHERE id = ? AND eo_id = ? AND status IN ('draft','aktif')
         LIMIT 1"
    );
    $stmtEv->bind_param('ii', $eventId, $eoId);
    $stmtEv->execute();
    $stmtEv->store_result();
    if ($stmtEv->num_rows === 0) {
        $stmtEv->close();
        $fail('Event tidak valid atau sudah tidak aktif.', $indexUrl);
    }
    $stmtEv->close();

    // Validasi staff milik EO ini dan aktif
    $stmtSf = $mysqli->prepare(
        "SELECT id FROM users
         WHERE id = ? AND eo_id = ? AND role = 'staff_eo' AND is_active = 1
         LIMIT 1"
    );
    $stmtSf->bind_param('ii', $staffId, $eoId);
    $stmtSf->execute();
    $stmtSf->store_result();
    if ($stmtSf->num_rows === 0) {
        $stmtSf->close();
        $fail('Staff tidak ditemukan atau tidak aktif.', $indexUrl);
    }
    $stmtSf->close();

    // Cek duplikasi — UNIQUE KEY uq_penugasan_event_user(event_id, user_id)
    $stmtCek = $mysqli->prepare(
        'SELECT id FROM penugasan WHERE event_id = ? AND user_id = ? LIMIT 1'
    );
    $stmtCek->bind_param('ii', $eventId, $staffId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows > 0) {
        $stmtCek->close();
        $fail('Staff ini sudah ditugaskan ke event tersebut.', $indexUrl);
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

    // Redirect kembali ke penugasan index dengan filter event yang baru ditugaskan
    $ok(
        'Staff berhasil ditugaskan ke event.',
        $indexUrl . '?event_id=' . $eventId
    );
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: LEPAS
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'lepas') {
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $staffId = (int) ($_POST['user_id']  ?? 0);

    if ($eventId <= 0 || $staffId <= 0) {
        $fail('Data penugasan tidak valid.', $indexUrl);
    }

    // eo_id di WHERE memastikan tidak bisa lepas penugasan milik EO lain
    $stmt = $mysqli->prepare(
        'DELETE FROM penugasan WHERE event_id = ? AND user_id = ? AND eo_id = ?'
    );
    $stmt->bind_param('iii', $eventId, $staffId, $eoId);
    $stmt->execute();
    $affected = $mysqli->affected_rows;
    $stmt->close();

    if ($affected === 0) {
        $fail('Penugasan tidak ditemukan.', $indexUrl);
    }

    $ok('Penugasan berhasil dilepas.', $indexUrl);
}

$fail('Aksi tidak dikenali.', $indexUrl);