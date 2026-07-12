<?php
/**
 * EO Finance — Halaman login.
 *
 * Dapat diakses semua role (admin_eo dan staff_eo).
 * Jika sudah login, redirect langsung ke dashboard sesuai role.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/config.php';

// ── Redirect jika sudah login ─────────────────────────────────────────────────
// Jika ada ?for=staff dan sesi aktif adalah admin_eo (atau sebaliknya),
// tampilkan form login agar pengguna bisa masuk dengan akun yang berbeda.
if (!empty($_SESSION['user_id'])) {
    $currentRole  = $_SESSION['role'] ?? '';
    $requestedFor = $_GET['for']      ?? '';

    $roleMismatch = ($requestedFor === 'staff' && $currentRole === 'admin_eo')
                 || ($requestedFor === 'admin' && $currentRole === 'staff_eo');

    if (!$roleMismatch) {
        $target = match ($currentRole) {
            'admin_eo' => BASE_URL . '/admin/dashboard.php',
            'staff_eo' => BASE_URL . '/staff/dashboard.php',
            default    => BASE_URL . '/login.php',
        };
        header('Location: ' . $target);
        exit();
    }
    // Role berbeda → jangan redirect, tampilkan form login seperti biasa
}

// ── Generate CSRF token jika belum ada ───────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Ambil dan hapus pesan flash dari sesi ─────────────────────────────────────
// Pesan dikirim oleh proses-login.php saat login gagal, atau oleh require_login()
// saat user mencoba akses halaman yang dilindungi tanpa sesi aktif.
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Masuk ke akun EO Finance kamu.">
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <title>Masuk — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body class="auth-body">

  <div class="auth-layout">

    <!-- ── Panel kiri — branding ────────────────────────────────── -->
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
          "Keuangan event yang<br>selalu terkendali."
        </blockquote>
        <ul class="auth-brand__features" role="list">
          <li class="auth-brand__feature-item">Multi event dalam satu dashboard</li>
          <li class="auth-brand__feature-item">Laporan keuangan otomatis</li>
          <li class="auth-brand__feature-item">Data EO terisolasi sepenuhnya</li>
        </ul>
      </div>
    </aside>

    <!-- ── Panel kanan — form login ─────────────────────────────── -->
    <main class="auth-main" id="main-content">
      <div class="auth-card">

        <!-- Header kartu -->
        <div class="auth-card__header">
          <h1 class="auth-card__title">Selamat datang</h1>
          <p class="auth-card__subtitle">Masuk ke akun <?= APP_NAME ?> kamu</p>
        </div>

        <!-- Pesan flash — error dari proses login atau sesi kadaluarsa -->
        <?php if (!empty($flashMessage)) : ?>
          <?php foreach ($flashMessage as $type => $pesan) : ?>
          <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
               role="alert">
            <span class="alert__icon" aria-hidden="true">
              <?php if ($type === 'error') : ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
                  <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round"/>
                </svg>
              <?php elseif ($type === 'success') : ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
                  <path d="M8 12l3 3 5-5" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              <?php else : ?>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                  <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
                  <path d="M12 8v4M12 16h.01" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round"/>
                </svg>
              <?php endif; ?>
            </span>
            <span class="alert__text">
              <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Form login -->
        <form class="auth-form"
              action="<?= BASE_URL ?>/proses/auth/proses-login.php"
              method="POST"
              novalidate>

          <!-- Token CSRF — akan diimplementasi di proses-login.php -->
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <!-- Field Email -->
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
              placeholder="nama@emailkamu.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              autocomplete="email"
              required
              autofocus>
          </div>

          <!-- Field Password -->
          <div class="form-group">
            <div class="form-label-row">
              <label class="form-label" for="password">
                Password
                <span class="form-label__required" aria-hidden="true">*</span>
              </label>
              <a href="<?= BASE_URL ?>/lupa-password.php"
                 class="form-label-link"
                 tabindex="-1">
                Lupa password?
              </a>
            </div>
            <input
              class="form-input"
              type="password"
              id="password"
              name="password"
              placeholder="Masukkan password kamu"
              autocomplete="current-password"
              required>
          </div>

          <!-- Tombol submit -->
          <button type="submit" class="btn btn--primary btn--full">
            Masuk
          </button>

        </form>

        <!-- Link ke register -->
        <p class="auth-card__footer-text">
          Belum punya akun?
          <a href="<?= BASE_URL ?>/register.php" class="auth-link">
            Daftar sekarang
          </a>
        </p>

      </div><!-- /auth-card -->
    </main>

  </div><!-- /auth-layout -->

</body>
</html>