<?php
/**
 * EO Finance — Form tambah event baru.
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

// ── Flash & old input ─────────────────────────────────────────────────────────
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);
$old = $_SESSION['__old_input'] ?? [];
unset($_SESSION['__old_input']);

// ── Dropdown: daftar klien milik EO ini ───────────────────────────────────────
$stmtKlien = $mysqli->prepare(
    'SELECT id, nama FROM klien WHERE eo_id = ? AND 1=1 ORDER BY nama ASC'
);
$stmtKlien->bind_param('i', $eoId);
$stmtKlien->execute();
$daftarKlien = $stmtKlien->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtKlien->close();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Buat Event';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/admin/events/index.php"
             class="breadcrumb__item">Event</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">Buat Event</span>
        </nav>
        <h1 class="page-title">Buat Event Baru</h1>
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
      <div class="form-card">
        <h2 class="form-card__title">Detail Event</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-event.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="tambah">

          <!-- Nama event -->
          <div class="form-group">
            <label class="form-label" for="nama_event">
              Nama Event <span class="form-label__required">*</span>
            </label>
            <input type="text" id="nama_event" name="nama_event"
                   class="form-input"
                   placeholder="Contoh: Wedding Budi & Ani"
                   value="<?= htmlspecialchars($old['nama_event'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required>
          </div>

          <!-- Tanggal -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="tanggal_mulai">
                Tanggal Mulai <span class="form-label__required">*</span>
              </label>
              <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                     class="form-input"
                     value="<?= htmlspecialchars($old['tanggal_mulai'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required>
            </div>
            <div class="form-group">
              <label class="form-label" for="tanggal_selesai">
                Tanggal Selesai <span class="form-label__required">*</span>
              </label>
              <input type="date" id="tanggal_selesai" name="tanggal_selesai"
                     class="form-input"
                     value="<?= htmlspecialchars($old['tanggal_selesai'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required>
            </div>
          </div>

          <!-- Klien & Status -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="klien_id">Klien</label>
              <select id="klien_id" name="klien_id" class="form-input filter-select">
                <option value="">— Tanpa klien —</option>
                <?php foreach ($daftarKlien as $kl) : ?>
                <option value="<?= (int)$kl['id'] ?>"
                        <?= ($old['klien_id'] ?? '') == $kl['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($kl['nama'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="status">
                Status <span class="form-label__required">*</span>
              </label>
              <select id="status" name="status" class="form-input filter-select" required>
                <?php
                $statuses = ['draft' => 'Draft', 'aktif' => 'Aktif',
                             'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
                foreach ($statuses as $val => $lbl) :
                ?>
                <option value="<?= $val ?>"
                        <?= ($old['status'] ?? 'draft') === $val ? 'selected' : '' ?>>
                  <?= $lbl ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Lokasi & Anggaran -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="lokasi">Lokasi</label>
              <input type="text" id="lokasi" name="lokasi"
                     class="form-input"
                     placeholder="Venue / lokasi acara"
                     value="<?= htmlspecialchars($old['lokasi'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="anggaran">
                Anggaran (Rp) <span class="form-label__required">*</span>
              </label>
              <input type="number" id="anggaran" name="anggaran"
                     class="form-input"
                     placeholder="0"
                     min="0" step="1000"
                     value="<?= htmlspecialchars($old['anggaran'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required>
            </div>
          </div>

          <!-- Deskripsi -->
          <div class="form-group">
            <label class="form-label" for="deskripsi">Deskripsi</label>
            <textarea id="deskripsi" name="deskripsi"
                      class="form-input form-input--textarea"
                      rows="4"
                      placeholder="Catatan atau deskripsi tambahan tentang event ini..."
                      ><?= htmlspecialchars($old['deskripsi'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Simpan Event</button>
            <a href="<?= BASE_URL ?>/admin/events/index.php"
               class="btn btn--secondary">Batal</a>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
      </div>

      <!-- Sidebar info -->
      <div class="form-sidebar-card">
        <p class="form-sidebar-card__title">Panduan Event</p>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="4" width="18" height="18" rx="2"
                    stroke="var(--primary)" stroke-width="1.8"/>
              <path d="M16 2v4M8 2v4M3 10h18"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <span>Isi <strong>Tanggal Mulai</strong> dan <strong>Tanggal Selesai</strong>
          dengan benar. Event satu hari pun tetap perlu keduanya diisi sama.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round"/>
            </svg>
          </span>
          <span>Status <strong>Draft</strong> berarti event belum aktif.
          Ganti ke <strong>Aktif</strong> saat sudah berjalan, dan
          <strong>Selesai</strong> setelah event berakhir.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <line x1="12" y1="1" x2="12" y2="23"
                    stroke="var(--success)" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"
                    stroke="var(--success)" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <span><strong>Anggaran</strong> adalah total biaya yang dialokasikan.
          Sistem akan menampilkan sisa anggaran secara otomatis berdasarkan
          transaksi yang dicatat.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
              <circle cx="12" cy="7" r="4"
                      stroke="var(--primary)" stroke-width="1.8"/>
            </svg>
          </span>
          <span><strong>Klien</strong> bersifat opsional. Tambah klien baru di menu
          <a href="<?= BASE_URL ?>/admin/klien/tambah.php"
             style="color:var(--primary);font-weight:500">Klien</a>
          jika belum terdaftar.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"
                    stroke="var(--primary)" stroke-width="1.8"/>
              <circle cx="12" cy="10" r="3"
                      stroke="var(--primary)" stroke-width="1.8"/>
            </svg>
          </span>
          <span>Isi <strong>Lokasi</strong> dengan nama venue atau alamat lengkap
          agar mudah direferensikan saat koordinasi tim.</span>
        </div>
      </div>
    </div>

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>