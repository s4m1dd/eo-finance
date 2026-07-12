<?php
/**
 * EO Finance — Halaman registrasi admin_eo baru.
 *
 * Hanya untuk pemilik EO yang belum punya akun.
 * Staff tidak bisa mendaftar sendiri — akun staff dibuat oleh admin.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/config.php';

// ── Redirect jika sudah login ─────────────────────────────────────────────────
if (!empty($_SESSION['user_id'])) {
    $target = match ($_SESSION['role'] ?? '') {
        'admin_eo' => BASE_URL . '/admin/dashboard.php',
        'staff_eo' => BASE_URL . '/staff/dashboard.php',
        default    => BASE_URL . '/login.php',
    };
    header('Location: ' . $target);
    exit();
}

// ── Generate CSRF token jika belum ada ───────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Ambil dan hapus pesan flash ───────────────────────────────────────────────
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Pertahankan nilai input jika ada validasi gagal dari proses-register.php ──
// Semua field kecuali password dikembalikan agar user tidak mengetik ulang.
$old = [
    'nama'      => $_SESSION['__old_input']['nama']      ?? '',
    'email'     => $_SESSION['__old_input']['email']     ?? '',
    'nama_eo'   => $_SESSION['__old_input']['nama_eo']   ?? '',
    'alamat_eo' => $_SESSION['__old_input']['alamat_eo'] ?? '',
    'telepon'   => $_SESSION['__old_input']['telepon']   ?? '',
];
unset($_SESSION['__old_input']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Daftarkan EO kamu ke <?= APP_NAME ?> secara gratis.">
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <title>Daftar — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body class="auth-body">

  <div class="auth-layout auth-layout--wide">

    <!-- ── Panel kiri — branding ─────────────────────────────────── -->
    <aside class="auth-brand" aria-hidden="true">
      <div class="auth-brand__inner">
        <div class="auth-brand__logo">
          <svg width="48" height="48" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="28" height="28" rx="8" fill="white" fill-opacity=".15"/>
            <path d="M8 20L12 13L16 16.5L20 8" stroke="white" stroke-width="2.2"
                  stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="20" cy="8" r="2" fill="white"/>
          </svg>
          <span class="auth-brand__name"><?= APP_NAME ?></span>
        </div>
        <blockquote class="auth-brand__tagline">
          "Mulai kelola keuangan event<br>kamu hari ini."
        </blockquote>
        <ul class="auth-brand__features" role="list">
          <li class="auth-brand__feature-item">Gratis untuk memulai</li>
          <li class="auth-brand__feature-item">Setup dalam hitungan menit</li>
          <li class="auth-brand__feature-item">Tambahkan staff kapan saja</li>
        </ul>
      </div>
    </aside>

    <!-- ── Panel kanan — form register ───────────────────────────── -->
    <main class="auth-main" id="main-content">
      <div class="auth-card auth-card--scroll">

        <!-- Header kartu -->
        <div class="auth-card__header">
          <h1 class="auth-card__title">Buat akun baru</h1>
          <p class="auth-card__subtitle">Daftarkan Event Organizer kamu ke <?= APP_NAME ?></p>
        </div>

        <!-- Pesan flash — error validasi dari proses-register.php -->
        <?php if (!empty($flashMessage)) : ?>
          <?php foreach ($flashMessage as $type => $pesan) : ?>
          <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
               role="alert">
            <span class="alert__icon" aria-hidden="true">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
                <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.8"
                      stroke-linecap="round"/>
              </svg>
            </span>
            <span class="alert__text">
              <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Form registrasi -->
        <form class="auth-form"
              action="<?= BASE_URL ?>/proses/auth/proses-register.php"
              method="POST"
              novalidate>

          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <!-- ── SEKSI: Data Akun Admin ─────────────────────────── -->
          <fieldset class="form-fieldset">
            <legend class="form-fieldset__legend">Data Akun</legend>

            <!-- Nama Lengkap -->
            <div class="form-group">
              <label class="form-label" for="nama">
                Nama lengkap
                <span class="form-label__required" aria-hidden="true">*</span>
              </label>
              <input
                class="form-input"
                type="text"
                id="nama"
                name="nama"
                placeholder="Nama lengkap kamu"
                value="<?= htmlspecialchars($old['nama'], ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="name"
                required
                autofocus>
            </div>

            <!-- Email -->
            <div class="form-group">
              <label class="form-label" for="email">
                Email
                <span class="form-label__required" aria-hidden="true">*</span>
              </label>
              <input
                class="form-input"
                type="email"
                id="email"
                name="email"
                placeholder="email@kamu.com"
                value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="email"
                required>
              <span class="form-hint">Digunakan untuk login dan notifikasi sistem</span>
            </div>

            <!-- Password -->
            <div class="form-group">
              <label class="form-label" for="password">
                Password
                <span class="form-label__required" aria-hidden="true">*</span>
              </label>
              <input
                class="form-input"
                type="password"
                id="password"
                name="password"
                placeholder="Minimal 8 karakter"
                autocomplete="new-password"
                required>
              <span class="form-hint">Minimal <?= PASSWORD_MIN_LENGTH ?> karakter</span>
            </div>

            <!-- Konfirmasi Password -->
            <div class="form-group">
              <label class="form-label" for="konfirmasi_password">
                Konfirmasi password
                <span class="form-label__required" aria-hidden="true">*</span>
              </label>
              <input
                class="form-input"
                type="password"
                id="konfirmasi_password"
                name="konfirmasi_password"
                placeholder="Ulangi password kamu"
                autocomplete="new-password"
                required>
            </div>

          </fieldset>

          <!-- ── SEKSI: Data EO ──────────────────────────────────── -->
          <fieldset class="form-fieldset">
            <legend class="form-fieldset__legend">Data Event Organizer</legend>

            <!-- Nama EO -->
            <div class="form-group">
              <label class="form-label" for="nama_eo">
                Nama EO
                <span class="form-label__required" aria-hidden="true">*</span>
              </label>
              <input
                class="form-input"
                type="text"
                id="nama_eo"
                name="nama_eo"
                placeholder="Nama Event Organizer kamu"
                value="<?= htmlspecialchars($old['nama_eo'], ENT_QUOTES, 'UTF-8') ?>"
                required>
            </div>

            <!-- Telepon EO -->
            <div class="form-group">
              <label class="form-label" for="telepon">
                Nomor telepon EO
              </label>
              <input
                class="form-input"
                type="tel"
                id="telepon"
                name="telepon"
                placeholder="08xxxxxxxxxx"
                value="<?= htmlspecialchars($old['telepon'], ENT_QUOTES, 'UTF-8') ?>"
                autocomplete="tel">
            </div>

            <!-- Alamat EO -->
            <div class="form-group">
              <label class="form-label" for="alamat_eo">
                Alamat EO
              </label>
              <textarea
                class="form-input form-input--textarea"
                id="alamat_eo"
                name="alamat_eo"
                placeholder="Alamat kantor atau operasional EO kamu"
                rows="3"><?= htmlspecialchars($old['alamat_eo'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </div>

          </fieldset>

          <!-- Tombol submit -->
          <button type="submit" class="btn btn--primary btn--full">
            Buat Akun
          </button>

          <!-- Catatan field wajib -->
          <p class="form-note">
            <span aria-hidden="true">*</span> Wajib diisi
          </p>

        </form>

        <!-- Link ke login -->
        <p class="auth-card__footer-text">
          Sudah punya akun?
          <a href="<?= BASE_URL ?>/login.php" class="auth-link">
            Masuk di sini
          </a>
        </p>

      </div><!-- /auth-card -->
    </main>

  </div><!-- /auth-layout -->

</body>
</html>