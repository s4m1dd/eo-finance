<?php
/**
 * EO Finance — Reset password baru (langkah 3 dari 3).
 *
 * Alur reset password:
 *   1. lupa-password.php   → input email, kirim OTP
 *   2. verifikasi-otp.php  → input kode OTP
 *   3. reset-password.php  → input password baru     ← halaman ini
 *
 * Hanya bisa diakses jika $_SESSION['reset_verified'] === true,
 * yang diset oleh proses-verifikasi-otp.php setelah OTP valid.
 * Tanpa itu, user diarahkan kembali ke langkah 1.
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

// ── Guard: langkah 1 & 2 harus sudah selesai ────────────────────────────────
// Tanpa reset_verified, akses ke halaman ini tidak sah.
// Tanpa reset_email, tidak ada konteks user mana yang direset.
if (empty($_SESSION['reset_verified']) || empty($_SESSION['reset_email'])) {
    header('Location: ' . BASE_URL . '/lupa-password.php');
    exit();
}

// ── Ambil dan hapus flash message ─────────────────────────────────────────────
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Email yang direset (samarkan untuk tampilan) ──────────────────────────────
$_resetEmail  = htmlspecialchars($_SESSION['reset_email'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Buat password baru untuk akun EO Finance kamu.">
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <title>Password Baru — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body class="auth-body">

  <div class="auth-layout">

    <!-- ── Panel kiri — branding ──────────────────────────── -->
    <aside class="auth-brand" aria-hidden="true">
      <div class="auth-brand__inner">
        <div class="auth-brand__logo">
          <svg width="48" height="48" viewBox="0 0 28 28" fill="none">
            <rect width="28" height="28" rx="8" fill="white" fill-opacity=".15"/>
            <path d="M8 20L12 13L16 16.5L20 8" stroke="white" stroke-width="2.2"
                  stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="20" cy="8" r="2" fill="white"/>
          </svg>
          <span class="auth-brand__name"><?= APP_NAME ?></span>
        </div>
        <blockquote class="auth-brand__tagline">
          "Hampir selesai —<br>buat password yang kuat."
        </blockquote>
        <ul class="auth-brand__features" role="list">
          <li class="auth-brand__feature-item">
            Minimal <?= PASSWORD_MIN_LENGTH ?> karakter
          </li>
          <li class="auth-brand__feature-item">
            Kombinasi huruf dan angka lebih aman
          </li>
          <li class="auth-brand__feature-item">
            Jangan gunakan password yang sama di tempat lain
          </li>
        </ul>
      </div>
    </aside>

    <!-- ── Panel kanan — form password baru ──────────────────── -->
    <main class="auth-main" id="main-content">
      <div class="auth-card">

        <!-- Ikon -->
        <div class="auth-card__icon" aria-hidden="true">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="11" width="18" height="11" rx="2"
                  stroke="currentColor" stroke-width="1.8"/>
            <path d="M7 11V7a5 5 0 0110 0v4"
                  stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round"/>
            <path d="M12 15v2" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round"/>
          </svg>
        </div>

        <!-- Header -->
        <div class="auth-card__header">
          <h1 class="auth-card__title">Buat password baru</h1>
          <p class="auth-card__subtitle">
            Identitas berhasil diverifikasi untuk
            <strong><?= $_resetEmail ?></strong>.
            Masukkan password baru kamu di bawah.
          </p>
        </div>

        <!-- Flash message -->
        <?php if (!empty($flashMessage)) : ?>
          <?php foreach ($flashMessage as $type => $pesan) : ?>
          <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
               role="alert">
            <span class="alert__icon" aria-hidden="true">
              <?php if ($type === 'success') : ?>
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

        <!-- Form password baru -->
        <form class="auth-form"
              action="<?= BASE_URL ?>/proses/auth/proses-reset-password.php"
              method="POST"
              novalidate>

          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <!-- Password baru -->
          <div class="form-group">
            <label class="form-label" for="password_baru">
              Password baru
              <span class="form-label__required" aria-hidden="true">*</span>
            </label>
            <input
              class="form-input"
              type="password"
              id="password_baru"
              name="password_baru"
              placeholder="Minimal <?= PASSWORD_MIN_LENGTH ?> karakter"
              minlength="<?= PASSWORD_MIN_LENGTH ?>"
              autocomplete="new-password"
              autofocus
              required>
            <span class="form-hint">
              Minimal <?= PASSWORD_MIN_LENGTH ?> karakter.
              Gunakan kombinasi huruf, angka, atau simbol.
            </span>
          </div>

          <!-- Konfirmasi password -->
          <div class="form-group">
            <label class="form-label" for="konfirmasi_password">
              Konfirmasi password baru
              <span class="form-label__required" aria-hidden="true">*</span>
            </label>
            <input
              class="form-input"
              type="password"
              id="konfirmasi_password"
              name="konfirmasi_password"
              placeholder="Ulangi password baru kamu"
              autocomplete="new-password"
              required>
          </div>

          <button type="submit" class="btn btn--primary btn--full">
            Simpan Password Baru
          </button>

          <p class="form-note">
            <span aria-hidden="true">*</span> Wajib diisi
          </p>

        </form>

        <!-- Indikator langkah -->
        <div class="auth-steps" aria-label="Langkah reset password">
          <span class="auth-steps__item">1. Masukkan email</span>
          <span class="auth-steps__divider" aria-hidden="true">›</span>
          <span class="auth-steps__item">2. Verifikasi OTP</span>
          <span class="auth-steps__divider" aria-hidden="true">›</span>
          <span class="auth-steps__item auth-steps__item--active" aria-current="step">
            3. Password baru
          </span>
        </div>

      </div>
    </main>

  </div>

</body>
</html>