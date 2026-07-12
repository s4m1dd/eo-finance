<?php
/**
 * EO Finance — Verifikasi kode OTP (langkah 2 dari 3 reset password).
 *
 * Alur reset password:
 *   1. lupa-password.php   → input email, kirim OTP
 *   2. verifikasi-otp.php  → input kode OTP           ← halaman ini
 *   3. reset-password.php  → input password baru
 *
 * Halaman ini hanya bisa diakses jika $_SESSION['reset_email']
 * sudah diset oleh proses-lupa-password.php (langkah 1).
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

// ── Guard: langkah 1 harus sudah selesai ────────────────────────────────────
// proses-lupa-password.php menyimpan email ke sesi setelah OTP berhasil dikirim.
// Tanpa ini, halaman verifikasi tidak ada konteksnya.
if (empty($_SESSION['reset_email'])) {
    header('Location: ' . BASE_URL . '/lupa-password.php');
    exit();
}

// ── Ambil dan hapus flash message ─────────────────────────────────────────────
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Email yang sedang direset (untuk ditampilkan samar di UI) ─────────────────
// Tampilkan sebagian email saja: "j***@gmail.com" — tidak ekspos penuh
// tapi cukup untuk user mengonfirmasi OTP dikirim ke email yang benar.
$_resetEmail = $_SESSION['reset_email'] ?? '';
$_emailParts = explode('@', $_resetEmail);
$_emailMasked = '';
if (count($_emailParts) === 2) {
    $local  = $_emailParts[0];
    $domain = $_emailParts[1];
    // Tampilkan 2 karakter pertama + bintang + domain
    $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
    $_emailMasked = $visible . str_repeat('*', max(1, mb_strlen($local) - 2)) . '@' . $domain;
} else {
    $_emailMasked = '***@***.***';
}
$_emailMasked = htmlspecialchars($_emailMasked, ENT_QUOTES, 'UTF-8');

// ── Hitung sisa waktu OTP (untuk info di UI) ──────────────────────────────────
// $_SESSION['otp_sent_at'] diset oleh proses-lupa-password.php.
$_otpSentAt   = $_SESSION['otp_sent_at'] ?? time();
$_otpExpirySec = OTP_EXPIRY_MINUTES * 60;
$_sisaDetik   = max(0, $_otpExpirySec - (time() - $_otpSentAt));
$_sisaMenit   = (int) ceil($_sisaDetik / 60);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Masukkan kode OTP yang dikirim ke email kamu.">
  <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate">
  <title>Verifikasi OTP — <?= APP_NAME ?></title>
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
          "Satu langkah lagi<br>untuk masuk kembali."
        </blockquote>
        <ul class="auth-brand__features" role="list">
          <li class="auth-brand__feature-item">
            Kode dikirim ke email terdaftar
          </li>
          <li class="auth-brand__feature-item">
            Berlaku <?= OTP_EXPIRY_MINUTES ?> menit sejak dikirim
          </li>
          <li class="auth-brand__feature-item">
            Kode hanya bisa dipakai sekali
          </li>
        </ul>
      </div>
    </aside>

    <!-- ── Panel kanan — form OTP ─────────────────────────── -->
    <main class="auth-main" id="main-content">
      <div class="auth-card">

        <!-- Ikon -->
        <div class="auth-card__icon" aria-hidden="true">
          <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07
                     A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.07 1.18
                     A2 2 0 012.06 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81
                     a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27
                     a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z"
                  stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>

        <!-- Header -->
        <div class="auth-card__header">
          <h1 class="auth-card__title">Masukkan kode OTP</h1>
          <p class="auth-card__subtitle">
            Kode <?= OTP_LENGTH ?>-digit telah dikirim ke
            <strong><?= $_emailMasked ?></strong>.
            <?php if ($_sisaDetik > 0) : ?>
              Berlaku sekitar <strong><?= $_sisaMenit ?> menit</strong> lagi.
            <?php else : ?>
              Kode sudah <strong>kadaluarsa</strong> — minta kode baru di bawah.
            <?php endif; ?>
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

        <!-- Form verifikasi OTP -->
        <form class="auth-form"
              action="<?= BASE_URL ?>/proses/auth/proses-verifikasi-otp.php"
              method="POST"
              novalidate>

          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

          <!-- Input kode OTP -->
          <div class="form-group">
            <label class="form-label" for="kode_otp">
              Kode OTP
              <span class="form-label__required" aria-hidden="true">*</span>
            </label>
            <input
              class="form-input form-input--otp"
              type="text"
              id="kode_otp"
              name="kode_otp"
              placeholder="<?= str_repeat('0', OTP_LENGTH) ?>"
              maxlength="<?= OTP_LENGTH ?>"
              inputmode="numeric"
              pattern="[0-9]{<?= OTP_LENGTH ?>}"
              autocomplete="one-time-code"
              autofocus
              required>
            <span class="form-hint">
              Masukkan <?= OTP_LENGTH ?> digit angka dari email kamu.
              Periksa folder spam jika tidak menemukan emailnya.
            </span>
          </div>

          <button type="submit" class="btn btn--primary btn--full">
            Verifikasi Kode
          </button>

        </form>

        <!-- Link kirim ulang OTP -->
        <div class="auth-card__resend">
          <p class="auth-card__footer-text">
            Tidak menerima kode?
          </p>
          <form action="<?= BASE_URL ?>/proses/auth/proses-lupa-password.php"
                method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="email"
                   value="<?= htmlspecialchars($_SESSION['reset_email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="resend" value="1">
            <button type="submit" class="btn btn--text">
              Kirim ulang kode OTP
            </button>
          </form>
        </div>

        <!-- Indikator langkah -->
        <div class="auth-steps" aria-label="Langkah reset password">
          <span class="auth-steps__item">1. Masukkan email</span>
          <span class="auth-steps__divider" aria-hidden="true">›</span>
          <span class="auth-steps__item auth-steps__item--active" aria-current="step">
            2. Verifikasi OTP
          </span>
          <span class="auth-steps__divider" aria-hidden="true">›</span>
          <span class="auth-steps__item">3. Password baru</span>
        </div>

        <!-- Kembali ke langkah 1 -->
        <p class="auth-card__footer-text">
          <a href="<?= BASE_URL ?>/lupa-password.php"
             class="auth-link auth-link--back">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true">
              <path d="M19 12H5M11 6l-6 6 6 6"
                    stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Gunakan email berbeda
          </a>
        </p>

      </div>
    </main>

  </div>

</body>
</html>