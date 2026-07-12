<?php
/**
 * EO Finance — Verifikasi OTP 2FA.
 *
 * Ditampilkan setelah login berhasil jika admin_eo mengaktifkan 2FA.
 * Kode OTP 6 digit dikirim via email (atau ditampilkan langsung jika
 * email belum dikonfigurasi) dan harus dimasukkan dalam 10 menit.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/config.php';

// Hanya bisa diakses kalau ada sesi "pending_2fa"
if (empty($_SESSION['__2fa_user_id'])) {
    header('Location: ' . BASE_URL . '/login.php'); exit();
}

$flash = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Email pemilik akun (untuk ditampilkan samar di UI) ────────────────────────
// Disimpan sementara di __2fa_data oleh proses-login.php saat 2FA dipicu.
$_2faEmail    = $_SESSION['__2fa_data']['email'] ?? '';
$_emailParts  = explode('@', $_2faEmail);
$_emailMasked = '***@***.***';
if (count($_emailParts) === 2) {
    $local    = $_emailParts[0];
    $domain   = $_emailParts[1];
    $visible  = mb_substr($local, 0, min(2, mb_strlen($local)));
    $_emailMasked = $visible . str_repeat('*', max(1, mb_strlen($local) - 2)) . '@' . $domain;
}
$_emailMasked = htmlspecialchars($_emailMasked, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Verifikasi dua langkah untuk masuk ke EO Finance.">
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <title>Verifikasi 2FA — <?= APP_NAME ?></title>
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
          "Satu kode lagi untuk<br>menjaga akun kamu aman."
        </blockquote>
        <ul class="auth-brand__features" role="list">
          <li class="auth-brand__feature-item">Verifikasi dua langkah aktif</li>
          <li class="auth-brand__feature-item">Kode dikirim ke email terdaftar</li>
          <li class="auth-brand__feature-item">Berlaku 10 menit sejak dikirim</li>
        </ul>
      </div>
    </aside>

    <!-- ── Panel kanan — form verifikasi 2FA ──────────────── -->
    <main class="auth-main" id="main-content">
      <div class="auth-card">

        <!-- Ikon -->
        <div class="auth-card__icon" aria-hidden="true">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
            <path d="M12 2l8 4v5c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"
                  stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>

        <!-- Header -->
        <div class="auth-card__header">
          <h1 class="auth-card__title">Verifikasi Dua Langkah</h1>
          <p class="auth-card__subtitle">
            Masukkan kode 6 digit yang dikirim ke
            <strong><?= $_emailMasked ?></strong>. Kode berlaku selama 10 menit.
          </p>
        </div>

        <!-- Flash message -->
        <?php if (!empty($flash)) : ?>
          <?php foreach ($flash as $type => $msg) : ?>
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
              <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- Form verifikasi kode -->
        <form class="auth-form"
              method="POST"
              action="<?= BASE_URL ?>/proses/auth/proses-verifikasi-2fa.php"
              novalidate autocomplete="off">

          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <div class="form-group">
            <label class="form-label" for="kode_otp">
              Kode Verifikasi
              <span class="form-label__required" aria-hidden="true">*</span>
            </label>
            <input
              class="form-input form-input--otp"
              type="text"
              id="kode_otp"
              name="kode_otp"
              placeholder="000000"
              maxlength="6"
              inputmode="numeric"
              pattern="[0-9]{6}"
              autocomplete="one-time-code"
              autofocus
              required>
            <span class="form-hint">
              Periksa folder spam jika tidak menemukan emailnya.
            </span>
          </div>

          <button type="submit" class="btn btn--primary btn--full">
            Verifikasi
          </button>

        </form>

        <!-- Kirim ulang kode -->
        <div class="auth-card__resend">
          <p class="auth-card__footer-text">
            Tidak menerima kode?
          </p>
          <form action="<?= BASE_URL ?>/proses/auth/proses-verifikasi-2fa.php"
                method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="kirim_ulang">
            <button type="submit" class="btn btn--text">
              Kirim ulang kode
            </button>
          </form>
        </div>

        <!-- Kembali ke login -->
        <p class="auth-card__footer-text">
          <a href="<?= BASE_URL ?>/proses/auth/proses-logout.php"
             class="auth-link auth-link--back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true">
              <path d="M19 12H5M11 6l-6 6 6 6"
                    stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Kembali ke login
          </a>
        </p>

      </div>
    </main>

  </div>

</body>
</html>