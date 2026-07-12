<?php
/**
 * EO Finance — Halaman 500 Internal Server Error.
 * Tampilkan pesan generik — tidak ekspos detail teknis ke user.
 */
define('EO_FINANCE_APP', true);

// Session & config mungkin belum tersedia saat error 500
// Gunakan define langsung sebagai fallback
if (!defined('APP_NAME')) {
    define('APP_NAME', 'EO Finance');
}
if (!defined('BASE_URL')) {
    define('BASE_URL', '/eo-finance');
}

// Coba load config jika memungkinkan
$configPath = __DIR__ . '/../config/config.php';
if (file_exists($configPath)) {
    @require_once $configPath;
}

http_response_code(500);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 Server Error — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/img/favicon.svg">
</head>
<body class="auth-body">
  <div style="display:flex;align-items:center;justify-content:center;
              min-height:100vh;padding:24px">
    <div class="card" style="max-width:440px;width:100%;text-align:center;
                              padding:48px 40px">

      <!-- Ikon -->
      <div style="width:72px;height:72px;background:var(--warning-light);
                  border-radius:50%;display:flex;align-items:center;
                  justify-content:center;margin:0 auto 20px">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none"
             aria-hidden="true">
          <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"
                stroke="var(--warning)" stroke-width="1.8" stroke-linejoin="round"/>
          <path d="M12 9v4M12 17h.01" stroke="var(--warning)" stroke-width="1.8"
                stroke-linecap="round"/>
        </svg>
      </div>

      <p style="font-size:48px;font-weight:800;color:var(--warning);
                line-height:1;margin-bottom:12px">500</p>
      <h1 style="font-size:20px;font-weight:700;color:#1D1D1F;
                 margin-bottom:12px">Terjadi Kesalahan</h1>
      <p style="font-size:15px;color:#6E6E73;margin-bottom:24px;line-height:1.6">
        Server mengalami masalah saat memproses permintaan ini.
        Coba lagi dalam beberapa saat. Jika masalah berlanjut,
        hubungi administrator sistem.
      </p>

      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>/login.php"
           style="display:inline-flex;align-items:center;padding:9px 20px;
                  background:#0071E3;color:#fff;border-radius:10px;
                  font-size:14px;font-weight:500;text-decoration:none">
          Ke Halaman Login
        </a>
        <a href="javascript:location.reload()"
           style="display:inline-flex;align-items:center;padding:9px 20px;
                  background:#fff;color:#1D1D1F;border:1.5px solid #D2D2D7;
                  border-radius:10px;font-size:14px;font-weight:500;
                  text-decoration:none">
          Coba Lagi
        </a>
      </div>

    </div>
  </div>
</body>
</html>