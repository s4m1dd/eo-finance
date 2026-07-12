<?php
/**
 * EO Finance — Halaman 404 Not Found.
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';

http_response_code(404);

$dashboardUrl = BASE_URL . '/login.php';
if (!empty($_SESSION['role'])) {
    $dashboardUrl = $_SESSION['role'] === 'admin_eo'
        ? BASE_URL . '/admin/dashboard.php'
        : BASE_URL . '/staff/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>404 Tidak Ditemukan — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body class="auth-body">
  <div style="display:flex;align-items:center;justify-content:center;
              min-height:100vh;padding:var(--space-6)">
    <div class="card" style="max-width:440px;width:100%;text-align:center;
                              padding:var(--space-10) var(--space-8)">

      <!-- Ikon -->
      <div style="width:72px;height:72px;background:var(--primary-light);
                  border-radius:50%;display:flex;align-items:center;
                  justify-content:center;margin:0 auto var(--space-5)">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none"
             aria-hidden="true">
          <circle cx="11" cy="11" r="8" stroke="var(--primary)" stroke-width="1.8"/>
          <path d="M21 21l-4.35-4.35" stroke="var(--primary)" stroke-width="1.8"
                stroke-linecap="round"/>
          <path d="M8.5 11h5M11 8.5v5" stroke="var(--primary)" stroke-width="1.8"
                stroke-linecap="round"/>
        </svg>
      </div>

      <p style="font-size:48px;font-weight:800;color:var(--primary);
                line-height:1;margin-bottom:var(--space-3)">404</p>
      <h1 style="font-size:20px;font-weight:700;color:var(--text-primary);
                 margin-bottom:var(--space-3)">Halaman Tidak Ditemukan</h1>
      <p style="font-size:15px;color:var(--text-secondary);
                margin-bottom:var(--space-6);line-height:1.6">
        Halaman yang kamu cari tidak ada atau sudah dipindahkan.
        Periksa kembali URL yang kamu akses.
      </p>

      <div style="display:flex;gap:var(--space-2);justify-content:center;
                  flex-wrap:wrap">
        <a href="<?= $dashboardUrl ?>"
           class="btn btn--primary">Kembali ke Dashboard</a>
        <a href="javascript:history.back()"
           class="btn btn--secondary">Kembali</a>
      </div>

    </div>
  </div>
</body>
</html>