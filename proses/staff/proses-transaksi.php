<?php
/**
 * EO Finance — Proses CRUD transaksi oleh staff.
 *
 * Aksi via POST field `action`:
 *   tambah → INSERT transaksi baru (hanya event dalam penugasan staff)
 *   edit   → UPDATE transaksi (hanya jika staff adalah created_by-nya)
 *
 * Staff tidak bisa menghapus transaksi — hanya admin yang bisa.
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
require_role('staff_eo');

$userId = get_current_user_id();
$eoId   = get_current_eo_id();

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

$indexUrl = BASE_URL . '/staff/transaksi/index.php';

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

// ── Helper: baca file bukti dari upload ───────────────────────────────────────
// Return: ['data'=>binary,'mime'=>string,'nama'=>string] | null | false
$handleUpload = static function (string $backUrl) use ($fail): array|null|false {
    if (empty($_FILES['bukti_file']['name'])) {
        return null;
    }

    $file = $_FILES['bukti_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE_BYTES) {
        $maxMb = MAX_UPLOAD_SIZE_BYTES / 1024 / 1024;
        $_SESSION['__old_input'] = $_POST;
        $fail("File terlalu besar. Maksimal {$maxMb}MB.", $backUrl);
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, ALLOWED_UPLOAD_MIME_TYPES, true)) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Tipe file tidak diizinkan. Gunakan JPG, PNG, WEBP, atau PDF.', $backUrl);
    }

    $binaryData = file_get_contents($file['tmp_name']);
    if ($binaryData === false) {
        return false;
    }

    $ext = match ($mimeType) {
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
    $backUrl = BASE_URL . '/staff/transaksi/tambah.php';

    $tipe             = trim($_POST['tipe']              ?? '');
    $eventId          = (int) ($_POST['event_id']         ?? 0);
    $kategori         = trim($_POST['kategori']           ?? '');
    $jumlah           = trim($_POST['jumlah']             ?? '');
    $tanggalTransaksi = trim($_POST['tanggal_transaksi']  ?? '');
    $vendorId         = (int) ($_POST['vendor_id']         ?? 0);
    $deskripsi        = trim($_POST['deskripsi']          ?? '');
    $statusPembayaran = trim($_POST['status_pembayaran']  ?? 'lunas');
    if (!in_array($statusPembayaran, ['lunas', 'belum_lunas'], true)) {
        $statusPembayaran = 'lunas';
    }

    if (!in_array($tipe, ['pemasukan', 'pengeluaran'], true)) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Tipe transaksi tidak valid.', $backUrl);
    }
    if ($eventId <= 0) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Pilih event terlebih dahulu.', $backUrl);
    }
    if ($kategori === '') {
        $_SESSION['__old_input'] = $_POST;
        $fail('Kategori wajib diisi.', $backUrl);
    }
    if (!is_numeric($jumlah) || (float)$jumlah <= 0) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Jumlah harus berupa angka lebih dari 0.', $backUrl);
    }
    if ($tanggalTransaksi === '') {
        $_SESSION['__old_input'] = $_POST;
        $fail('Tanggal wajib diisi.', $backUrl);
    }

    // Validasi event dalam penugasan staff
    $stmtCekEv = $mysqli->prepare(
        "SELECT e.id FROM events e
         INNER JOIN penugasan p ON p.event_id = e.id AND p.eo_id = e.eo_id
         WHERE e.id = ? AND e.eo_id = ?
           AND p.user_id = ?
           AND e.status IN ('draft','aktif')
         LIMIT 1"
    );
    $stmtCekEv->bind_param('iii', $eventId, $eoId, $userId);
    $stmtCekEv->execute();
    $stmtCekEv->store_result();
    if ($stmtCekEv->num_rows === 0) {
        $stmtCekEv->close();
        $_SESSION['__old_input'] = $_POST;
        $fail('Event tidak valid atau kamu tidak memiliki akses ke event ini.', $backUrl);
    }
    $stmtCekEv->close();

    // Validasi vendor (opsional)
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

    $buktiResult = $handleUpload($backUrl);
    if ($buktiResult === false) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Gagal membaca file bukti. Coba lagi.', $backUrl);
    }

    $jumlahDb    = round((float)$jumlah, 2);
    $deskripsiDb = $deskripsi !== '' ? $deskripsi : null;

    try {
        if ($buktiResult !== null) {
            // ── Ada file bukti ────────────────────────────────────────────────
            // Simpan BLOB via send_long_data — cara yang benar untuk mediumblob/blob
            // di MySQLi PHP 8.
            //
            // PENTING: send_long_data(N, data) mengacu pada index placeholder
            // ke-N di SQL (0-based, dihitung dari SEMUA ? termasuk posisi BLOB).
            // bind_param dipanggil dengan type 'b' di posisi BLOB,
            // tapi TANPA variabel untuk posisi tersebut.
            //
            // Placeholder di SQL (13 total):
            //  0:eo_id  1:event_id  2:vendor_id  3:tipe  4:status_approval
            //  5:kategori  6:jumlah  7:deskripsi  8:bukti_file  9:bukti_mime
            //  10:bukti_data←BLOB  11:tanggal_transaksi  12:created_by
            $stmt = $mysqli->prepare("
                INSERT INTO transaksi
                    (eo_id, event_id, vendor_id, tipe, status_approval, status_pembayaran,
                     kategori, jumlah,
                     deskripsi, bukti_file, bukti_mime, bukti_data,
                     tanggal_transaksi, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            // bind_param: 13 placeholder, 13 variabel — jumlah HARUS sama persis.
            // PHP 8 tidak mendukung 'b' tanpa variabel; gunakan 's' untuk BLOB binary.
            $buktiData = $buktiResult['data'];
            $stmt->bind_param(
                'iiisssdsssssi',      // 13 karakter: i,i,i,s,s,s,d,s,s,s,s,s,i
                $eoId,                // 0: i
                $eventId,             // 1: i
                $vendorIdDb,          // 2: i (nullable)
                $tipe,                // 3: s
                $statusPembayaran,    // 4: s
                $kategori,            // 5: s
                $jumlahDb,            // 6: d
                $deskripsiDb,         // 7: s (nullable)
                $buktiResult['nama'], // 8: s
                $buktiResult['mime'], // 9: s
                $buktiData,           // 10: s (binary — bukti_data BLOB)
                $tanggalTransaksi,    // 11: s
                $userId               // 12: i
            );
            $stmt->execute();
            $stmt->close();

        } else {
            // ── Tanpa file bukti: simpan NULL langsung di SQL ─────────────────
            $stmt = $mysqli->prepare("
                INSERT INTO transaksi
                    (eo_id, event_id, vendor_id, tipe, status_approval, status_pembayaran,
                     kategori, jumlah,
                     deskripsi, bukti_file, bukti_mime, bukti_data,
                     tanggal_transaksi, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, NULL, NULL, NULL, ?, ?, NOW(), NOW())
            ");
            // 10 placeholder (bukti_file/mime/data hardcode NULL di SQL)
            $stmt->bind_param(
                'iiisssdssi',
                $eoId, $eventId, $vendorIdDb, $tipe, $statusPembayaran, $kategori,
                $jumlahDb, $deskripsiDb, $tanggalTransaksi, $userId
            );
            $stmt->execute();
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log('[EO Finance] INSERT transaksi staff gagal: ' . $e->getMessage());
        $_SESSION['__old_input'] = $_POST;
        $fail('Gagal menyimpan transaksi. Silakan coba lagi.', $backUrl);
    }

    $newTrxId = $mysqli->insert_id;

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Staff',
        'created', 'transaksi', $newTrxId,
        sprintf(
            'Staff mencatat transaksi %s sebesar %s (kategori: %s) — menunggu approval',
            $tipe, format_rupiah((float) str_replace(['.', ','], ['', '.'], $jumlah)), $kategori
        )
    );

    // Notifikasi ke semua admin bahwa ada transaksi baru menunggu approval
    kirim_notifikasi_ke_semua_admin(
        $mysqli, $eoId,
        'transaksi_baru',
        'Transaksi Baru Menunggu Approval',
        sprintf(
            '%s mencatat transaksi %s — perlu persetujuan kamu.',
            $_SESSION['nama'] ?? 'Staff', $tipe
        ),
        '/admin/transaksi/detail.php?id=' . $newTrxId
    );

    $ok('Transaksi berhasil dicatat.',
        BASE_URL . '/staff/transaksi/detail.php?id=' . $newTrxId);
}

// ════════════════════════════════════════════════════════════════════════════
// AKSI: EDIT
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'edit') {
    $trxId            = (int) ($_POST['id']               ?? 0);
    $tipe             = trim($_POST['tipe']               ?? '');
    $kategori         = trim($_POST['kategori']            ?? '');
    $jumlah           = trim($_POST['jumlah']              ?? '');
    $tanggalTransaksi = trim($_POST['tanggal_transaksi']   ?? '');
    $vendorId         = (int) ($_POST['vendor_id']          ?? 0);
    $deskripsi        = trim($_POST['deskripsi']           ?? '');
    $hapusBukti       = isset($_POST['hapus_bukti']) && $_POST['hapus_bukti'] === '1';
    $statusPembayaran = trim($_POST['status_pembayaran']   ?? 'lunas');
    if (!in_array($statusPembayaran, ['lunas', 'belum_lunas'], true)) {
        $statusPembayaran = 'lunas';
    }

    $backUrl = BASE_URL . '/staff/transaksi/edit.php?id=' . $trxId;

    if ($trxId <= 0) {
        $fail('ID transaksi tidak valid.', $indexUrl);
    }

    // Validasi kepemilikan transaksi
    $stmtOld = $mysqli->prepare(
        "SELECT t.bukti_file, t.event_id, t.status_approval
         FROM transaksi t
         INNER JOIN penugasan p ON p.event_id = t.event_id AND p.eo_id = t.eo_id
         WHERE t.id = ? AND t.created_by = ? AND t.eo_id = ? AND p.user_id = ?
         LIMIT 1"
    );
    $stmtOld->bind_param('iiii', $trxId, $userId, $eoId, $userId);
    $stmtOld->execute();
    $trxLama = $stmtOld->get_result()->fetch_assoc();
    $stmtOld->close();

    if ($trxLama === null) {
        $fail('Transaksi tidak ditemukan atau kamu tidak memiliki akses.', $indexUrl);
    }

    // Jika transaksi sebelumnya sudah disetujui/ditolak admin, perubahan apapun
    // harus diverifikasi ulang — status dikembalikan ke 'pending' di bawah.
    $statusSebelumnya = $trxLama['status_approval'];

    if (!in_array($tipe, ['pemasukan', 'pengeluaran'], true)) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Tipe tidak valid.', $backUrl);
    }
    if ($kategori === '') {
        $_SESSION['__old_input'] = $_POST;
        $fail('Kategori wajib diisi.', $backUrl);
    }
    if (!is_numeric($jumlah) || (float)$jumlah <= 0) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Jumlah harus berupa angka lebih dari 0.', $backUrl);
    }
    if ($tanggalTransaksi === '') {
        $_SESSION['__old_input'] = $_POST;
        $fail('Tanggal wajib diisi.', $backUrl);
    }

    // Validasi vendor
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

    $uploadResult = $handleUpload($backUrl);
    if ($uploadResult === false) {
        $_SESSION['__old_input'] = $_POST;
        $fail('Gagal membaca file bukti.', $backUrl);
    }

    $jumlahDb    = round((float)$jumlah, 2);
    $deskripsiDb = $deskripsi !== '' ? $deskripsi : null;

    try {
        if ($uploadResult !== null) {
            // ── Ada file baru: UPDATE dengan BLOB ─────────────────────────────
            //
            // Placeholder di SQL (12 total):
            //  0:tipe  1:kategori  2:jumlah  3:deskripsi
            //  4:bukti_file  5:bukti_mime  6:bukti_data←BLOB
            //  7:vendor_id  8:tanggal_transaksi  9:id  10:created_by  11:eo_id
            $stmt = $mysqli->prepare("
                UPDATE transaksi
                SET tipe = ?, kategori = ?, jumlah = ?, deskripsi = ?, status_pembayaran = ?,
                    bukti_file = ?, bukti_mime = ?, bukti_data = ?,
                    vendor_id = ?, tanggal_transaksi = ?, updated_at = NOW(),
                    status_approval = 'pending', approved_by = NULL,
                    approved_at = NULL, catatan_approval = NULL
                WHERE id = ? AND created_by = ? AND eo_id = ?
            ");

            // bind_param: 13 placeholder, 13 variabel — jumlah HARUS sama persis.
            // PHP 8 tidak mendukung 'b' tanpa variabel; gunakan 's' untuk BLOB binary.
            //
            // BUGFIX (Minggu 5): sebelumnya type string di sini adalah
            // 'ssdssssiisii' — posisi tanggal_transaksi dan id tertukar tipenya.
            // Karena posisi tanggal_transaksi dipaksa jadi 'i' (integer),
            // MySQLi men-cast tanggal_transaksi (string "2026-06-20") menjadi
            // integer 2026 sebelum dikirim ke MySQL — merusak data tanggal
            // transaksi setiap kali staff edit transaksi sambil upload bukti baru.
            $uploadData = $uploadResult['data'];
            $stmt->bind_param(
                'ssdsssssisiii',       // 13 karakter: s,s,d,s,s,s,s,s,i,s,i,i,i
                $tipe,                 // 0: s
                $kategori,             // 1: s
                $jumlahDb,             // 2: d
                $deskripsiDb,          // 3: s (nullable)
                $statusPembayaran,     // 4: s
                $uploadResult['nama'], // 5: s
                $uploadResult['mime'], // 6: s
                $uploadData,           // 7: s (binary — bukti_data BLOB)
                $vendorIdDb,           // 8: i (nullable)
                $tanggalTransaksi,     // 9: s
                $trxId,                // 10: i
                $userId,               // 11: i
                $eoId                  // 12: i
            );
            $stmt->execute();
            $stmt->close();

        } elseif ($hapusBukti) {
            // ── Hapus bukti tanpa upload baru ─────────────────────────────────
            $stmt = $mysqli->prepare("
                UPDATE transaksi
                SET tipe = ?, kategori = ?, jumlah = ?, deskripsi = ?, status_pembayaran = ?,
                    bukti_file = NULL, bukti_mime = NULL, bukti_data = NULL,
                    vendor_id = ?, tanggal_transaksi = ?, updated_at = NOW(),
                    status_approval = 'pending', approved_by = NULL,
                    approved_at = NULL, catatan_approval = NULL
                WHERE id = ? AND created_by = ? AND eo_id = ?
            ");
            $stmt->bind_param(
                'ssdssisiii',
                $tipe, $kategori, $jumlahDb, $deskripsiDb, $statusPembayaran,
                $vendorIdDb, $tanggalTransaksi, $trxId, $userId, $eoId
            );
            $stmt->execute();
            $stmt->close();

        } else {
            // ── Tidak ada perubahan bukti ──────────────────────────────────────
            $stmt = $mysqli->prepare("
                UPDATE transaksi
                SET tipe = ?, kategori = ?, jumlah = ?, deskripsi = ?, status_pembayaran = ?,
                    vendor_id = ?, tanggal_transaksi = ?, updated_at = NOW(),
                    status_approval = 'pending', approved_by = NULL,
                    approved_at = NULL, catatan_approval = NULL
                WHERE id = ? AND created_by = ? AND eo_id = ?
            ");
            $stmt->bind_param(
                'ssdssisiii',
                $tipe, $kategori, $jumlahDb, $deskripsiDb, $statusPembayaran,
                $vendorIdDb, $tanggalTransaksi, $trxId, $userId, $eoId
            );
            $stmt->execute();
            $stmt->close();
        }
    } catch (mysqli_sql_exception $e) {
        error_log('[EO Finance] UPDATE transaksi staff gagal: ' . $e->getMessage());
        $_SESSION['__old_input'] = $_POST;
        $fail('Gagal memperbarui transaksi. Silakan coba lagi.', $backUrl);
    }

    $pesanSukses = in_array($statusSebelumnya, ['approved', 'rejected'], true)
        ? 'Transaksi berhasil diperbarui. Status dikembalikan ke "Menunggu Persetujuan" karena ada perubahan data.'
        : 'Transaksi berhasil diperbarui.';

    catat_audit(
        $mysqli, $eoId, $userId, $_SESSION['nama'] ?? 'Staff',
        'updated', 'transaksi', $trxId,
        sprintf(
            'Staff mengubah transaksi #%d%s',
            $trxId,
            in_array($statusSebelumnya, ['approved', 'rejected'], true)
                ? ' — status kembali ke menunggu approval' : ''
        )
    );

    $ok($pesanSukses,
        BASE_URL . '/staff/events/detail.php?id=' . $trxLama['event_id']);
}

$fail('Aksi tidak dikenali.', $indexUrl);