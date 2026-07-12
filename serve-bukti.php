<?php
/**
 * EO Finance — Serve bukti transaksi dari database.
 *
 * File ini membaca binary data bukti pembayaran yang tersimpan di DB
 * dan mengirimkannya langsung ke browser dengan Content-Type yang sesuai.
 *
 * Akses: /serve-bukti.php?id={transaksi_id}
 * Keamanan:
 *   - User harus login.
 *   - Admin hanya bisa akses bukti milik EO-nya sendiri.
 *   - Staff hanya bisa akses bukti dari event yang ditugaskan kepadanya.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

$trxId = (int) ($_GET['id'] ?? 0);

if ($trxId <= 0) {
    http_response_code(400);
    exit('ID tidak valid.');
}

$eoId   = get_current_eo_id();
$userId = get_current_user_id();
$role   = get_current_role();

// ── Query berbeda untuk admin vs staff ───────────────────────────────────────
if ($role === 'admin_eo') {
    // Admin: bisa akses semua transaksi milik EO-nya
    $stmt = $mysqli->prepare(
        'SELECT bukti_data, bukti_mime, bukti_file
         FROM transaksi
         WHERE id = ? AND eo_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('ii', $trxId, $eoId);
} else {
    // Staff: hanya bisa akses transaksi dari event yang ia ditugaskan
    $stmt = $mysqli->prepare(
        'SELECT t.bukti_data, t.bukti_mime, t.bukti_file
         FROM transaksi t
         INNER JOIN penugasan p ON p.event_id = t.event_id AND p.eo_id = t.eo_id
         WHERE t.id = ? AND t.eo_id = ? AND p.user_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('iii', $trxId, $eoId, $userId);
}

$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($row === null || $row['bukti_data'] === null) {
    http_response_code(404);
    exit('Bukti tidak ditemukan.');
}

$mime     = $row['bukti_mime'] ?? 'application/octet-stream';
$filename = $row['bukti_file'] ?? ('bukti-' . $trxId);

// Sanitasi MIME: hanya izinkan tipe yang valid
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(415);
    exit('Tipe file tidak dikenali.');
}

// ── Kirim file ke browser ────────────────────────────────────────────────────
// PDF dibuka inline di browser, gambar langsung ditampilkan
$disposition = ($mime === 'application/pdf') ? 'inline' : 'inline';
$safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

header('Content-Type: ' . $mime);
header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"');
header('Content-Length: ' . strlen($row['bukti_data']));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

echo $row['bukti_data'];
exit();