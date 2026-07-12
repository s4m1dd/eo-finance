<?php
/**
 * EO Finance — Halaman lupa password.
 *
 * Langkah pertama dari alur reset password:
 *   1. lupa-password.php   → user masukkan email    ← halaman ini
 *   2. verifikasi-otp.php  → user masukkan kode OTP
 *   3. reset-password.php  → user masukkan password baru
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/config.php';

// ── Redirect jika sudah login — tidak perlu reset password ────────────────────
if (!empty($_SESSION['user_id'])) {
    $target = match ($_SESSION['role'] ?? '') {
        'admin_eo' => BASE_URL . '/admin/dashboard.php',
        'staff_eo' => BASE_URL . '/staff/dashboard.php',
        default    => BASE_URL . '/login.php',
    };
    header('Location: ' . $target);
    exit();
}

// ── Ambil dan hapus pesan flash ───────────────────────────────────────────────
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Reset password akun <?= APP_NAME ?> kamu melalui kode OTP.">
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <title>Lupa Password — <?= APP_NAME ?></title>
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
          "Tenang, kita bantu kamu<br>masuk kembali."
        </blockquote>
        <ul class="auth-brand__features" role="list">
          <li class="auth-brand__feature-item">Kode OTP dikirim ke email terdaftar</li>
          <li class="auth-brand__feature-item">Kode berlaku <?= OTP_EXPIRY_MINUTES ?> menit</li>
          <li class="auth-brand__feature-item">Proses aman dan terenkripsi</li>
        </ul>
      </div>
    </aside>

    <!-- ── Panel kanan — form lupa password ─────────────────────── -->
    <main class="auth-main" id="main-content">
      <div class="auth-card">

        <!-- Ikon ilustrasi kunci -->
        <div class="auth-card__icon" aria-hidden="true">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
               xmlns="http://www.w3.org/2000/svg">
            <rect x="3" y="11" width="18" height="11" rx="2"
                  stroke="currentColor" stroke-width="1.8"/>
            <path d="M7 11V7a5 5 0 0110 0v4"
                  stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round"/>
            <circle cx="12" cy="16" r="1.5" fill="currentColor"/>
          </svg>
        </div>

        <!-- Header kartu -->
        <div class="auth-card__header">
          <h1 class="auth-card__title">Lupa password?</h1>
          <p class="auth-card__subtitle">
            Masukkan email yang terdaftar. Kami akan mengirimkan
            kode OTP <?= OTP_LENGTH ?>-digit untuk verifikasi identitasmu.
          </p>
        </div>

        <!-- Pesan flash -->
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

        <!-- Form lupa password -->
        <form class="auth-form"
              action="<?= BASE_URL ?>/proses/auth/proses-lupa-password.php"
              method="POST"
              novalidate>

          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <!-- Field Email -->
          <div class="form-group">
            <label class="form-label" for="email">
              Email terdaftar
              <span class="form-label__required" aria-hidden="true">*</span>
            </label>
            <input
              class="form-input"
              type="email"
              id="email"
              name="email"
              placeholder="email@kamu.com"
              value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              autocomplete="email"
              required
              autofocus>
            <span class="form-hint">
              Pastikan email yang kamu masukkan sama dengan yang digunakan saat mendaftar.
            </span>
          </div>

          <!-- Tombol submit -->
          <button type="submit" class="btn btn--primary btn--full">
            Kirim Kode OTP
          </button>

        </form>

        <!-- Indikator langkah — posisi halaman ini dalam alur reset -->
        <div class="auth-steps" aria-label="Langkah reset password">
          <span class="auth-steps__item auth-steps__item--active" aria-current="step">
            1. Masukkan email
          </span>
          <span class="auth-steps__divider" aria-hidden="true">›</span>
          <span class="auth-steps__item">2. Verifikasi OTP</span>
          <span class="auth-steps__divider" aria-hidden="true">›</span>
          <span class="auth-steps__item">3. Password baru</span>
        </div>

        <!-- Link kembali ke login -->
        <p class="auth-card__footer-text">
          <a href="<?= BASE_URL ?>/login.php" class="auth-link auth-link--back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 12H5M11 6l-6 6 6 6"
                    stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Kembali ke halaman login
          </a>
        </p>

      </div><!-- /auth-card -->
    </main>

  </div><!-- /auth-layout -->

</body>
</html>