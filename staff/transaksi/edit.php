<?php
/**
 * EO Finance — Form edit transaksi oleh staff.
 *
 * Staff hanya bisa mengedit transaksi yang dia sendiri buat
 * DAN event-nya masih dalam penugasan aktif miliknya.
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

if ((int) ($_SESSION['first_login'] ?? 0) === 1) {
    $_SESSION['__flash']['warning'] = 'Silakan ganti password terlebih dahulu.';
    header('Location: ' . BASE_URL . '/staff/ganti-password.php');
    exit();
}

$userId = get_current_user_id();
$eoId   = get_current_eo_id();
$trxId  = (int) ($_GET['id'] ?? 0);

if ($trxId <= 0) {
    header('Location: ' . BASE_URL . '/staff/transaksi/index.php');
    exit();
}

// ── Validasi: transaksi milik staff ini DAN event dalam penugasannya ──────────
// Double guard: created_by = staff ini, dan event ada di penugasan staff ini.
// Mencegah staff A mengedit transaksi staff B meski eventnya sama.
$stmtTrx = $mysqli->prepare(
    "SELECT t.id, t.tipe, t.kategori, t.jumlah, t.status_pembayaran,
            t.tanggal_transaksi, t.deskripsi, t.bukti_file,
            t.event_id, t.vendor_id,
            e.nama_event, e.status AS event_status
     FROM transaksi t
     INNER JOIN events    e ON t.event_id = e.id
     INNER JOIN penugasan p ON p.event_id = t.event_id AND p.eo_id = t.eo_id
     WHERE t.id         = ?
       AND t.created_by = ?
       AND t.eo_id      = ?
       AND p.user_id    = ?
     LIMIT 1"
);
$stmtTrx->bind_param('iiii', $trxId, $userId, $eoId, $userId);
$stmtTrx->execute();
$trx = $stmtTrx->get_result()->fetch_assoc();
$stmtTrx->close();

if ($trx === null) {
    $_SESSION['__flash']['error'] =
        'Transaksi tidak ditemukan atau kamu tidak memiliki akses untuk mengeditnya.';
    header('Location: ' . BASE_URL . '/staff/transaksi/index.php');
    exit();
}

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);
$old = $_SESSION['__old_input'] ?? [];
unset($_SESSION['__old_input']);

$v = fn(string $key) => htmlspecialchars(
    $old[$key] ?? $trx[$key] ?? '',
    ENT_QUOTES, 'UTF-8'
);

// ── Vendor milik EO ───────────────────────────────────────────────────────────
$stmtVn = $mysqli->prepare(
    'SELECT id, nama, kategori_layanan FROM vendor WHERE eo_id = ? ORDER BY nama ASC'
);
$stmtVn->bind_param('i', $eoId);
$stmtVn->execute();
$daftarVendor = $stmtVn->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtVn->close();

$selectedTipe   = $old['tipe']      ?? $trx['tipe'];
$selectedVendor = $old['vendor_id'] ?? $trx['vendor_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Edit Transaksi';
$page_type = 'staff';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-staff.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/staff/transaksi/index.php"
             class="breadcrumb__item">Transaksi</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">Edit</span>
        </nav>
        <h1 class="page-title">Edit Transaksi</h1>
        <p class="page-subtitle">
          <?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?>
          ·
          <span class="badge badge--<?= $trx['tipe'] ?>" style="vertical-align:middle">
            <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
          </span>
        </p>
      </div>
    </div>

    <?php foreach ($flashMessage as $type => $pesan) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
         role="alert">
      <span class="alert__text">
        <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>
    <?php endforeach; ?>

    <div class="form-layout">
      <div class="trx-form-card">
        <h2 class="trx-form-card__title">Detail Transaksi</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/staff/proses-transaksi.php"
              enctype="multipart/form-data"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" value="<?= $trxId ?>">

          <!-- Event — readonly: staff tidak bisa pindah event -->
          <div class="form-group">
            <label class="form-label">Event</label>
            <input type="text" class="form-input form-input--readonly"
                   value="<?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?>"
                   readonly>
            <span class="form-readonly-note">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
                <path d="M12 8v4M12 16h.01" stroke="currentColor"
                      stroke-width="1.8" stroke-linecap="round"/>
              </svg>
              Event tidak bisa diubah setelah transaksi dicatat.
            </span>
          </div>

          <!-- Tipe toggle -->
          <div class="form-group">
            <label class="form-label">
              Tipe <span class="form-label__required">*</span>
            </label>
            <div class="tipe-toggle">
              <label class="tipe-toggle__btn tipe-toggle__btn--pemasukan
                            <?= $selectedTipe === 'pemasukan' ? 'active' : '' ?>">
                <input type="radio" name="tipe" value="pemasukan"
                       <?= $selectedTipe === 'pemasukan' ? 'checked' : '' ?>>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Pemasukan
              </label>
              <label class="tipe-toggle__btn tipe-toggle__btn--pengeluaran
                            <?= $selectedTipe === 'pengeluaran' ? 'active' : '' ?>">
                <input type="radio" name="tipe" value="pengeluaran"
                       <?= $selectedTipe === 'pengeluaran' ? 'checked' : '' ?>>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Pengeluaran
              </label>
            </div>
          </div>

          <!-- Kategori & Jumlah -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="kategori">
                Kategori <span class="form-label__required">*</span>
              </label>
              <input type="text" id="kategori" name="kategori"
                     class="form-input"
                     value="<?= $v('kategori') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="jumlah">
                Jumlah (Rp) <span class="form-label__required">*</span>
              </label>
              <input type="number" id="jumlah" name="jumlah"
                     class="form-input"
                     min="1" step="1000"
                     value="<?= $v('jumlah') ?>" required>
            </div>
          </div>

          <!-- Tanggal -->
          <div class="form-group">
            <label class="form-label" for="tanggal_transaksi">
              Tanggal <span class="form-label__required">*</span>
            </label>
            <input type="date" id="tanggal_transaksi" name="tanggal_transaksi"
                   class="form-input"
                   value="<?= $v('tanggal_transaksi') ?>" required>
          </div>

          <!-- Vendor -->
          <?php if (!empty($daftarVendor)) : ?>
          <div class="form-group">
            <label class="form-label" for="vendor_id">Vendor</label>
            <select id="vendor_id" name="vendor_id" class="form-input filter-select">
              <option value="">— Tidak ada vendor —</option>
              <?php foreach ($daftarVendor as $vn) : ?>
              <option value="<?= (int)$vn['id'] ?>"
                      <?= (string)$selectedVendor === (string)$vn['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($vn['nama'], ENT_QUOTES, 'UTF-8') ?>
                <?= $vn['kategori_layanan']
                    ? ' (' . htmlspecialchars($vn['kategori_layanan'], ENT_QUOTES, 'UTF-8') . ')'
                    : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>

          <!-- Status Pembayaran (piutang/hutang) -->
          <div class="form-group">
            <label class="form-label" for="status_pembayaran">Status Pembayaran</label>
            <select id="status_pembayaran" name="status_pembayaran" class="form-input filter-select">
              <option value="lunas" <?= $v('status_pembayaran') !== 'belum_lunas' ? 'selected' : '' ?>>
                Lunas — uang sudah diterima/dibayar penuh
              </option>
              <option value="belum_lunas" <?= $v('status_pembayaran') === 'belum_lunas' ? 'selected' : '' ?>>
                Belum Lunas — masih ada sisa (akan masuk daftar Piutang/Hutang)
              </option>
            </select>
            <p class="form-hint">
              Pilih "Belum Lunas" jika ini tagihan ke klien yang belum dibayar penuh (piutang),
              atau pembayaran ke vendor/pihak lain yang belum dilunasi (hutang).
            </p>
          </div>

          <!-- Deskripsi -->
          <div class="form-group">
            <label class="form-label" for="deskripsi">Deskripsi</label>
            <textarea id="deskripsi" name="deskripsi"
                      class="form-input form-input--textarea"
                      rows="3"><?= $v('deskripsi') ?></textarea>
          </div>

          <!-- Bukti file -->
          <div class="form-group">
            <label class="form-label">Bukti Pembayaran</label>
            <?php if ($trx['bukti_file']) : ?>
            <div class="upload-preview" style="margin-bottom:8px">
              <span class="upload-preview__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                        stroke="currentColor" stroke-width="1.8"/>
                  <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="1.8"/>
                </svg>
              </span>
              <span class="upload-preview__name">
                <?= htmlspecialchars($trx['bukti_file'] ?? 'bukti-' . $trx['id'], ENT_QUOTES, 'UTF-8') ?>
              </span>
              <a href="<?= BASE_URL . '/serve-bukti.php?id=' . (int)$trx['id'] ?>"
                 target="_blank" rel="noopener"
                 style="font-size:12px;color:var(--success)">Lihat</a>
            </div>
            <label style="font-size:13px;color:var(--text-secondary);
                          display:flex;align-items:center;gap:6px;margin-bottom:8px">
              <input type="checkbox" name="hapus_bukti" value="1">
              Hapus bukti yang ada
            </label>
            <?php endif; ?>
            <div class="upload-area" style="position:relative">
              <span class="upload-area__icon" aria-hidden="true">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <polyline points="17 8 12 3 7 8" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round"/>
                  <line x1="12" y1="3" x2="12" y2="15"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </span>
              <span class="upload-area__text">
                <?= $trx['bukti_file'] ? 'Ganti dengan file baru' : 'Klik untuk pilih file' ?>
              </span>
              <span class="upload-area__hint">
                JPG, PNG, WEBP, atau PDF · Maks <?= MAX_UPLOAD_SIZE_BYTES / 1024 / 1024 ?>MB
              </span>
              <input type="file" name="bukti_file"
                     accept=".jpg,.jpeg,.png,.webp,.pdf"
                     style="position:absolute;inset:0;opacity:0;cursor:pointer">
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Simpan Perubahan</button>
            <a href="<?= BASE_URL ?>/staff/transaksi/index.php"
               class="btn btn--secondary">Batal</a>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
      </div>

      <div class="form-sidebar-card">
        <p class="form-sidebar-card__title">Info</p>
        <div class="form-sidebar-tip">
          Kamu hanya bisa mengedit transaksi yang kamu catat sendiri.
        </div>
        <div class="form-sidebar-tip">
          Event tidak bisa diubah setelah transaksi dicatat. Jika event salah,
          hapus transaksi ini dan buat yang baru.
        </div>
      </div>
    </div>

  </div>
</div>

<?php
$page_type = 'staff';
require_once __DIR__ . '/../../includes/footer.php';
?>