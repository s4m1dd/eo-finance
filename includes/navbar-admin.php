<?php
/**
 * EO Finance — Sidebar navigasi admin_eo.
 *
 * PRASYARAT: header.php sudah di-include (session & config tersedia).
 *
 * CARA PAKAI — di setiap halaman admin setelah header:
 *   require_once __DIR__ . '/../../includes/navbar-admin.php';
 *
 * @package EOFinance
 */

if (!defined('EO_FINANCE_APP')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

// ── Data sesi ─────────────────────────────────────────────────────────────────
$_adminNama   = htmlspecialchars($_SESSION['nama']    ?? 'Admin',   ENT_QUOTES, 'UTF-8');
$_adminNamaEO = htmlspecialchars($_SESSION['nama_eo'] ?? 'EO Kamu', ENT_QUOTES, 'UTF-8');

// ── Deteksi halaman aktif ─────────────────────────────────────────────────────
// str_contains pada segmen folder: semua sub-halaman satu modul
// (index, tambah, edit, detail) menyalakan satu menu yang sama.
$_self = $_SERVER['PHP_SELF'] ?? '';

$_active = static function (string $segment) use ($_self): string {
    return str_contains($_self, $segment) ? 'nav__item--active' : '';
};

// ── Definisi menu ─────────────────────────────────────────────────────────────
$_menus = [
    [
        'label'   => 'Dashboard',
        'href'    => BASE_URL . '/admin/dashboard.php',
        'segment' => '/admin/dashboard.php',
        'icon'    => '<path d="M3 9.5L12 3l9 6.5V21a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linejoin="round" fill="none"/>
                     <path d="M9 22V12h6v10"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round"/>',
    ],
    [
        'label'   => 'Event',
        'href'    => BASE_URL . '/admin/events/index.php',
        'segment' => '/admin/events/',
        'icon'    => '<rect x="3" y="4" width="18" height="18" rx="2"
                          stroke="currentColor" stroke-width="1.7" fill="none"/>
                     <path d="M3 10h18M8 2v4M16 2v4"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round"/>',
    ],
    [
        'label'   => 'Kalender',
        'href'    => BASE_URL . '/admin/events/kalender.php',
        'segment' => '/admin/events/kalender.php',
        'icon'    => '<rect x="3" y="4" width="18" height="18" rx="2"
                          stroke="currentColor" stroke-width="1.7" fill="none"/>
                     <path d="M3 10h18M8 2v4M16 2v4"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round"/>
                     <path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"
                          stroke="currentColor" stroke-width="2"
                          stroke-linecap="round"/>',
    ],
    [
        'label'   => 'Transaksi',
        'href'    => BASE_URL . '/admin/transaksi/index.php',
        'segment' => '/admin/transaksi/',
        'icon'    => '<path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" fill="none"/>',
    ],
    [
        'label'   => 'Template Transaksi',
        'href'    => BASE_URL . '/admin/transaksi/template.php',
        'segment' => '/admin/transaksi/template.php',
        'icon'    => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                          stroke="currentColor" stroke-width="1.7" fill="none"/>
                      <polyline points="14 2 14 8 20 8"
                          stroke="currentColor" stroke-width="1.7"/>
                      <line x1="8" y1="13" x2="16" y2="13"
                          stroke="currentColor" stroke-width="1.7"/>
                      <line x1="8" y1="17" x2="12" y2="17"
                          stroke="currentColor" stroke-width="1.7"/>',
    ],
    [
        'label'   => 'Approval Transaksi',
        'href'    => BASE_URL . '/admin/transaksi/approval.php',
        'segment' => '/admin/transaksi/approval.php',
        'icon'    => '<path d="M20 6L9 17l-5-5"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'badge'   => isset($mysqli) ? (static function () use ($mysqli): int {
            $eoIdMenu = (int) ($_SESSION['eo_id'] ?? 0);
            if ($eoIdMenu <= 0 || !($mysqli instanceof mysqli)) {
                return 0;
            }
            $stmt = $mysqli->prepare(
                "SELECT COUNT(*) AS total FROM transaksi
                 WHERE eo_id = ? AND status_approval = 'pending'"
            );
            $stmt->bind_param('i', $eoIdMenu);
            $stmt->execute();
            $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $stmt->close();
            return $total;
        })() : 0,
    ],
    [
        'label'   => 'Vendor',
        'href'    => BASE_URL . '/admin/vendor/index.php',
        'segment' => '/admin/vendor/',
        'icon'    => '<rect x="2" y="7" width="20" height="14" rx="2"
                          stroke="currentColor" stroke-width="1.7" fill="none"/>
                     <path d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linejoin="round" fill="none"/>
                     <path d="M2 13h20"
                          stroke="currentColor" stroke-width="1.7"/>',
    ],
    [
        'label'   => 'Klien',
        'href'    => BASE_URL . '/admin/klien/index.php',
        'segment' => '/admin/klien/',
        'icon'    => '<circle cx="12" cy="8" r="4"
                          stroke="currentColor" stroke-width="1.7" fill="none"/>
                     <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" fill="none"/>',
    ],
    [
        'label'   => 'Staff',
        'href'    => BASE_URL . '/admin/staff/index.php',
        'segment' => '/admin/staff/',
        'icon'    => '<circle cx="9" cy="7" r="3"
                          stroke="currentColor" stroke-width="1.7" fill="none"/>
                     <circle cx="17" cy="9" r="2.5"
                          stroke="currentColor" stroke-width="1.7" fill="none"/>
                     <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" fill="none"/>
                     <path d="M17 14c2 0 4 1 4 3"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" fill="none"/>',
    ],
    [
        'label'   => 'Piutang & Hutang',
        'href'    => BASE_URL . '/admin/piutang-hutang/index.php',
        'segment' => '/admin/piutang-hutang/',
        'icon'    => '<path d="M17 1l4 4-4 4" stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                     <path d="M3 11V9a4 4 0 014-4h14" stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                     <path d="M7 23l-4-4 4-4" stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                     <path d="M21 13v2a4 4 0 01-4 4H3" stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'badge'   => isset($mysqli) ? (static function () use ($mysqli): int {
            $eoIdMenu = (int) ($_SESSION['eo_id'] ?? 0);
            if ($eoIdMenu <= 0 || !($mysqli instanceof mysqli)) {
                return 0;
            }
            $stmt = $mysqli->prepare(
                "SELECT COUNT(*) AS total FROM transaksi
                 WHERE eo_id = ? AND status_approval = 'approved' AND status_pembayaran = 'belum_lunas'"
            );
            $stmt->bind_param('i', $eoIdMenu);
            $stmt->execute();
            $total = (int) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);
            $stmt->close();
            return $total;
        })() : 0,
    ],
    [
        'label'   => 'Audit Log',
        'href'    => BASE_URL . '/admin/audit-log/index.php',
        'segment' => '/admin/audit-log/',
        'icon'    => '<path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586
                          a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" fill="none"/>',
    ],
    [
        'label'   => 'Notifikasi',
        'href'    => BASE_URL . '/admin/notifikasi/index.php',
        'segment' => '/admin/notifikasi/',
        'icon'    => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 01-3.46 0"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'badge'   => isset($mysqli) ? (static function () use ($mysqli): int {
            $uid = (int) ($_SESSION['user_id'] ?? 0);
            if ($uid <= 0) return 0;
            $s = $mysqli->prepare(
                'SELECT COUNT(*) AS total FROM notifikasi WHERE user_id = ? AND is_read = 0'
            );
            $s->bind_param('i', $uid);
            $s->execute();
            $total = (int) ($s->get_result()->fetch_assoc()['total'] ?? 0);
            $s->close();
            return $total;
        })() : 0,
    ],
    [
        'label'   => 'Laporan',
        'href'    => BASE_URL . '/admin/laporan/index.php',
        'segment' => '/admin/laporan/',
        'icon'    => '<path d="M3 3v18h18"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" fill="none"/>
                     <path d="M7 16l4-5 4 3 4-7"
                          stroke="currentColor" stroke-width="1.7"
                          stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
    ],
];
?>
<aside class="sidebar" id="sidebar" role="navigation" aria-label="Navigasi admin">
  <div class="sidebar__inner">

    <!-- ── Logo & nama EO ───────────────────────────────────── -->
    <div class="sidebar__header">
      <a href="<?= BASE_URL ?>/admin/dashboard.php"
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
      <p class="sidebar__eo-name" title="<?= $_adminNamaEO ?>">
        <?= $_adminNamaEO ?>
      </p>
    </div>

    <!-- ── Menu navigasi ────────────────────────────────────── -->
    <nav class="sidebar__nav" aria-label="Menu utama admin">
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
            <span class="nav__badge" aria-label="<?= (int)$_m['badge'] ?> menunggu">
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

    <!-- ── Footer sidebar: profil + logout ─────────────────── -->
    <div class="sidebar__footer">
      <a href="<?= BASE_URL ?>/admin/profil.php"
         class="sidebar__user"
         title="Lihat profil akun">
        <span class="sidebar__avatar" aria-hidden="true">
          <?= mb_strtoupper(
                mb_substr($_SESSION['nama'] ?? 'A', 0, 1, 'UTF-8'),
                'UTF-8'
              ) ?>
        </span>
        <div class="sidebar__user-info">
          <span class="sidebar__user-name"><?= $_adminNama ?></span>
          <span class="sidebar__user-role">Admin EO</span>
        </div>
      </a>

      <a href="<?= BASE_URL ?>/logout.php"
         class="sidebar__logout"
         aria-label="Keluar dari akun <?= $_adminNama ?>">
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