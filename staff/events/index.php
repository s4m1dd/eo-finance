<?php
/**
 * EO Finance — Daftar event yang ditugaskan ke staff.
 *
 * Staff hanya melihat event dari tabel penugasan miliknya.
 * Tidak ada aksi edit/hapus — view-only.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_role('staff_eo');

// Guard first_login
if ((int) ($_SESSION['first_login'] ?? 0) === 1) {
    $_SESSION['__flash']['warning'] = 'Silakan ganti password terlebih dahulu.';
    header('Location: ' . BASE_URL . '/staff/ganti-password.php');
    exit();
}

$userId = get_current_user_id();
$eoId   = get_current_eo_id();

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Filter & pagination ───────────────────────────────────────────────────────
$filterStatus = $_GET['status'] ?? '';
$filterCari   = trim($_GET['cari'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = ITEMS_PER_PAGE;
$offset       = ($page - 1) * $perPage;

$validStatus = ['draft', 'aktif', 'selesai', 'dibatalkan'];
if (!in_array($filterStatus, $validStatus, true)) {
    $filterStatus = '';
}

// ── WHERE: hanya event dari tabel penugasan milik staff ini ──────────────────
$where  = 'WHERE p.user_id = ? AND p.eo_id = ?';
$params = [$userId, $eoId];
$types  = 'ii';

if ($filterStatus !== '') {
    $where   .= ' AND e.status = ?';
    $params[] = $filterStatus;
    $types   .= 's';
}
if ($filterCari !== '') {
    $where   .= ' AND (e.nama_event LIKE ? OR k.nama LIKE ?)';
    $like     = '%' . $filterCari . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

// ── Total ─────────────────────────────────────────────────────────────────────
$stmtCount = $mysqli->prepare(
    "SELECT COUNT(*) AS total
     FROM penugasan p
     INNER JOIN events e ON p.event_id = e.id
     LEFT  JOIN klien  k ON e.klien_id = k.id
     $where"
);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalData = (int) $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

// ── Data event ────────────────────────────────────────────────────────────────
$stmtEvents = $mysqli->prepare(
    "SELECT e.id, e.nama_event, e.tanggal_mulai, e.tanggal_selesai,
            e.lokasi, e.anggaran, e.status,
            k.nama AS nama_klien,
            p.role_dalam_event
     FROM penugasan p
     INNER JOIN events e ON p.event_id = e.id
     LEFT  JOIN klien  k ON e.klien_id = k.id
     $where
     ORDER BY e.tanggal_mulai ASC
     LIMIT ? OFFSET ?"
);
$paramsFull = array_merge($params, [$perPage, $offset]);
$stmtEvents->bind_param($types . 'ii', ...$paramsFull);
$stmtEvents->execute();
$daftarEvents = $stmtEvents->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEvents->close();

$baseUrl = BASE_URL . '/staff/events/index.php?'
         . ($filterStatus ? 'status=' . urlencode($filterStatus) . '&' : '')
         . ($filterCari   ? 'cari='   . urlencode($filterCari)   . '&' : '');

$labelStatus = [
    'draft'      => 'Draft',
    'aktif'      => 'Aktif',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

$pageTitle = 'Event Saya';
$page_type = 'staff';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-staff.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Event Saya</h1>
        <p class="page-subtitle">
          <?= $totalData ?> event yang kamu tangani
        </p>
      </div>
    </div>

    <?php foreach ($flashMessage as $type => $pesan) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
         role="alert">
      <span class="alert__text">
        <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>
    <?php endforeach; ?>

    <!-- Filter -->
    <form method="GET" action="" class="table-filter-bar">
      <div class="table-search">
        <span class="table-search__icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="1.8"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor"
                  stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </span>
        <input type="text" name="cari" class="table-search__input"
               placeholder="Cari nama event atau klien..."
               value="<?= htmlspecialchars($filterCari, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <select name="status" class="filter-select" aria-label="Filter status">
        <option value="">Semua Status</option>
        <?php foreach ($labelStatus as $val => $lbl) : ?>
        <option value="<?= $val ?>"
                <?= $filterStatus === $val ? 'selected' : '' ?>>
          <?= $lbl ?>
        </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
      <?php if ($filterStatus || $filterCari) : ?>
      <a href="<?= BASE_URL ?>/staff/events/index.php"
         class="btn btn--text btn--sm">Reset</a>
      <?php endif; ?>
      <span class="table-result-count"><?= $totalData ?> event</span>
    </form>

    <?php if (empty($daftarEvents)) : ?>
    <div class="empty-state">
      <div class="empty-state__icon" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
          <rect x="3" y="4" width="18" height="18" rx="2"
                stroke="currentColor" stroke-width="1.5"/>
          <path d="M3 10h18M8 2v4M16 2v4"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
      <p class="empty-state__text">
        <?= $filterStatus || $filterCari
            ? 'Tidak ada event yang cocok dengan filter.'
            : 'Kamu belum ditugaskan ke event manapun.' ?>
      </p>
      <?php if (!$filterStatus && !$filterCari) : ?>
      <p class="empty-state__hint">
        Hubungi Admin EO kamu untuk mendapatkan penugasan.
      </p>
      <?php endif; ?>
    </div>

    <?php else : ?>
    <!-- Grid kartu event -->
    <div class="events-staff-grid">
      <?php foreach ($daftarEvents as $ev) :
          $statusClass = $ev['status'];
          $isAktif     = in_array($ev['status'], ['draft', 'aktif'], true);
      ?>
      <div class="event-staff-card event-staff-card--<?= $statusClass ?>">

        <div class="event-staff-card__header">
          <div>
            <h2 class="event-staff-card__name">
              <a href="<?= BASE_URL ?>/staff/events/detail.php?id=<?= (int)$ev['id'] ?>"
                 class="table-link">
                <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </h2>
          </div>
          <span class="badge badge--<?= $statusClass ?>">
            <?= $labelStatus[$statusClass] ?>
          </span>
        </div>

        <div class="event-staff-card__meta">
          <!-- Tanggal -->
          <div class="event-staff-card__meta-item">
            <span class="event-staff-card__meta-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <rect x="3" y="4" width="18" height="18" rx="2"
                      stroke="currentColor" stroke-width="1.8"/>
                <path d="M3 10h18M8 2v4M16 2v4"
                      stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </span>
            <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?>
            &ndash;
            <?= date('d M Y', strtotime($ev['tanggal_selesai'])) ?>
          </div>

          <!-- Lokasi -->
          <?php if ($ev['lokasi']) : ?>
          <div class="event-staff-card__meta-item">
            <span class="event-staff-card__meta-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"
                      stroke="currentColor" stroke-width="1.8"/>
                <circle cx="12" cy="10" r="3"
                        stroke="currentColor" stroke-width="1.8"/>
              </svg>
            </span>
            <?= htmlspecialchars($ev['lokasi'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <?php endif; ?>

          <!-- Klien -->
          <?php if ($ev['nama_klien']) : ?>
          <div class="event-staff-card__meta-item">
            <span class="event-staff-card__meta-icon" aria-hidden="true">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="8" r="4"
                        stroke="currentColor" stroke-width="1.8"/>
                <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"
                      stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </span>
            <?= htmlspecialchars($ev['nama_klien'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="event-staff-card__footer">
          <!-- Badge peran staff dalam event -->
          <?php if ($ev['role_dalam_event']) : ?>
          <span class="role-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true">
              <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/>
              <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            <?= htmlspecialchars($ev['role_dalam_event'], ENT_QUOTES, 'UTF-8') ?>
          </span>
          <?php else : ?>
          <span class="text-muted" style="font-size:13px">Peran tidak ditentukan</span>
          <?php endif; ?>

          <div style="display:flex;gap:8px">
            <a href="<?= BASE_URL ?>/staff/events/detail.php?id=<?= (int)$ev['id'] ?>"
               class="btn btn--secondary btn--sm">
              Lihat Detail
            </a>
            <?php if ($isAktif) : ?>
            <a href="<?= BASE_URL ?>/staff/transaksi/tambah.php?event_id=<?= (int)$ev['id'] ?>"
               class="btn btn--primary btn--sm">
              + Transaksi
            </a>
            <?php endif; ?>
          </div>
        </div>

      </div>
      <?php endforeach; ?>
    </div>

    <?= pagination($totalData, $baseUrl, $page, $perPage) ?>
    <?php endif; ?>

  </div>
</div>

<?php
$page_type = 'staff';
require_once __DIR__ . '/../../includes/footer.php';
?>