<?php
/**
 * EO Finance — Form edit transaksi.
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

$eoId    = get_current_eo_id();
$trxId   = (int) ($_GET['id'] ?? 0);

if ($trxId <= 0) {
    header('Location: ' . BASE_URL . '/admin/transaksi/index.php');
    exit();
}

// ── Ambil data transaksi (validasi kepemilikan via eo_id) ─────────────────────
$stmt = $mysqli->prepare("
    SELECT t.id, t.eo_id, t.event_id, t.vendor_id, t.tipe, t.kategori,
           t.jumlah, t.deskripsi, t.bukti_file, t.status_pembayaran,
           t.tanggal_transaksi, t.created_by, t.created_at, t.updated_at,
           e.nama_event
    FROM transaksi t
    INNER JOIN events e ON t.event_id = e.id
    WHERE t.id = ? AND t.eo_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $trxId, $eoId);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($trx === null) {
    $_SESSION['__flash']['error'] = 'Transaksi tidak ditemukan.';
    header('Location: ' . BASE_URL . '/admin/transaksi/index.php');
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

// ── Dropdown data ─────────────────────────────────────────────────────────────
$stmtEv = $mysqli->prepare(
    "SELECT id, nama_event FROM events
     WHERE eo_id = ? ORDER BY tanggal_mulai DESC"
);
$stmtEv->bind_param('i', $eoId);
$stmtEv->execute();
$daftarEvents = $stmtEv->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEv->close();

$stmtVn = $mysqli->prepare(
    'SELECT id, nama, kategori_layanan FROM vendor WHERE eo_id = ? ORDER BY nama ASC'
);
$stmtVn->bind_param('i', $eoId);
$stmtVn->execute();
$daftarVendor = $stmtVn->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtVn->close();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$selectedTipe    = $old['tipe']      ?? $trx['tipe'];
$selectedEventId = $old['event_id']  ?? $trx['event_id'];
$selectedVendor  = $old['vendor_id'] ?? $trx['vendor_id'];

$pageTitle = 'Edit Transaksi';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/admin/transaksi/index.php"
             class="breadcrumb__item">Transaksi</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">Edit</span>
        </nav>
        <h1 class="page-title">Edit Transaksi</h1>
        <p class="page-subtitle">
          <?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?> ·
          <span class="badge badge--<?= $trx['tipe'] ?>" style="vertical-align:middle">
            <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
          </span>
        </p>
      </div>
    </div>

    <?php foreach ($flashMessage as $type => $pesan) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
      <span class="alert__text"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endforeach; ?>

    <div class="form-layout">
      <div class="form-card">
        <h2 class="form-card__title">Detail Transaksi</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-transaksi.php"
              enctype="multipart/form-data"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" value="<?= $trxId ?>">

          <!-- Tipe -->
          <div class="form-group">
            <label class="form-label">
              Tipe Transaksi <span class="form-label__required">*</span>
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

          <!-- Event & Kategori -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="event_id">
                Event <span class="form-label__required">*</span>
              </label>
              <select id="event_id" name="event_id"
                      class="form-input filter-select" required>
                <?php foreach ($daftarEvents as $ev) : ?>
                <option value="<?= (int)$ev['id'] ?>"
                        <?= (string)$selectedEventId === (string)$ev['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="kategori">
                Kategori <span class="form-label__required">*</span>
              </label>
              <input type="text" id="kategori" name="kategori"
                     class="form-input"
                     value="<?= $v('kategori') ?>" required>
            </div>
          </div>

          <!-- Jumlah & Tanggal -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="jumlah">
                Jumlah (Rp) <span class="form-label__required">*</span>
              </label>
              <input type="number" id="jumlah" name="jumlah"
                     class="form-input"
                     min="1" step="1000"
                     value="<?= $v('jumlah') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="tanggal_transaksi">
                Tanggal <span class="form-label__required">*</span>
              </label>
              <input type="date" id="tanggal_transaksi" name="tanggal_transaksi"
                     class="form-input"
                     value="<?= $v('tanggal_transaksi') ?>" required>
            </div>
          </div>

          <!-- Vendor -->
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
                 style="font-size:12px;color:var(--primary)">Lihat</a>
            </div>
            <label style="font-size:13px;color:var(--text-secondary)">
              <input type="checkbox" name="hapus_bukti" value="1">
              Hapus bukti yang ada
            </label>
            <div style="margin-top:8px">
            <?php endif; ?>
            <label for="bukti_file_input" class="upload-area">
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
              <span class="upload-area__text" id="upload-text">
                <?= $trx['bukti_file'] ? 'Ganti dengan file baru' : 'Klik untuk pilih file' ?>
              </span>
              <span class="upload-area__hint">
                JPG, PNG, WEBP, atau PDF · Maks <?= MAX_UPLOAD_SIZE_BYTES / 1024 / 1024 ?>MB
              </span>
            </label>
            <input type="file" name="bukti_file" id="bukti_file_input"
                   accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none"
                   onchange="document.getElementById('upload-text').textContent=this.files[0]?this.files[0].name:'Klik untuk pilih file'">
            <?php if ($trx['bukti_file']) : ?></div><?php endif; ?>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Simpan Perubahan</button>
            <a href="<?= BASE_URL ?>/admin/transaksi/index.php"
               class="btn btn--secondary">Batal</a>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
      </div>

      <!-- Danger zone -->
      <div class="danger-zone">
        <div>
          <p class="danger-zone__text">Hapus transaksi ini</p>
          <p class="danger-zone__desc">Tindakan ini tidak bisa dibatalkan.</p>
        </div>
        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-transaksi.php"
              onsubmit="return confirm('Yakin hapus transaksi ini?')">
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="hapus">
          <input type="hidden" name="id" value="<?= $trxId ?>">
          <button type="submit" class="btn btn--danger btn--sm">Hapus</button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>