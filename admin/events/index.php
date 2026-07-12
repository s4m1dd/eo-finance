<?php
/**
 * EO Finance — Daftar event admin.
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

require_login();
require_role('admin_eo');

$eoId = get_current_eo_id();

// ── Flash message ─────────────────────────────────────────────────────────────
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

// ── Bangun WHERE clause ───────────────────────────────────────────────────────
$where  = 'WHERE e.eo_id = ?';
$params = [$eoId];
$types  = 'i';

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

// ── Total data untuk pagination ───────────────────────────────────────────────
$stmtCount = $mysqli->prepare(
    "SELECT COUNT(*) AS total
     FROM events e
     LEFT JOIN klien k ON e.klien_id = k.id
     $where"
);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalData = (int) $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

// ── Query data ────────────────────────────────────────────────────────────────
$stmtEvents = $mysqli->prepare(
    "SELECT e.id, e.nama_event, e.tanggal_mulai, e.tanggal_selesai,
            e.anggaran, e.status, k.nama AS nama_klien
     FROM events e
     LEFT JOIN klien k ON e.klien_id = k.id
     $where
     ORDER BY e.tanggal_mulai DESC
     LIMIT ? OFFSET ?"
);
$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';
$stmtEvents->bind_param($types, ...$params);
$stmtEvents->execute();
$events = $stmtEvents->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEvents->close();

$baseUrl    = BASE_URL . '/admin/events/index.php?'
            . ($filterStatus ? 'status=' . urlencode($filterStatus) . '&' : '')
            . ($filterCari   ? 'cari='   . urlencode($filterCari)   . '&' : '');

$labelStatus = [
    'draft'      => 'Draft',
    'aktif'      => 'Aktif',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

$pageTitle = 'Event';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Event</h1>
        <p class="page-subtitle">
          <?= $totalData ?> event ditemukan
          <?= $filterStatus ? ' · filter: ' . htmlspecialchars($labelStatus[$filterStatus], ENT_QUOTES, 'UTF-8') : '' ?>
        </p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/admin/events/kalender.php"
           class="btn btn--outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <rect x="3" y="4" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.8" fill="none"/>
            <path d="M3 10h18M8 2v4M16 2v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          Kalender
        </a>
        <a href="<?= BASE_URL ?>/admin/events/tambah.php"
           class="btn btn--primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
               aria-hidden="true">
            <path d="M12 5v14M5 12h14" stroke="currentColor"
                  stroke-width="2" stroke-linecap="round"/>
          </svg>
          Buat Event
        </a>
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

    <!-- Filter bar -->
    <div class="table-filter-bar">
      <form method="GET" action="" class="table-filter-bar"
            style="flex:1;gap:12px;display:flex;flex-wrap:wrap;align-items:center;">
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
        <a href="<?= BASE_URL ?>/admin/events/index.php"
           class="btn btn--text btn--sm">Reset</a>
        <?php endif; ?>
      </form>
      <span class="table-result-count"><?= $totalData ?> data</span>
    </div>

    <?php if (empty($events)) : ?>
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
        <?= $filterStatus || $filterCari ? 'Tidak ada event yang cocok dengan filter.' : 'Belum ada event.' ?>
      </p>
      <?php if (!$filterStatus && !$filterCari) : ?>
      <a href="<?= BASE_URL ?>/admin/events/tambah.php" class="btn btn--primary">
        Buat Event Pertama
      </a>
      <?php endif; ?>
    </div>

    <?php else : ?>
    <div class="table-wrap">
      <table class="data-table" aria-label="Daftar event">
        <thead>
          <tr>
            <th scope="col">Nama Event</th>
            <th scope="col">Tanggal</th>
            <th scope="col">Klien</th>
            <th scope="col">Anggaran</th>
            <th scope="col">Status</th>
            <th scope="col"><span class="sr-only">Aksi</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($events as $ev) : ?>
          <tr>
            <td class="data-table__cell data-table__cell--primary">
              <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$ev['id'] ?>"
                 class="table-link">
                <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </td>
            <td class="data-table__cell">
              <span class="date-range">
                <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?>
                <span class="date-range__sep" aria-hidden="true">–</span>
                <?= date('d M Y', strtotime($ev['tanggal_selesai'])) ?>
              </span>
            </td>
            <td class="data-table__cell">
              <?= $ev['nama_klien']
                  ? htmlspecialchars($ev['nama_klien'], ENT_QUOTES, 'UTF-8')
                  : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="data-table__cell data-table__cell--amount">
              <?= format_rupiah($ev['anggaran']) ?>
            </td>
            <td class="data-table__cell">
              <span class="badge badge--<?= $ev['status'] ?>">
                <?= $labelStatus[$ev['status']] ?? $ev['status'] ?>
              </span>
            </td>
            <td class="data-table__cell data-table__cell--actions">
              <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$ev['id'] ?>"
                 class="btn-icon" title="Detail">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                        stroke="currentColor" stroke-width="1.8"/>
                  <circle cx="12" cy="12" r="3"
                          stroke="currentColor" stroke-width="1.8"/>
                </svg>
              </a>
              <a href="<?= BASE_URL ?>/admin/events/edit.php?id=<?= (int)$ev['id'] ?>"
                 class="btn-icon" title="Edit">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </a>
              <form method="POST"
                    action="<?= BASE_URL ?>/proses/admin/proses-event.php"
                    onsubmit="return confirm('Hapus event ini? Semua transaksi dan penugasan terkait juga akan terhapus.')">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= (int)$ev['id'] ?>">
                <button type="submit" class="btn-icon" title="Hapus"
                        style="color:var(--danger)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <polyline points="3 6 5 6 21 6"
                              stroke="currentColor" stroke-width="1.8"
                              stroke-linecap="round"/>
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"
                          stroke="currentColor" stroke-width="1.8"
                          stroke-linecap="round"/>
                    <path d="M10 11v6M14 11v6M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"
                          stroke="currentColor" stroke-width="1.8"
                          stroke-linecap="round"/>
                  </svg>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?= pagination($totalData, $baseUrl, $page, $perPage) ?>
    <?php endif; ?>

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>