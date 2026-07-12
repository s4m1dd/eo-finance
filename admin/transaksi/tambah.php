<?php
/**
 * EO Finance — Form tambah transaksi.
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role('admin_eo');

$eoId   = get_current_eo_id();
$userId = get_current_user_id();

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);
$old = $_SESSION['__old_input'] ?? [];
unset($_SESSION['__old_input']);

// Pre-fill event_id dari URL (misal dari tombol "Tambah Transaksi" di detail event)
$preEventId = (int) ($_GET['event_id'] ?? $old['event_id'] ?? 0);

// ── Dropdown events aktif milik EO ────────────────────────────────────────────
$stmtEv = $mysqli->prepare(
    "SELECT id, nama_event FROM events
     WHERE eo_id = ? AND status IN ('draft','aktif')
     ORDER BY tanggal_mulai DESC"
);
$stmtEv->bind_param('i', $eoId);
$stmtEv->execute();
$daftarEvents = $stmtEv->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEv->close();

// ── Dropdown vendor milik EO ──────────────────────────────────────────────────
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

$pageTitle = 'Tambah Transaksi';
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
          <span class="breadcrumb__item breadcrumb__item--active">Tambah</span>
        </nav>
        <h1 class="page-title">Tambah Transaksi</h1>
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

        <!-- Pakai template (isi otomatis field di bawah) -->
        <div class="form-group" style="margin-bottom:var(--space-4);
             padding:var(--space-4);background:var(--bg-light);border-radius:var(--radius-md)">
          <label class="form-label" for="pilih_template">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"
                 style="vertical-align:-2px;margin-right:4px">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                    stroke="currentColor" stroke-width="1.8"/>
              <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="1.8"/>
              <line x1="8" y1="13" x2="16" y2="13" stroke="currentColor" stroke-width="1.8"/>
              <line x1="8" y1="17" x2="12" y2="17" stroke="currentColor" stroke-width="1.8"/>
            </svg>
            Pakai Template
          </label>
          <select id="pilih_template" class="form-input filter-select">
            <option value="">— Pilih template untuk isi otomatis —</option>
          </select>
          <p class="form-hint">Pilih template untuk mengisi tipe, kategori, dan estimasi secara otomatis. Kamu tetap bisa mengubahnya.</p>
        </div>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-transaksi.php"
              enctype="multipart/form-data"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="tambah">

          <!-- Tipe -->
          <div class="form-group">
            <label class="form-label">
              Tipe Transaksi <span class="form-label__required">*</span>
            </label>
            <div class="tipe-toggle">
              <label class="tipe-toggle__btn tipe-toggle__btn--pemasukan
                            <?= ($old['tipe'] ?? '') === 'pemasukan' ? 'active' : '' ?>">
                <input type="radio" name="tipe" value="pemasukan"
                       <?= ($old['tipe'] ?? 'pemasukan') === 'pemasukan' ? 'checked' : '' ?>>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 19V5M5 12l7-7 7 7"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round"/>
                </svg>
                Pemasukan
              </label>
              <label class="tipe-toggle__btn tipe-toggle__btn--pengeluaran
                            <?= ($old['tipe'] ?? '') === 'pengeluaran' ? 'active' : '' ?>">
                <input type="radio" name="tipe" value="pengeluaran"
                       <?= ($old['tipe'] ?? '') === 'pengeluaran' ? 'checked' : '' ?>>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <path d="M12 5v14M5 12l7 7 7-7"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round"/>
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
                <option value="">— Pilih event —</option>
                <?php foreach ($daftarEvents as $ev) : ?>
                <option value="<?= (int)$ev['id'] ?>"
                        <?= ((int)($old['event_id'] ?? $preEventId)) === (int)$ev['id']
                            ? 'selected' : '' ?>>
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
                     placeholder="Contoh: Tiket, Sewa, Honor"
                     value="<?= htmlspecialchars($old['kategori'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required>
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
                     placeholder="0"
                     min="1" step="1000"
                     value="<?= htmlspecialchars($old['jumlah'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required>
            </div>
            <div class="form-group">
              <label class="form-label" for="tanggal_transaksi">
                Tanggal <span class="form-label__required">*</span>
              </label>
              <input type="date" id="tanggal_transaksi" name="tanggal_transaksi"
                     class="form-input"
                     value="<?= htmlspecialchars(
                                 $old['tanggal_transaksi'] ?? date('Y-m-d'),
                                 ENT_QUOTES, 'UTF-8'
                             ) ?>"
                     required>
            </div>
          </div>

          <!-- Vendor (opsional) -->
          <div class="form-group">
            <label class="form-label" for="vendor_id">Vendor</label>
            <select id="vendor_id" name="vendor_id" class="form-input filter-select">
              <option value="">— Tidak ada vendor —</option>
              <?php foreach ($daftarVendor as $vn) : ?>
              <option value="<?= (int)$vn['id'] ?>"
                      <?= ((int)($old['vendor_id'] ?? 0)) === (int)$vn['id'] ? 'selected' : '' ?>>
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
              <option value="lunas" <?= ($old['status_pembayaran'] ?? 'lunas') === 'lunas' ? 'selected' : '' ?>>
                Lunas — uang sudah diterima/dibayar penuh
              </option>
              <option value="belum_lunas" <?= ($old['status_pembayaran'] ?? '') === 'belum_lunas' ? 'selected' : '' ?>>
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
                      rows="3"
                      placeholder="Keterangan tambahan (opsional)"
                      ><?= htmlspecialchars($old['deskripsi'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <!-- Upload bukti -->
          <div class="form-group">
            <label class="form-label" for="bukti_file">Bukti Pembayaran</label>
            <label for="bukti_file" class="upload-area" id="upload-label">
              <span class="upload-area__icon" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none">
                  <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <polyline points="17 8 12 3 7 8"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <line x1="12" y1="3" x2="12" y2="15"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </span>
              <span class="upload-area__text" id="upload-text">Klik untuk pilih file</span>
              <span class="upload-area__hint">
                JPG, PNG, WEBP, atau PDF · Maks <?= MAX_UPLOAD_SIZE_BYTES / 1024 / 1024 ?>MB
              </span>
            </label>
            <input type="file" id="bukti_file" name="bukti_file"
                   accept=".jpg,.jpeg,.png,.webp,.pdf" style="display:none">
            <!-- Info file yang dipilih -->
            <div id="file-info" style="display:none;margin-top:8px;padding:8px 12px;
                 background:var(--primary-faint);border:1.5px solid rgba(0,113,227,.18);
                 border-radius:var(--radius-md);align-items:center;gap:8px">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                   style="flex-shrink:0;color:var(--primary)" aria-hidden="true">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                      stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <polyline points="14 2 14 8 20 8"
                          stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
              <span id="file-name" style="font-size:13px;font-weight:500;
                    color:var(--text-primary);min-width:0;overflow:hidden;
                    text-overflow:ellipsis;white-space:nowrap;flex:1"></span>
              <span id="file-size" style="font-size:12px;color:var(--text-secondary);
                    flex-shrink:0"></span>
              <button type="button" id="file-remove"
                      style="margin-left:4px;background:none;border:none;cursor:pointer;
                             padding:2px;color:var(--text-tertiary);flex-shrink:0;line-height:1"
                      title="Hapus pilihan" aria-label="Hapus file yang dipilih">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M18 6L6 18M6 6l12 12" stroke="currentColor"
                        stroke-width="2" stroke-linecap="round"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Simpan Transaksi</button>
            <a href="<?= BASE_URL ?>/admin/transaksi/index.php"
               class="btn btn--secondary">Batal</a>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
      </div>

      <div class="form-sidebar-card">
        <p class="form-sidebar-card__title">Panduan Transaksi</p>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M12 19V5M5 12l7-7 7 7" stroke="var(--success)"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span>Pilih <strong>Pemasukan</strong> untuk dana masuk seperti
          pembayaran klien atau sponsor.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M12 5v14M5 12l7 7 7-7" stroke="var(--danger)"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span>Pilih <strong>Pengeluaran</strong> untuk dana keluar seperti
          bayar vendor, sewa tempat, atau operasional.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="12" r="10" stroke="var(--primary)"
                      stroke-width="1.8"/>
              <path d="M12 8v4l3 3" stroke="var(--primary)"
                    stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <span>Isi <strong>Kategori</strong> dengan jenis transaksi,
          misalnya: Tiket, Sewa Gedung, Honor Artis, Konsumsi.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
              <polyline points="17 8 12 3 7 8"
                        stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
              <line x1="12" y1="3" x2="12" y2="15"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <span>Unggah <strong>bukti pembayaran</strong> (foto struk, transfer,
          atau invoice PDF) untuk keperluan audit dan laporan keuangan.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
              <circle cx="9" cy="7" r="4"
                      stroke="var(--primary)" stroke-width="1.8"/>
              <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <span>Pilih <strong>Vendor</strong> jika transaksi terkait supplier
          tertentu agar mudah dilacak di laporan per vendor.</span>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
(function () {
  // ── Sinkronisasi class .active pada tipe-toggle ──────────────────────────
  var btns = document.querySelectorAll('.tipe-toggle__btn');
  function syncTipe() {
    btns.forEach(function (btn) {
      var radio = btn.querySelector('input[type="radio"]');
      btn.classList.toggle('active', radio && radio.checked);
    });
  }
  btns.forEach(function (btn) { btn.addEventListener('click', syncTipe); });
  syncTipe();

  // ── Upload bukti: tampilkan nama & ukuran file yang dipilih ──────────────
  var inputFile  = document.getElementById('bukti_file');
  var uploadText = document.getElementById('upload-text');
  var fileInfo   = document.getElementById('file-info');
  var fileName   = document.getElementById('file-name');
  var fileSize   = document.getElementById('file-size');
  var fileRemove = document.getElementById('file-remove');

  function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function showFileInfo(file) {
    uploadText.textContent = 'Ganti file';
    fileName.textContent   = file.name;
    fileSize.textContent   = formatSize(file.size);
    fileInfo.style.display = 'flex';
  }

  function clearFileInfo() {
    inputFile.value        = '';
    uploadText.textContent = 'Klik untuk pilih file';
    fileInfo.style.display = 'none';
    fileName.textContent   = '';
    fileSize.textContent   = '';
  }

  if (inputFile) {
    inputFile.addEventListener('change', function () {
      if (this.files && this.files[0]) {
        showFileInfo(this.files[0]);
      } else {
        clearFileInfo();
      }
    });
  }

  if (fileRemove) {
    fileRemove.addEventListener('click', function (e) {
      e.preventDefault();
      clearFileInfo();
    });
  }
})();
</script>

<script>
// ── Template autofill ─────────────────────────────────────────────────────────
(async function () {
  const sel = document.getElementById('pilih_template');
  if (!sel) return;

  try {
    const res = await fetch(
      '<?= BASE_URL ?>/proses/admin/proses-expense-template.php?action=api_list'
    );
    const templates = await res.json();

    if (!templates.length) {
      sel.closest('.form-group').style.display = 'none';
      return;
    }

    // Kelompokkan per tipe
    const groups = { pengeluaran: [], pemasukan: [] };
    templates.forEach(t => groups[t.tipe]?.push(t));

    for (const [tipe, items] of Object.entries(groups)) {
      if (!items.length) continue;
      const og = document.createElement('optgroup');
      og.label = tipe === 'pengeluaran' ? 'Pengeluaran' : 'Pemasukan';
      items.forEach(t => {
        const opt = document.createElement('option');
        opt.value = JSON.stringify(t);
        opt.textContent = t.nama_template + (t.jumlah_estimasi
          ? ' — ' + new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',minimumFractionDigits:0}).format(t.jumlah_estimasi)
          : '');
        og.appendChild(opt);
      });
      sel.appendChild(og);
    }

    sel.addEventListener('change', function () {
      if (!this.value) return;
      const t = JSON.parse(this.value);

      // Tipe
      const radioTipe = document.querySelector(`input[name="tipe"][value="${t.tipe}"]`);
      if (radioTipe) { radioTipe.checked = true; radioTipe.dispatchEvent(new Event('change')); }

      // Kategori
      const kat = document.getElementById('kategori');
      if (kat) kat.value = t.kategori || '';

      // Jumlah estimasi
      const jml = document.getElementById('jumlah');
      if (jml && t.jumlah_estimasi) {
        jml.value = new Intl.NumberFormat('id-ID').format(t.jumlah_estimasi);
      }

      // Deskripsi
      const desk = document.getElementById('deskripsi');
      if (desk && t.deskripsi) desk.value = t.deskripsi;

      // Reset pilihan supaya bisa dipilih lagi kalau mau ganti
      this.value = '';
    });

  } catch (e) {
    console.warn('Template transaksi gagal dimuat:', e);
  }
})();
</script>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>