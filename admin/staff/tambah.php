<?php
/**
 * EO Finance — Form tambah akun staff baru.
 *
 * Admin membuat akun staff dengan password sementara.
 * Staff wajib mengganti password saat pertama login (first_login = 1).
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

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);
$old = $_SESSION['__old_input'] ?? [];
unset($_SESSION['__old_input']);

// Generate password sementara untuk ditampilkan ke admin
// Admin perlu menyampaikan password ini ke staff secara manual
$passwordSementara = $_SESSION['__temp_password'] ?? '';
unset($_SESSION['__temp_password']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Tambah Staff';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/admin/staff/index.php"
             class="breadcrumb__item">Staff</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">Tambah</span>
        </nav>
        <h1 class="page-title">Tambah Staff Baru</h1>
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

    <?php if ($passwordSementara !== '') : ?>
    <!-- Box tampilkan password sementara setelah berhasil membuat staff -->
    <div class="alert alert--success" role="alert" style="flex-direction:column;gap:8px">
      <p style="font-weight:600;color:inherit">
        Staff berhasil dibuat! Sampaikan password sementara ini ke staff:
      </p>
      <code style="font-size:22px;letter-spacing:4px;padding:8px 16px;
                   background:rgba(0,0,0,.06);border-radius:6px;display:inline-block">
        <?= htmlspecialchars($passwordSementara, ENT_QUOTES, 'UTF-8') ?>
      </code>
      <p style="font-size:13px;opacity:.8">
        Password ini hanya ditampilkan sekali. Staff wajib menggantinya saat pertama login.
      </p>
    </div>
    <?php endif; ?>

    <div class="form-layout">
      <div class="form-card">
        <h2 class="form-card__title">Data Akun Staff</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-staff.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="tambah">

          <!-- Nama & Email -->
          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="nama">
                Nama Lengkap <span class="form-label__required">*</span>
              </label>
              <input type="text" id="nama" name="nama"
                     class="form-input"
                     placeholder="Nama lengkap staff"
                     value="<?= htmlspecialchars($old['nama'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required autofocus>
            </div>
            <div class="form-group">
              <label class="form-label" for="email">
                Email <span class="form-label__required">*</span>
              </label>
              <input type="email" id="email" name="email"
                     class="form-input"
                     placeholder="email@staff.com"
                     value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                     required>
              <span class="form-hint">
                Digunakan sebagai username login. Unik dalam EO ini.
              </span>
            </div>
          </div>

          <!-- Password sementara -->
          <div class="form-group">
            <label class="form-label" for="password_sementara">
              Password Sementara <span class="form-label__required">*</span>
            </label>
            <input type="text" id="password_sementara" name="password_sementara"
                   class="form-input"
                   placeholder="Min <?= PASSWORD_MIN_LENGTH ?> karakter"
                   value="<?= htmlspecialchars($old['password_sementara'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required>
            <span class="form-hint">
              Password ini akan kamu sampaikan ke staff secara manual.
              Staff <strong>wajib</strong> menggantinya saat pertama login.
            </span>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Buat Akun Staff</button>
            <a href="<?= BASE_URL ?>/admin/staff/index.php"
               class="btn btn--secondary">Batal</a>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
      </div>

      <div class="form-sidebar-card">
        <p class="form-sidebar-card__title">Cara Kerja</p>
        <div class="form-sidebar-tip">
          Akun staff dibuat oleh Admin. Staff <strong>tidak bisa mendaftar sendiri</strong>.
        </div>
        <div class="form-sidebar-tip">
          Setelah akun dibuat, kamu akan melihat password sementara.
          Sampaikan ke staff melalui WhatsApp, email, atau secara langsung.
        </div>
        <div class="form-sidebar-tip">
          Staff wajib mengganti password sementara saat pertama kali login
          sebelum bisa mengakses fitur lainnya.
        </div>
        <div class="form-sidebar-tip">
          Setelah akun dibuat, tugaskan staff ke event melalui halaman
          <a href="<?= BASE_URL ?>/admin/staff/edit.php">Edit Staff</a>.
        </div>
      </div>
    </div>

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>