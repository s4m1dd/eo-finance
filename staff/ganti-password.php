<?php
/**
 * EO Finance — Halaman ganti password staff_eo.
 *
 * Dua skenario:
 *   A. first_login = 1 → staff baru wajib ganti password sebelum lanjut.
 *      Tampil tanpa sidebar/navbar (layout terpusat), field password lama
 *      disembunyikan, banner peringatan ditampilkan.
 *   B. Ganti mandiri → staff aktif mengubah password dari menu akun.
 *      Tampil dalam layout normal (sidebar + navbar), field password lama muncul.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
require_role('staff_eo');

$isFirstLogin = (int) ($_SESSION['first_login'] ?? 0) === 1;

// Ambil flash message sekali lalu hapus
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// Pastikan CSRF token tersedia
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Ganti Password';
$page_type = 'staff';

// ── Skenario A (first_login): render halaman standalone tanpa sidebar ──────────
if ($isFirstLogin) :
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <title>Buat Password Baru — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/staff.css">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body class="role-staff_eo">

<div class="ganti-password-body">
  <div class="ganti-password-card">

    <!-- Ikon kunci -->
    <div class="ganti-password-card__icon">
      <svg width="26" height="26" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
           viewBox="0 0 24 24" aria-hidden="true">
        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
      </svg>
    </div>

    <h1 class="ganti-password-card__title">Buat Password Baru</h1>
    <p class="ganti-password-card__subtitle">
      Selamat datang! Kamu harus membuat password baru sebelum bisa menggunakan aplikasi.
    </p>

    <!-- Banner first_login -->
    <div class="first-login-notice" role="alert">
      <span class="first-login-notice__icon" aria-hidden="true">
        <svg width="18" height="18" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
             viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
      </span>
      <div class="first-login-notice__body">
        <span class="first-login-notice__title">Langkah wajib sebelum melanjutkan</span>
        <p class="first-login-notice__text">
          Admin EO telah membuat password sementara untukmu.
          Buat password baru yang aman untuk melindungi akunmu.
        </p>
      </div>
    </div>

    <!-- Flash message error -->
    <?php if (!empty($flashMessage['error'])) : ?>
    <div class="alert alert--error" role="alert" style="margin-bottom: var(--space-4);">
      <span class="alert__text">
        <?= htmlspecialchars($flashMessage['error'], ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>
    <?php endif; ?>

    <!-- Form: tidak ada field password_lama untuk first_login -->
    <form method="POST"
          action="<?= BASE_URL ?>/proses/staff/proses-ganti-password.php"
          novalidate>
      <input type="hidden" name="csrf_token"
             value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

      <!-- Password baru -->
      <div class="form-group">
        <label class="form-label" for="password_baru">
          Password Baru <span class="form-label__required">*</span>
        </label>
        <input type="password"
               id="password_baru"
               name="password_baru"
               class="form-input"
               placeholder="Minimal <?= PASSWORD_MIN_LENGTH ?> karakter"
               minlength="<?= PASSWORD_MIN_LENGTH ?>"
               maxlength="72"
               autocomplete="new-password"
               required>
        <span class="form-hint">Minimal <?= PASSWORD_MIN_LENGTH ?> karakter.</span>
      </div>

      <!-- Konfirmasi password -->
      <div class="form-group">
        <label class="form-label" for="konfirmasi_password">
          Konfirmasi Password Baru <span class="form-label__required">*</span>
        </label>
        <input type="password"
               id="konfirmasi_password"
               name="konfirmasi_password"
               class="form-input"
               placeholder="Ulangi password baru"
               maxlength="72"
               autocomplete="new-password"
               required>
      </div>

      <div class="form-actions" style="margin-top: var(--space-2);">
        <button type="submit" class="btn btn--primary btn--full">
          Buat Password &amp; Masuk
        </button>
      </div>

      <p class="form-note" style="margin-top: var(--space-3);">
        <span aria-hidden="true">*</span> Wajib diisi
      </p>
    </form>

  </div><!-- /ganti-password-card -->
</div>

<script src="<?= BASE_URL ?>/assets/js/validation.js"></script>
</body>
</html>

<?php
// ── Skenario B (ganti mandiri): render halaman dalam layout normal ────────────
else :
  require_once __DIR__ . '/../includes/header.php';
  require_once __DIR__ . '/../includes/navbar-staff.php';
?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Ganti Password</h1>
        <p class="page-subtitle">Perbarui password akun kamu</p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/staff/profil.php" class="btn btn--secondary btn--sm">
          ← Kembali ke Profil
        </a>
      </div>
    </div>

    <!-- Flash message -->
    <?php foreach ($flashMessage as $type => $pesan) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
         role="alert">
      <span class="alert__text">
        <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>
    <?php endforeach; ?>

    <div class="ganti-password-layout">
      <div class="ganti-password-card">

        <!-- Ikon kunci -->
        <div class="ganti-password-card__icon">
          <svg width="26" height="26" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
               viewBox="0 0 24 24" aria-hidden="true">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </div>

        <h2 class="ganti-password-card__title">Perbarui Password</h2>
        <p class="ganti-password-card__subtitle">
          Masukkan password saat ini untuk memverifikasi identitasmu, lalu buat password baru.
        </p>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/staff/proses-ganti-password.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

          <!-- Password saat ini (wajib untuk skenario B) -->
          <div class="form-group">
            <label class="form-label" for="password_lama">
              Password Saat Ini <span class="form-label__required">*</span>
            </label>
            <input type="password"
                   id="password_lama"
                   name="password_lama"
                   class="form-input"
                   placeholder="Masukkan password kamu saat ini"
                   autocomplete="current-password"
                   required>
          </div>

          <hr style="border:none;border-top:1px solid var(--border-light);
                     margin:var(--space-5) 0;">

          <!-- Password baru -->
          <div class="form-group">
            <label class="form-label" for="password_baru">
              Password Baru <span class="form-label__required">*</span>
            </label>
            <input type="password"
                   id="password_baru"
                   name="password_baru"
                   class="form-input"
                   placeholder="Minimal <?= PASSWORD_MIN_LENGTH ?> karakter"
                   minlength="<?= PASSWORD_MIN_LENGTH ?>"
                   maxlength="72"
                   autocomplete="new-password"
                   required>
            <span class="form-hint">Minimal <?= PASSWORD_MIN_LENGTH ?> karakter.</span>
          </div>

          <!-- Konfirmasi password -->
          <div class="form-group">
            <label class="form-label" for="konfirmasi_password">
              Konfirmasi Password Baru <span class="form-label__required">*</span>
            </label>
            <input type="password"
                   id="konfirmasi_password"
                   name="konfirmasi_password"
                   class="form-input"
                   placeholder="Ulangi password baru"
                   maxlength="72"
                   autocomplete="new-password"
                   required>
          </div>

          <div class="form-actions" style="margin-top: var(--space-2);">
            <button type="submit" class="btn btn--primary">
              Simpan Password Baru
            </button>
            <a href="<?= BASE_URL ?>/staff/profil.php"
               class="btn btn--ghost">
              Batal
            </a>
          </div>

          <p class="form-note" style="margin-top: var(--space-3);">
            <span aria-hidden="true">*</span> Wajib diisi
          </p>
        </form>

      </div><!-- /ganti-password-card -->
    </div><!-- /ganti-password-layout -->

  </div><!-- /admin-content -->
</div>

<?php
  $page_type = 'staff';
  require_once __DIR__ . '/../includes/footer.php';
endif;