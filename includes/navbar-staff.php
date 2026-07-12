<?php
/**
 * EO Finance — Sidebar navigasi staff_eo.
 *
 * Versi terbatas dari navbar admin — hanya 3 menu sesuai
 * hak akses staff: dashboard, event yang ditugaskan, transaksi.
 *
 * PRASYARAT: header.php sudah di-include (session & config tersedia).
 *
 * CARA PAKAI — di setiap halaman staff setelah header:
 *   require_once __DIR__ . '/../../includes/navbar-staff.php';
 *
 * @package EOFinance
 */

if (!defined('EO_FINANCE_APP')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

// ── Data sesi ─────────────────────────────────────────────────────────────────
$_staffNama   = htmlspecialchars($_SESSION['nama']    ?? 'Staff',   ENT_QUOTES, 'UTF-8');
$_staffNamaEO = htmlspecialchars($_SESSION['nama_eo'] ?? 'EO Kamu', ENT_QUOTES, 'UTF-8');

// ── Deteksi halaman aktif ─────────────────────────────────────────────────────
$_self = $_SERVER['PHP_SELF'] ?? '';

$_active = static function (string $segment) use ($_self): string {
    return str_contains($_self, $segment) ? 'nav__item--active' : '';
};

// ── Definisi menu (3 menu, akses terbatas) ───────────────────────────────────
// Hitung notifikasi belum dibaca untuk badge
$_notifBelumBacaStaff = 0;
if (isset($mysqli) && isset($_SESSION['user_id'])) {
    $_stmtNS = $mysqli->prepare(
        'SELECT COUNT(*) AS total FROM notifikasi WHERE user_id = ? AND is_read = 0'
    );
    $_userIdStaff = (int) ($_SESSION['user_id'] ?? 0);
    $_stmtNS->bind_param('i', $_userIdStaff);
    $_stmtNS->execute();
    $_notifBelumBacaStaff = (int) ($_stmtNS->get_result()->fetch_assoc()['total'] ?? 0);
    $_stmtNS->close();
}

$_menus = [
    [
        'label'   => 'Dashboard',
        'href'    => BASE_URL . '/staff/dashboard.php',
        'segment' => '/staff/dashboard.php',
        'icon'    => '<path d="M3 9.5L12 3l9 6.5V21a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linejoin="round" fill="none"/>
                     <path d="M9 22V12h6v10"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round"/>',
    ],
    [
        'label'   => 'Event Saya',
        'href'    => BASE_URL . '/staff/events/index.php',
        'segment' => '/staff/events/',
        'icon'    => '<rect x="3" y="4" width="18" height="18" rx="2"
                          stroke="currentColor" stroke-width="1.7" fill="none"/>
                     <path d="M3 10h18M8 2v4M16 2v4"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round"/>',
    ],
    [
        'label'   => 'Transaksi',
        'href'    => BASE_URL . '/staff/transaksi/index.php',
        'segment' => '/staff/transaksi/',
        'icon'    => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" fill="none"/>',
    ],
    [
        'label'   => 'Notifikasi',
        'href'    => BASE_URL . '/staff/notifikasi/index.php',
        'segment' => '/staff/notifikasi/',
        'badge'   => $_notifBelumBacaStaff,
        'icon'    => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
    ],
];
?>
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Navigasi staff">
  <div class="sidebar__inner">

    <!-- ── Logo & nama EO ───────────────────────────────────── -->
    <div class="sidebar__header">
      <a href="<?= BASE_URL ?>/staff/dashboard.php"
         class="sidebar__brand"
         aria-label="<?= APP_NAME ?> — Kembali ke Dashboard">
        <span class="sidebar__logo-icon" aria-hidden="true">
          <svg width="32" height="32" viewBox="0 0 28 28" fill="none"
               xmlns="http://www.w3.org/2000/svg">
            <rect width="28" height="28" rx="8" fill="#0071E3"/>
            <path d="M8 20L12 13L16 16.5L20 8"
                  stroke="white" stroke-width="2.2"
                  stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="20" cy="8" r="2" fill="white"/>
          </svg>
        </span>
        <span class="sidebar__brand-name"><?= APP_NAME ?></span>
      </a>
      <p class="sidebar__eo-name" title="<?= $_staffNamaEO ?>">
        <?= $_staffNamaEO ?>
      </p>
    </div>

    <!-- ── Menu navigasi (3 menu) ──────────────────────────── -->
    <nav class="sidebar__nav" aria-label="Menu utama staff">
      <ul class="nav__list" role="list">
        <?php foreach ($_menus as $_m) :
            $_cls = $_active($_m['segment']);
        ?>
        <li class="nav__item <?= $_cls ?>"
            <?= $_cls ? 'aria-current="page"' : '' ?>>
          <a href="<?= htmlspecialchars($_m['href'], ENT_QUOTES, 'UTF-8') ?>"
             class="nav__link">
            <span class="nav__icon" aria-hidden="true">
              <svg width="20" height="20" viewBox="0 0 24 24"
                   xmlns="http://www.w3.org/2000/svg">
                <?= $_m['icon'] ?>
              </svg>
            </span>
            <span class="nav__label">
              <?= htmlspecialchars($_m['label'], ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php if (!empty($_m['badge'])) : ?>
            <span class="nav__badge" aria-label="<?= (int)$_m['badge'] ?> notifikasi baru">
              <?= (int)$_m['badge'] ?>
            </span>
            <?php endif; ?>
            <?php if ($_cls) : ?>
            <span class="nav__active-indicator" aria-hidden="true"></span>
            <?php endif; ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <!-- ── Link ganti password ──────────────────────────────── -->
    <!--
      Dipisah dari nav utama karena ini pengaturan akun,
      bukan navigasi konten. Akses cepat dari sidebar penting
      untuk flow first_login yang mewajibkan ganti password.
    -->
    <div class="sidebar__account-actions">
      <a href="<?= BASE_URL ?>/staff/ganti-password.php"
         class="sidebar__account-link
                <?= str_contains($_self, '/staff/ganti-password.php')
                    ? 'sidebar__account-link--active' : '' ?>">
        <span class="sidebar__account-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="11" width="18" height="11" rx="2"
                  stroke="currentColor" stroke-width="1.7"/>
            <path d="M7 11V7a5 5 0 0110 0v4"
                  stroke="currentColor" stroke-width="1.7"
                  stroke-linecap="round"/>
            <circle cx="12" cy="16" r="1.5" fill="currentColor"/>
          </svg>
        </span>
        Ganti Password
      </a>
    </div>

    <!-- ── Footer sidebar: profil + logout ─────────────────── -->
    <div class="sidebar__footer">
      <a href="<?= BASE_URL ?>/staff/profil.php"
         class="sidebar__user"
         title="Lihat profil akun">
        <span class="sidebar__avatar" aria-hidden="true">
          <?= mb_strtoupper(
                mb_substr($_SESSION['nama'] ?? 'S', 0, 1, 'UTF-8'),
                'UTF-8'
              ) ?>
        </span>
        <div class="sidebar__user-info">
          <span class="sidebar__user-name"><?= $_staffNama ?></span>
          <span class="sidebar__user-role">Staff EO</span>
        </div>
      </a>

      <a href="<?= BASE_URL ?>/logout.php"
         class="sidebar__logout"
         aria-label="Keluar dari akun <?= $_staffNama ?>">
        <span class="sidebar__logout-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"
                  stroke="currentColor" stroke-width="1.7"
                  stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M16 17l5-5-5-5M21 12H9"
                  stroke="currentColor" stroke-width="1.7"
                  stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </span>
        <span class="sidebar__logout-label">Keluar</span>
      </a>
    </div>

  </div>
</aside>