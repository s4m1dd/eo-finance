<?php
/**
 * EO Finance — Halaman 403 Forbidden.
 * Ditampilkan saat user login tapi role tidak sesuai.
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';

http_response_code(403);

// Tentukan link kembali berdasarkan role aktif
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
  <title>403 Akses Ditolak — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body class="auth-body">
  <div style="display:flex;align-items:center;justify-content:center;
              min-height:100vh;padding:var(--space-6)">
    <div class="card" style="max-width:440px;width:100%;text-align:center;
                              padding:var(--space-10) var(--space-8)">

      <!-- Ikon -->
      <div style="width:72px;height:72px;background:var(--danger-light);
                  border-radius:50%;display:flex;align-items:center;
                  justify-content:center;margin:0 auto var(--space-5)">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none"
             aria-hidden="true">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                stroke="var(--danger)" stroke-width="1.8"
                stroke-linejoin="round"/>
          <path d="M9 12l2 2 4-4" stroke="var(--danger)" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>

      <p style="font-size:48px;font-weight:800;color:var(--danger);
                line-height:1;margin-bottom:var(--space-3)">403</p>
      <h1 style="font-size:20px;font-weight:700;color:var(--text-primary);
                 margin-bottom:var(--space-3)">Akses Ditolak</h1>
      <p style="font-size:15px;color:var(--text-secondary);
                margin-bottom:var(--space-6);line-height:1.6">
        Kamu tidak memiliki izin untuk mengakses halaman ini.
        Pastikan kamu login dengan akun yang sesuai.
      </p>

      <div style="display:flex;gap:var(--space-2);justify-content:center;
                  flex-wrap:wrap">
        <a href="<?= $dashboardUrl ?>"
           class="btn btn--primary">Kembali ke Dashboard</a>
        <a href="<?= BASE_URL ?>/logout.php"
           class="btn btn--secondary">Logout</a>
      </div>

    </div>
  </div>
</body>
</html>