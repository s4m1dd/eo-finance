<?php
/**
 * EO Finance — Proses CRUD event admin.
 *
 * Menangani tiga aksi via POST field `action`:
 *   tambah     → INSERT event baru
 *   edit       → UPDATE event yang sudah ada
 *   hapus      → DELETE event (CASCADE ke transaksi & penugasan)
 *   lepas_staff → DELETE satu baris penugasan
 *
 * Semua aksi memvalidasi kepemilikan EO sebelum menyentuh data.
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

// ── Helpers ───────────────────────────────────────────────────────────────────
$eoId   = get_current_eo_id();
$userId = get_current_user_id();

$_fail = static function (string $msg, string $redirect): never {
    $_SESSION['__flash']['error'] = $msg;
    header('Location: ' . $redirect);
    exit();
};

$_ok = static function (string $msg, string $redirect): never {
    $_SESSION['__flash']['success'] = $msg;
    header('Location: ' . $redirect);
    exit();
};

// ── Hanya POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/events/index.php');
    exit();
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$_csrfPost    = $_POST['csrf_token'] ?? '';
$_csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($_csrfSession) || empty($_csrfPost) || !hash_equals($_csrfSession, $_csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
   $_fail('Token keamanan tidak valid.', BASE_URL . '/admin/events/index.php');
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$action = $_POST['action'] ?? '';

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TAMBAH
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'tambah') {
    $namaEvent      = trim($_POST['nama_event']      ?? '');
    $tanggalMulai   = trim($_POST['tanggal_mulai']   ?? '');
    $tanggalSelesai = trim($_POST['tanggal_selesai'] ?? '');
    $klienId        = (int) ($_POST['klien_id']       ?? 0);
    $status         = trim($_POST['status']           ?? 'draft');
    $lokasi         = trim($_POST['lokasi']           ?? '');
    $anggaran       = trim($_POST['anggaran']         ?? '0');
    $deskripsi      = trim($_POST['deskripsi']        ?? '');

    // ── Validasi ──────────────────────────────────────────────────────────────
    $backUrl = BASE_URL . '/admin/events/tambah.php';

    if ($namaEvent === '') {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Nama event wajib diisi.', $backUrl);
    }
    if (mb_strlen($namaEvent) > 200) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Nama event maksimal 200 karakter.', $backUrl);
    }
    if ($tanggalMulai === '' || $tanggalSelesai === '') {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Tanggal mulai dan selesai wajib diisi.', $backUrl);
    }
    if ($tanggalSelesai < $tanggalMulai) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Tanggal selesai tidak boleh sebelum tanggal mulai.', $backUrl);
    }
    if (!is_numeric($anggaran) || (float)$anggaran < 0) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Anggaran harus berupa angka positif.', $backUrl);
    }
    $validStatus = ['draft', 'aktif', 'selesai', 'dibatalkan'];
    if (!in_array($status, $validStatus, true)) {
        $status = 'draft';
    }

    // klien_id: validasi kepemilikan jika diisi
    $klienIdDb = null;
    if ($klienId > 0) {
        $stmtKl = $mysqli->prepare(
            'SELECT id FROM klien WHERE id = ? AND eo_id = ? LIMIT 1'
        );
        $stmtKl->bind_param('ii', $klienId, $eoId);
        $stmtKl->execute();
        $stmtKl->store_result();
        if ($stmtKl->num_rows > 0) {
            $klienIdDb = $klienId;
        }
        $stmtKl->close();
    }

    $anggaranDb = round((float)$anggaran, 2);
    $lokasiDb   = $lokasi !== '' ? $lokasi : null;
    $deskDb     = $deskripsi !== '' ? $deskripsi : null;

    $stmt = $mysqli->prepare("
        INSERT INTO events
            (eo_id, klien_id, nama_event, deskripsi, tanggal_mulai,
             tanggal_selesai, lokasi, status, anggaran, created_by,
             created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt->bind_param(
        'iissssssdi',
        $eoId, $klienIdDb, $namaEvent, $deskDb,
        $tanggalMulai, $tanggalSelesai, $lokasiDb,
        $status, $anggaranDb, $userId
    );
    $stmt->execute();
    $newId = (int) $mysqli->insert_id;
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'created', 'events', $newId,
        'Membuat event: ' . $namaEvent
    );

   $_ok('Event berhasil dibuat.', BASE_URL . '/admin/events/detail.php?id=' . $newId);
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: EDIT
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'edit') {
    $eventId        = (int) ($_POST['id']             ?? 0);
    $namaEvent      = trim($_POST['nama_event']       ?? '');
    $tanggalMulai   = trim($_POST['tanggal_mulai']    ?? '');
    $tanggalSelesai = trim($_POST['tanggal_selesai']  ?? '');
    $klienId        = (int) ($_POST['klien_id']        ?? 0);
    $status         = trim($_POST['status']            ?? 'draft');
    $lokasi         = trim($_POST['lokasi']            ?? '');
    $anggaran       = trim($_POST['anggaran']          ?? '0');
    $deskripsi      = trim($_POST['deskripsi']         ?? '');

    $backUrl = BASE_URL . '/admin/events/edit.php?id=' . $eventId;

    if ($eventId <= 0) {
       $_fail('ID event tidak valid.', BASE_URL . '/admin/events/index.php');
    }

    // Pastikan event milik EO ini
    $stmtCek = $mysqli->prepare(
        'SELECT id FROM events WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtCek->bind_param('ii', $eventId, $eoId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows === 0) {
        $stmtCek->close();
       $_fail('Event tidak ditemukan.', BASE_URL . '/admin/events/index.php');
    }
    $stmtCek->close();

    // Validasi field
    if ($namaEvent === '') {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Nama event wajib diisi.', $backUrl);
    }
    if ($tanggalMulai === '' || $tanggalSelesai === '') {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Tanggal mulai dan selesai wajib diisi.', $backUrl);
    }
    if ($tanggalSelesai < $tanggalMulai) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Tanggal selesai tidak boleh sebelum tanggal mulai.', $backUrl);
    }
    if (!is_numeric($anggaran) || (float)$anggaran < 0) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Anggaran harus berupa angka positif.', $backUrl);
    }
    $validStatus = ['draft', 'aktif', 'selesai', 'dibatalkan'];
    if (!in_array($status, $validStatus, true)) {
        $status = 'draft';
    }

    $klienIdDb = null;
    if ($klienId > 0) {
        $stmtKl = $mysqli->prepare(
            'SELECT id FROM klien WHERE id = ? AND eo_id = ? LIMIT 1'
        );
        $stmtKl->bind_param('ii', $klienId, $eoId);
        $stmtKl->execute();
        $stmtKl->store_result();
        if ($stmtKl->num_rows > 0) {
            $klienIdDb = $klienId;
        }
        $stmtKl->close();
    }

    $anggaranDb = round((float)$anggaran, 2);
    $lokasiDb   = $lokasi !== '' ? $lokasi : null;
    $deskDb     = $deskripsi !== '' ? $deskripsi : null;

    $stmt = $mysqli->prepare("
        UPDATE events
        SET nama_event = ?, deskripsi = ?, tanggal_mulai = ?,
            tanggal_selesai = ?, lokasi = ?, status = ?,
            anggaran = ?, klien_id = ?, updated_at = NOW()
        WHERE id = ? AND eo_id = ?
    ");
    $stmt->bind_param(
        'ssssssdiii',
        $namaEvent, $deskDb, $tanggalMulai, $tanggalSelesai,
        $lokasiDb, $status, $anggaranDb, $klienIdDb,
        $eventId, $eoId
    );
    $stmt->execute();
    $stmt->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'updated', 'events', $eventId,
        'Memperbarui event: ' . $namaEvent
    );

   $_ok('Event berhasil diperbarui.', BASE_URL . '/admin/events/detail.php?id=' . $eventId);
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: HAPUS
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'hapus') {
    $eventId = (int) ($_POST['id'] ?? 0);

    if ($eventId <= 0) {
       $_fail('ID event tidak valid.', BASE_URL . '/admin/events/index.php');
    }

    // Ambil snapshot nama event untuk audit log sebelum hapus
    $stmtSnap = $mysqli->prepare(
        'SELECT nama_event FROM events WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtSnap->bind_param('ii', $eventId, $eoId);
    $stmtSnap->execute();
    $snapEvent = $stmtSnap->get_result()->fetch_assoc();
    $stmtSnap->close();

    // Pastikan milik EO — ON DELETE CASCADE di FK akan hapus transaksi & penugasan
    $stmt = $mysqli->prepare(
        'DELETE FROM events WHERE id = ? AND eo_id = ?'
    );
    $stmt->bind_param('ii', $eventId, $eoId);
    $stmt->execute();
    $affected = $mysqli->affected_rows;
    $stmt->close();

    if ($affected === 0) {
       $_fail('Event tidak ditemukan atau sudah dihapus.',
              BASE_URL . '/admin/events/index.php');
    }

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'deleted', 'events', $eventId,
        'Menghapus event: ' . ($snapEvent['nama_event'] ?? '#' . $eventId)
    );

   $_ok('Event berhasil dihapus.', BASE_URL . '/admin/events/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: LEPAS STAFF dari penugasan
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'lepas_staff') {
    $eventId = (int) ($_POST['event_id'] ?? 0);
    $staffId = (int) ($_POST['user_id']  ?? 0);

    if ($eventId <= 0 || $staffId <= 0) {
       $_fail('Data tidak valid.', BASE_URL . '/admin/events/index.php');
    }

    // eo_id di WHERE memastikan tidak bisa lepas staff EO lain
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

   $_ok('Penugasan staff berhasil dilepas.',
        BASE_URL . '/admin/events/detail.php?id=' . $eventId);
}

// Aksi tidak dikenal
$_fail('Aksi tidak dikenali.', BASE_URL . '/admin/events/index.php');