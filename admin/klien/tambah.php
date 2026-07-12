<?php
/**
 * EO Finance — Form tambah klien.
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role('admin_eo');

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);
$old = $_SESSION['__old_input'] ?? [];
unset($_SESSION['__old_input']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Tambah Klien';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/admin/klien/index.php"
             class="breadcrumb__item">Klien</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">Tambah</span>
        </nav>
        <h1 class="page-title">Tambah Klien</h1>
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
        <h2 class="form-card__title">Data Klien</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-klien.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="tambah">

          <!-- Nama -->
          <div class="form-group">
            <label class="form-label" for="nama">
              Nama Klien <span class="form-label__required">*</span>
            </label>
            <input type="text" id="nama" name="nama"
                   class="form-input"
                   placeholder="Nama lengkap atau nama perusahaan"
                   value="<?= htmlspecialchars($old['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required autofocus>
          </div>

          <!-- Email & Telepon -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email"
                     class="form-input"
                     placeholder="email@klien.com"
                     value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="telepon">Telepon</label>
              <input type="tel" id="telepon" name="telepon"
                     class="form-input"
                     placeholder="08xxxxxxxxxx"
                     value="<?= htmlspecialchars($old['telepon'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
          </div>

          <!-- Alamat -->
          <div class="form-group">
            <label class="form-label" for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat"
                      class="form-input form-input--textarea"
                      rows="3"
                      placeholder="Alamat lengkap klien (opsional)"
                      ><?= htmlspecialchars($old['alamat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <!-- Catatan -->
          <div class="form-group">
            <label class="form-label" for="catatan">Catatan</label>
            <textarea id="catatan" name="catatan"
                      class="form-input form-input--textarea"
                      rows="3"
                      placeholder="Catatan internal tentang klien ini (opsional)"
                      ><?= htmlspecialchars($old['catatan'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Simpan Klien</button>
            <a href="<?= BASE_URL ?>/admin/klien/index.php"
               class="btn btn--secondary">Batal</a>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
      </div>

      <!-- Sidebar info -->
      <div class="form-sidebar-card">
        <p class="form-sidebar-card__title">Info</p>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
              <circle cx="12" cy="7" r="4"
                      stroke="var(--primary)" stroke-width="1.8"/>
            </svg>
          </span>
          <span><strong>Nama Klien</strong> adalah satu-satunya field wajib.
          Isi dengan nama lengkap, panggilan, atau nama perusahaan klien.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                    stroke="var(--primary)" stroke-width="1.8"/>
              <polyline points="22 6 12 13 2 6"
                        stroke="var(--primary)" stroke-width="1.8"/>
            </svg>
          </span>
          <span><strong>Email</strong> dan <strong>Telepon</strong> bersifat opsional,
          namun disarankan diisi untuk keperluan komunikasi dan dokumentasi.</span>
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
          <span>Isi <strong>Alamat</strong> dengan alamat lengkap klien.
          Berguna untuk pembuatan invoice atau dokumen resmi lainnya.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round"/>
              <polyline points="14 2 14 8 20 8"
                        stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round"/>
              <line x1="16" y1="13" x2="8" y2="13"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
              <line x1="16" y1="17" x2="8" y2="17"
                    stroke="var(--primary)" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <span><strong>Catatan</strong> bersifat internal dan hanya terlihat oleh
          tim EO. Gunakan untuk menyimpan preferensi atau riwayat klien.</span>
        </div>

        <div class="form-sidebar-tip">
          <span class="form-sidebar-tip__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="4" width="18" height="18" rx="2"
                    stroke="var(--success)" stroke-width="1.8"/>
              <path d="M16 2v4M8 2v4M3 10h18"
                    stroke="var(--success)" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
          <span>Setelah tersimpan, klien dapat dilampirkan ke
          <a href="<?= BASE_URL ?>/admin/events/tambah.php"
             style="color:var(--primary);font-weight:500">Event</a>
          untuk memudahkan pelaporan keuangan per klien.</span>
        </div>

      </div>
    </div>

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>