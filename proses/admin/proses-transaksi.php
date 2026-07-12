<?php
/**
 * EO Finance — Proses CRUD transaksi admin.
 *
 * Aksi via POST field `action`:
 *   tambah → INSERT transaksi baru + handle upload bukti
 *   edit   → UPDATE transaksi + opsional ganti/hapus bukti
 *   hapus  → DELETE transaksi + hapus file bukti dari disk
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

// ── Helpers ───────────────────────────────────────────────────────────────────
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

// ── POST guard ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/transaksi/index.php');
    exit();
}

// ── CSRF ──────────────────────────────────────────────────────────────────────
$_csrfPost    = $_POST['csrf_token']    ?? '';
$_csrfSession = $_SESSION['csrf_token'] ?? '';

if (empty($_csrfSession) || empty($_csrfPost) || !hash_equals($_csrfSession, $_csrfPost)) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
   $_fail('Token keamanan tidak valid.', BASE_URL . '/admin/transaksi/index.php');
}
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$action = $_POST['action'] ?? '';

// ════════════════════════════════════════════════════════════════════════════
// HELPER: Baca file bukti dari upload dan kembalikan sebagai array data BLOB.
// Bukti disimpan langsung di database — tidak ada file yang ditulis ke disk.
//
// Return:
//   ['data' => <binary>, 'mime' => <string>, 'nama' => <string>]
//     → ada file diupload, valid
//   null  → tidak ada file diupload (bukti_file kosong di form)
//   false → ada file tapi error (digunakan untuk trigger flash error)
// ════════════════════════════════════════════════════════════════════════════
$_handleUpload = static function (string $backUrl) use (&$_fail): array|null|false {
    if (empty($_FILES['bukti_file']['name'])) {
        return null;  // tidak ada file diupload
    }

    $file    = $_FILES['bukti_file'];
    $tmpPath = $file['tmp_name'];

    // Cek error upload dari PHP
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    // Validasi ukuran
    if ($file['size'] > MAX_UPLOAD_SIZE_BYTES) {
        $maxMb = MAX_UPLOAD_SIZE_BYTES / 1024 / 1024;
        $_SESSION['__old_input'] = $_POST;
        $_fail("File terlalu besar. Maksimal {$maxMb}MB.", $backUrl);
    }

    // Validasi MIME type dari konten file (bukan ekstensi — bisa dipalsukan)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tmpPath);

    if (!in_array($mimeType, ALLOWED_UPLOAD_MIME_TYPES, true)) {
        $_SESSION['__old_input'] = $_POST;
        $_fail('Tipe file tidak diizinkan. Gunakan JPG, PNG, WEBP, atau PDF.', $backUrl);
    }

    // Baca konten binary file dari temporary path
    $binaryData = file_get_contents($tmpPath);
    if ($binaryData === false) {
        return false;
    }

    // Nama file asli (hanya untuk tampilan, tidak disimpan ke disk)
    $ext          = match ($mimeType) {
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
        default           => 'bin',
    };
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeNama     = preg_replace('/[^a-zA-Z0-9_-]/', '_', mb_substr($originalName, 0, 80))
                    . '.' . $ext;

    return [
        'data' => $binaryData,
        'mime' => $mimeType,
        'nama' => $safeNama,
    ];
};

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TAMBAH
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'tambah') {
    $backUrl = BASE_URL . '/admin/transaksi/tambah.php';

    $tipe              = trim($_POST['tipe']              ?? '');
    $eventId           = (int) ($_POST['event_id']         ?? 0);
    $kategori          = trim($_POST['kategori']           ?? '');
    $jumlah            = trim($_POST['jumlah']             ?? '');
    $tanggalTransaksi  = trim($_POST['tanggal_transaksi']  ?? '');
    $vendorId          = (int) ($_POST['vendor_id']         ?? 0);
    $deskripsi         = trim($_POST['deskripsi']          ?? '');
    $statusPembayaran  = trim($_POST['status_pembayaran']  ?? 'lunas');
    if (!in_array($statusPembayaran, ['lunas', 'belum_lunas'], true)) {
        $statusPembayaran = 'lunas';
    }

    // ── Validasi ──────────────────────────────────────────────────────────────
    if (!in_array($tipe, ['pemasukan', 'pengeluaran'], true)) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Tipe transaksi tidak valid.', $backUrl);
    }
    if ($eventId <= 0) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Pilih event terlebih dahulu.', $backUrl);
    }
    if ($kategori === '') {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Kategori wajib diisi.', $backUrl);
    }
    if (!is_numeric($jumlah) || (float)$jumlah <= 0) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Jumlah harus berupa angka lebih dari 0.', $backUrl);
    }
    if ($tanggalTransaksi === '') {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Tanggal transaksi wajib diisi.', $backUrl);
    }

    // Validasi event milik EO ini
    $stmtCekEv = $mysqli->prepare(
        'SELECT id FROM events WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtCekEv->bind_param('ii', $eventId, $eoId);
    $stmtCekEv->execute();
    $stmtCekEv->store_result();
    if ($stmtCekEv->num_rows === 0) {
        $stmtCekEv->close();
       $_fail('Event tidak valid.', $backUrl);
    }
    $stmtCekEv->close();

    // Validasi vendor (jika diisi) milik EO ini
    $vendorIdDb = null;
    if ($vendorId > 0) {
        $stmtCekVn = $mysqli->prepare(
            'SELECT id FROM vendor WHERE id = ? AND eo_id = ? LIMIT 1'
        );
        $stmtCekVn->bind_param('ii', $vendorId, $eoId);
        $stmtCekVn->execute();
        $stmtCekVn->store_result();
        if ($stmtCekVn->num_rows > 0) {
            $vendorIdDb = $vendorId;
        }
        $stmtCekVn->close();
    }

    // ── Upload bukti ──────────────────────────────────────────────────────────
    $buktiResult = $_handleUpload($backUrl);
    if ($buktiResult === false) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Gagal membaca file bukti. Coba lagi.', $backUrl);
    }

    $jumlahDb   = round((float)$jumlah, 2);
    $deskripsiDb = $deskripsi !== '' ? $deskripsi : null;

    // Siapkan nilai kolom bukti — null jika tidak ada upload
    $buktiNama = $buktiResult ? $buktiResult['nama'] : null;
    $buktiMime = $buktiResult ? $buktiResult['mime'] : null;
    $buktiData = $buktiResult ? $buktiResult['data'] : null;

    try {
        // Transaksi yang dicatat langsung oleh admin dianggap sudah final —
        // otomatis berstatus 'approved' (admin = sumber kebenaran, tidak perlu
        // approval dari dirinya sendiri).
        $stmt = $mysqli->prepare("
            INSERT INTO transaksi
                (eo_id, event_id, vendor_id, tipe, status_approval, status_pembayaran,
                 kategori, jumlah,
                 deskripsi, bukti_file, bukti_mime, bukti_data,
                 tanggal_transaksi, created_by, approved_by, approved_at,
                 created_at, updated_at)
            VALUES (?, ?, ?, ?, 'approved', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
        ");
        $stmt->bind_param(
            'iiisssdsssssii',
            $eoId, $eventId, $vendorIdDb, $tipe, $statusPembayaran, $kategori,
            $jumlahDb, $deskripsiDb, $buktiNama, $buktiMime, $buktiData,
            $tanggalTransaksi, $userId, $userId
        );
        $stmt->execute();
        $newTrxId = $mysqli->insert_id;
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('[EO Finance] INSERT transaksi gagal: ' . $e->getMessage());
        $_SESSION['__old_input'] = $_POST;
       $_fail('Gagal menyimpan transaksi. Silakan coba lagi.', $backUrl);
    }

   catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'created', 'transaksi', $newTrxId,
        sprintf(
            'Mencatat transaksi %s sebesar %s (kategori: %s)',
            $tipe, format_rupiah($jumlahDb), $kategori
        )
    );

   $_ok('Transaksi berhasil dicatat.',
        BASE_URL . '/admin/transaksi/detail.php?id=' . $newTrxId);
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: EDIT
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'edit') {
    $trxId             = (int) ($_POST['id']               ?? 0);
    $tipe              = trim($_POST['tipe']               ?? '');
    $eventId           = (int) ($_POST['event_id']          ?? 0);
    $kategori          = trim($_POST['kategori']            ?? '');
    $jumlah            = trim($_POST['jumlah']              ?? '');
    $tanggalTransaksi  = trim($_POST['tanggal_transaksi']   ?? '');
    $vendorId          = (int) ($_POST['vendor_id']          ?? 0);
    $deskripsi         = trim($_POST['deskripsi']           ?? '');
    $hapusBukti        = isset($_POST['hapus_bukti']) && $_POST['hapus_bukti'] === '1';
    $statusPembayaran  = trim($_POST['status_pembayaran']   ?? 'lunas');
    if (!in_array($statusPembayaran, ['lunas', 'belum_lunas'], true)) {
        $statusPembayaran = 'lunas';
    }

    $backUrl = BASE_URL . '/admin/transaksi/edit.php?id=' . $trxId;

    if ($trxId <= 0) {
       $_fail('ID transaksi tidak valid.', BASE_URL . '/admin/transaksi/index.php');
    }

    // Ambil data lama untuk validasi kepemilikan & file lama
    $stmtOld = $mysqli->prepare(
        'SELECT eo_id, bukti_file, event_id FROM transaksi WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtOld->bind_param('ii', $trxId, $eoId);
    $stmtOld->execute();
    $trxLama = $stmtOld->get_result()->fetch_assoc();
    $stmtOld->close();

    // Pastikan transaksi ditemukan dan benar-benar milik EO ini
    if ($trxLama === null) {
       $_fail('Transaksi tidak ditemukan.', BASE_URL . '/admin/transaksi/index.php');
    }

    // Validasi field
    if (!in_array($tipe, ['pemasukan', 'pengeluaran'], true)) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Tipe transaksi tidak valid.', $backUrl);
    }
    if ($kategori === '') {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Kategori wajib diisi.', $backUrl);
    }
    if (!is_numeric($jumlah) || (float)$jumlah <= 0) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Jumlah harus berupa angka lebih dari 0.', $backUrl);
    }
    if ($tanggalTransaksi === '') {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Tanggal transaksi wajib diisi.', $backUrl);
    }

    // Validasi event (boleh ganti event dalam EO yang sama)
    if ($eventId <= 0) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Pilih event terlebih dahulu.', $backUrl);
    }
    $stmtCekEv = $mysqli->prepare(
        'SELECT id FROM events WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtCekEv->bind_param('ii', $eventId, $eoId);
    $stmtCekEv->execute();
    $stmtCekEv->store_result();
    if ($stmtCekEv->num_rows === 0) {
        $stmtCekEv->close();
       $_fail('Event tidak valid.', $backUrl);
    }
    $stmtCekEv->close();

    $vendorIdDb = null;
    if ($vendorId > 0) {
        $stmtCekVn = $mysqli->prepare(
            'SELECT id FROM vendor WHERE id = ? AND eo_id = ? LIMIT 1'
        );
        $stmtCekVn->bind_param('ii', $vendorId, $eoId);
        $stmtCekVn->execute();
        $stmtCekVn->store_result();
        if ($stmtCekVn->num_rows > 0) {
            $vendorIdDb = $vendorId;
        }
        $stmtCekVn->close();
    }

    // ── Tentukan nilai bukti baru ─────────────────────────────────────────────
    // Jika ada upload baru → ganti. Jika user centang hapus → null. Jika tidak → tetap.
    $uploadResult = $_handleUpload($backUrl);

    if ($uploadResult === false) {
        $_SESSION['__old_input'] = $_POST;
       $_fail('Gagal membaca file bukti.', $backUrl);
    }

    $jumlahDb    = round((float)$jumlah, 2);
    $deskripsiDb = $deskripsi !== '' ? $deskripsi : null;

    try {
        if ($uploadResult !== null) {
            // Ada file baru — simpan data baru ke DB, timpa yang lama
            $stmt = $mysqli->prepare("
                UPDATE transaksi
                SET tipe = ?, event_id = ?, vendor_id = ?, kategori = ?,
                    jumlah = ?, deskripsi = ?, status_pembayaran = ?,
                    bukti_file = ?, bukti_mime = ?, bukti_data = ?,
                    tanggal_transaksi = ?, updated_at = NOW(),
                    status_approval = 'approved', approved_by = ?, approved_at = NOW(),
                    catatan_approval = NULL
                WHERE id = ? AND eo_id = ?
            ");
            $stmt->bind_param(
                'siisdsssssiiii',
                $tipe, $eventId, $vendorIdDb, $kategori,
                $jumlahDb, $deskripsiDb, $statusPembayaran,
                $uploadResult['nama'], $uploadResult['mime'], $uploadResult['data'],
                $tanggalTransaksi, $userId, $trxId, $eoId
            );
        } elseif ($hapusBukti) {
            // User centang "hapus bukti" tanpa upload baru — set null
            $stmt = $mysqli->prepare("
                UPDATE transaksi
                SET tipe = ?, event_id = ?, vendor_id = ?, kategori = ?,
                    jumlah = ?, deskripsi = ?, status_pembayaran = ?,
                    bukti_file = NULL, bukti_mime = NULL, bukti_data = NULL,
                    tanggal_transaksi = ?, updated_at = NOW(),
                    status_approval = 'approved', approved_by = ?, approved_at = NOW(),
                    catatan_approval = NULL
                WHERE id = ? AND eo_id = ?
            ");
            $stmt->bind_param(
                'siisdsssiii',
                $tipe, $eventId, $vendorIdDb, $kategori,
                $jumlahDb, $deskripsiDb, $statusPembayaran,
                $tanggalTransaksi, $userId, $trxId, $eoId
            );
        } else {
            // Tidak ada perubahan bukti — update field lain saja
            $stmt = $mysqli->prepare("
                UPDATE transaksi
                SET tipe = ?, event_id = ?, vendor_id = ?, kategori = ?,
                    jumlah = ?, deskripsi = ?, status_pembayaran = ?,
                    tanggal_transaksi = ?, updated_at = NOW(),
                    status_approval = 'approved', approved_by = ?, approved_at = NOW(),
                    catatan_approval = NULL
                WHERE id = ? AND eo_id = ?
            ");
            $stmt->bind_param(
                'siisdsssiii',
                $tipe, $eventId, $vendorIdDb, $kategori,
                $jumlahDb, $deskripsiDb, $statusPembayaran,
                $tanggalTransaksi, $userId, $trxId, $eoId
            );
        }
        $stmt->execute();
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('[EO Finance] UPDATE transaksi gagal: ' . $e->getMessage());
        $_SESSION['__old_input'] = $_POST;
       $_fail('Gagal memperbarui transaksi. Silakan coba lagi.', $backUrl);
    }

   catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'updated', 'transaksi', $trxId,
        sprintf(
            'Mengubah transaksi #%d menjadi %s sebesar %s (kategori: %s)',
            $trxId, $tipe, format_rupiah($jumlahDb), $kategori
        )
    );

   $_ok('Transaksi berhasil diperbarui.',
        BASE_URL . '/admin/transaksi/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: HAPUS
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'hapus') {
    $trxId = (int) ($_POST['id'] ?? 0);

    if ($trxId <= 0) {
       $_fail('ID tidak valid.', BASE_URL . '/admin/transaksi/index.php');
    }

    // Validasi kepemilikan sebelum hapus (sekaligus ambil snapshot untuk audit log)
    $stmtCek = $mysqli->prepare(
        'SELECT id, tipe, kategori, jumlah FROM transaksi WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtCek->bind_param('ii', $trxId, $eoId);
    $stmtCek->execute();
    $trxLama = $stmtCek->get_result()->fetch_assoc();
    $stmtCek->close();
    if ($trxLama === null) {
       $_fail('Transaksi tidak ditemukan.',
              BASE_URL . '/admin/transaksi/index.php');
    }

    // Hapus baris dari DB — data BLOB (bukti_data) ikut terhapus otomatis
    $stmtDel = $mysqli->prepare(
        'DELETE FROM transaksi WHERE id = ? AND eo_id = ?'
    );
    $stmtDel->bind_param('ii', $trxId, $eoId);
    $stmtDel->execute();
    $stmtDel->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'deleted', 'transaksi', $trxId,
        sprintf(
            'Menghapus transaksi #%d: %s sebesar %s (kategori: %s)',
            $trxId, $trxLama['tipe'], format_rupiah($trxLama['jumlah']), $trxLama['kategori']
        ),
        $trxLama
    );

   $_ok('Transaksi berhasil dihapus.',
        BASE_URL . '/admin/transaksi/index.php');
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: APPROVE
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'approve') {
    $trxId = (int) ($_POST['id'] ?? 0);
    $backTo = $_POST['kembali_ke'] ?? (BASE_URL . '/admin/transaksi/approval.php');

    if ($trxId <= 0) {
       $_fail('ID tidak valid.', BASE_URL . '/admin/transaksi/approval.php');
    }

    // Pastikan transaksi milik EO ini dan masih berstatus pending
    $stmtCek = $mysqli->prepare(
        "SELECT id FROM transaksi WHERE id = ? AND eo_id = ? AND status_approval = 'pending' LIMIT 1"
    );
    $stmtCek->bind_param('ii', $trxId, $eoId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows === 0) {
        $stmtCek->close();
       $_fail('Transaksi tidak ditemukan atau sudah diproses sebelumnya.', $backTo);
    }
    $stmtCek->close();

    $stmtApprove = $mysqli->prepare(
        "UPDATE transaksi
         SET status_approval = 'approved', approved_by = ?, approved_at = NOW(),
             catatan_approval = NULL
         WHERE id = ? AND eo_id = ?"
    );
    $stmtApprove->bind_param('iii', $userId, $trxId, $eoId);
    $stmtApprove->execute();
    $stmtApprove->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'approved', 'transaksi', $trxId,
        sprintf('Menyetujui transaksi #%d', $trxId)
    );

    // Notif ke staff pembuat transaksi (jika admin bukan pembuat sendiri)
    $stmtPembuat = $mysqli->prepare(
        'SELECT created_by FROM transaksi WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtPembuat->bind_param('ii', $trxId, $eoId);
    $stmtPembuat->execute();
    $pembuatRow = $stmtPembuat->get_result()->fetch_assoc();
    $stmtPembuat->close();
    if ($pembuatRow && (int)$pembuatRow['created_by'] !== $userId) {
        kirim_notifikasi(
            $mysqli, $eoId, (int)$pembuatRow['created_by'],
            'transaksi_approved',
            'Transaksi Disetujui',
            sprintf('Transaksi #%d kamu telah disetujui oleh admin.', $trxId),
            '/admin/transaksi/detail.php?id=' . $trxId
        );
    }

   $_ok('Transaksi disetujui.', $backTo);
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: REJECT
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'reject') {
    $trxId   = (int) ($_POST['id'] ?? 0);
    $catatan = trim($_POST['catatan_approval'] ?? '');
    $backTo  = $_POST['kembali_ke'] ?? (BASE_URL . '/admin/transaksi/approval.php');

    if ($trxId <= 0) {
       $_fail('ID tidak valid.', BASE_URL . '/admin/transaksi/approval.php');
    }
    if ($catatan === '') {
       $_fail('Alasan penolakan wajib diisi.', $backTo);
    }

    $stmtCek = $mysqli->prepare(
        "SELECT id FROM transaksi WHERE id = ? AND eo_id = ? AND status_approval = 'pending' LIMIT 1"
    );
    $stmtCek->bind_param('ii', $trxId, $eoId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows === 0) {
        $stmtCek->close();
       $_fail('Transaksi tidak ditemukan atau sudah diproses sebelumnya.', $backTo);
    }
    $stmtCek->close();

    $stmtReject = $mysqli->prepare(
        "UPDATE transaksi
         SET status_approval = 'rejected', approved_by = ?, approved_at = NOW(),
             catatan_approval = ?
         WHERE id = ? AND eo_id = ?"
    );
    $stmtReject->bind_param('isii', $userId, $catatan, $trxId, $eoId);
    $stmtReject->execute();
    $stmtReject->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'rejected', 'transaksi', $trxId,
        sprintf('Menolak transaksi #%d: %s', $trxId, $catatan)
    );

    $stmtPembuat2 = $mysqli->prepare(
        'SELECT created_by FROM transaksi WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtPembuat2->bind_param('ii', $trxId, $eoId);
    $stmtPembuat2->execute();
    $pembuatRow2 = $stmtPembuat2->get_result()->fetch_assoc();
    $stmtPembuat2->close();
    if ($pembuatRow2 && (int)$pembuatRow2['created_by'] !== $userId) {
        kirim_notifikasi(
            $mysqli, $eoId, (int)$pembuatRow2['created_by'],
            'transaksi_rejected',
            'Transaksi Ditolak',
            sprintf('Transaksi #%d ditolak: %s', $trxId, $catatan),
            '/admin/transaksi/detail.php?id=' . $trxId
        );
    }

   $_ok('Transaksi ditolak.', $backTo);
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: TANDAI LUNAS (piutang/hutang)
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'tandai_lunas') {
    $trxId  = (int) ($_POST['id'] ?? 0);
    $backTo = $_POST['kembali_ke'] ?? (BASE_URL . '/admin/piutang-hutang/index.php');

    if ($trxId <= 0) {
       $_fail('ID tidak valid.', BASE_URL . '/admin/piutang-hutang/index.php');
    }

    $stmtCek = $mysqli->prepare(
        "SELECT id FROM transaksi WHERE id = ? AND eo_id = ? AND status_pembayaran = 'belum_lunas' LIMIT 1"
    );
    $stmtCek->bind_param('ii', $trxId, $eoId);
    $stmtCek->execute();
    $stmtCek->store_result();
    if ($stmtCek->num_rows === 0) {
        $stmtCek->close();
       $_fail('Transaksi tidak ditemukan atau sudah lunas sebelumnya.', $backTo);
    }
    $stmtCek->close();

    $stmtLunas = $mysqli->prepare(
        "UPDATE transaksi SET status_pembayaran = 'lunas', updated_at = NOW()
         WHERE id = ? AND eo_id = ?"
    );
    $stmtLunas->bind_param('ii', $trxId, $eoId);
    $stmtLunas->execute();
    $stmtLunas->close();

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Admin',
        'lunas', 'transaksi', $trxId,
        sprintf('Menandai transaksi #%d sebagai lunas', $trxId)
    );

   $_ok('Transaksi ditandai lunas.', $backTo);
}

// Aksi tidak dikenal
$_fail('Aksi tidak dikenali.', BASE_URL . '/admin/transaksi/index.php');