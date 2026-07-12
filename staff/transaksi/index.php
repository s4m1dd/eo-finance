<?php
/**
 * EO Finance — Daftar transaksi staff.
 *
 * Staff hanya melihat transaksi dari event yang ditugaskan kepadanya.
 * Tidak bisa menghapus — hanya bisa mengedit transaksi yang dia sendiri buat.
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

if ((int) ($_SESSION['first_login'] ?? 0) === 1) {
    $_SESSION['__flash']['warning'] = 'Silakan ganti password terlebih dahulu.';
    header('Location: ' . BASE_URL . '/staff/ganti-password.php');
    exit();
}

$userId = get_current_user_id();
$eoId   = get_current_eo_id();

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Filter ────────────────────────────────────────────────────────────────────
$filterTipe    = $_GET['tipe']     ?? '';
$filterEventId = (int) ($_GET['event_id'] ?? 0);
$page          = max(1, (int) ($_GET['page'] ?? 1));
$perPage       = ITEMS_PER_PAGE;
$offset        = ($page - 1) * $perPage;

if (!in_array($filterTipe, ['pemasukan', 'pengeluaran'], true)) {
    $filterTipe = '';
}

// ── WHERE: hanya transaksi dari event yang staff ini ditugaskan ───────────────
// JOIN ke penugasan memastikan staff tidak bisa melihat transaksi
// event yang bukan tugasannya meski eo_id sama.
$where  = 'WHERE p.user_id = ? AND t.eo_id = ?';
$params = [$userId, $eoId];
$types  = 'ii';

if ($filterTipe !== '') {
    $where   .= ' AND t.tipe = ?';
    $params[] = $filterTipe;
    $types   .= 's';
}
if ($filterEventId > 0) {
    $where   .= ' AND t.event_id = ?';
    $params[] = $filterEventId;
    $types   .= 'i';
}

// ── Total ─────────────────────────────────────────────────────────────────────
$stmtCount = $mysqli->prepare(
    "SELECT COUNT(*) AS total
     FROM transaksi t
     INNER JOIN penugasan p ON p.event_id = t.event_id AND p.eo_id = t.eo_id
     $where"
);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalData = (int) $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

// ── Ringkasan pemasukan & pengeluaran sesuai filter (hanya yang approved) ────
$stmtSum = $mysqli->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN t.tipe='pemasukan'   THEN t.jumlah ELSE 0 END),0) AS total_masuk,
        COALESCE(SUM(CASE WHEN t.tipe='pengeluaran' THEN t.jumlah ELSE 0 END),0) AS total_keluar
     FROM transaksi t
     INNER JOIN penugasan p ON p.event_id = t.event_id AND p.eo_id = t.eo_id
     $where AND t.status_approval = 'approved'"
);
$stmtSum->bind_param($types, ...$params);
$stmtSum->execute();
$ringkasan = $stmtSum->get_result()->fetch_assoc();
$stmtSum->close();

// ── Jumlah transaksi milik staff ini yang masih menunggu approval ────────────
$stmtPendingMine = $mysqli->prepare(
    "SELECT COUNT(*) AS total FROM transaksi WHERE created_by = ? AND eo_id = ? AND status_approval = 'pending'"
);
$stmtPendingMine->bind_param('ii', $userId, $eoId);
$stmtPendingMine->execute();
$totalPendingMine = (int) $stmtPendingMine->get_result()->fetch_assoc()['total'];
$stmtPendingMine->close();

// ── Data transaksi ────────────────────────────────────────────────────────────
$stmtTrx = $mysqli->prepare(
    "SELECT t.id, t.tipe, t.kategori, t.jumlah, t.status_approval, t.catatan_approval, t.status_pembayaran,
            t.tanggal_transaksi, t.bukti_file,
            t.created_by,
            e.id AS event_id, e.nama_event,
            u.nama AS nama_user,
            v.nama AS nama_vendor
     FROM transaksi t
     INNER JOIN penugasan p ON p.event_id = t.event_id AND p.eo_id = t.eo_id
     INNER JOIN events e ON t.event_id = e.id
     INNER JOIN users  u ON t.created_by = u.id
     LEFT  JOIN vendor v ON t.vendor_id  = v.id
     $where
     ORDER BY t.tanggal_transaksi DESC, t.created_at DESC
     LIMIT ? OFFSET ?"
);
$paramsFull = array_merge($params, [$perPage, $offset]);
$stmtTrx->bind_param($types . 'ii', ...$paramsFull);
$stmtTrx->execute();
$transaksi = $stmtTrx->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTrx->close();

// ── Dropdown event yang ditugaskan (untuk filter) ─────────────────────────────
$stmtEvDrop = $mysqli->prepare(
    "SELECT e.id, e.nama_event
     FROM events e
     INNER JOIN penugasan p ON p.event_id = e.id AND p.eo_id = e.eo_id
     WHERE p.user_id = ? AND e.eo_id = ?
     ORDER BY e.tanggal_mulai DESC"
);
$stmtEvDrop->bind_param('ii', $userId, $eoId);
$stmtEvDrop->execute();
$daftarEvents = $stmtEvDrop->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEvDrop->close();

$baseUrl = BASE_URL . '/staff/transaksi/index.php?'
         . ($filterTipe    ? 'tipe='     . urlencode($filterTipe)    . '&' : '')
         . ($filterEventId ? 'event_id=' . $filterEventId            . '&' : '');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Transaksi';
$page_type = 'staff';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-staff.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Transaksi</h1>
        <p class="page-subtitle"><?= $totalData ?> transaksi ditemukan</p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/staff/transaksi/tambah.php"
           class="btn btn--primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 5v14M5 12h14" stroke="currentColor"
                  stroke-width="2" stroke-linecap="round"/>
          </svg>
          Catat Transaksi
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

    <!-- Ringkasan -->
    <div class="trx-staff-summary">
      <div class="trx-staff-summary__item">
        <span class="trx-staff-summary__label">Pemasukan <small>(disetujui)</small></span>
        <span class="trx-staff-summary__value trx-staff-summary__value--green">
          <?= format_rupiah($ringkasan['total_masuk']) ?>
        </span>
      </div>
      <div class="trx-staff-summary__item">
        <span class="trx-staff-summary__label">Pengeluaran <small>(disetujui)</small></span>
        <span class="trx-staff-summary__value trx-staff-summary__value--red">
          <?= format_rupiah($ringkasan['total_keluar']) ?>
        </span>
      </div>
      <div class="trx-staff-summary__item">
        <span class="trx-staff-summary__label">Selisih</span>
        <?php $selisih = (float)$ringkasan['total_masuk'] - (float)$ringkasan['total_keluar']; ?>
        <span class="trx-staff-summary__value
                     <?= $selisih >= 0
                         ? 'trx-staff-summary__value--green'
                         : 'trx-staff-summary__value--red' ?>">
          <?= format_rupiah($selisih) ?>
        </span>
      </div>
    </div>
    <?php if ($totalPendingMine > 0) : ?>
    <p class="page-subtitle" style="margin-bottom:var(--space-4);">
      <?= $totalPendingMine ?> transaksi kamu masih menunggu persetujuan admin.
    </p>
    <?php endif; ?>

    <!-- Filter -->
    <form method="GET" action="" class="table-filter-bar">
      <select name="tipe" class="filter-select" aria-label="Filter tipe transaksi">
        <option value="">Semua Tipe</option>
        <option value="pemasukan"
                <?= $filterTipe === 'pemasukan'   ? 'selected' : '' ?>>Pemasukan</option>
        <option value="pengeluaran"
                <?= $filterTipe === 'pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option>
      </select>

      <select name="event_id" class="filter-select" aria-label="Filter event">
        <option value="0">Semua Event</option>
        <?php foreach ($daftarEvents as $ev) : ?>
        <option value="<?= (int)$ev['id'] ?>"
                <?= $filterEventId === (int)$ev['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
      <?php if ($filterTipe || $filterEventId) : ?>
      <a href="<?= BASE_URL ?>/staff/transaksi/index.php"
         class="btn btn--text btn--sm">Reset</a>
      <?php endif; ?>
      <span class="table-result-count"><?= $totalData ?> data</span>
    </form>

    <?php if (empty($transaksi)) : ?>
    <div class="empty-state">
      <div class="empty-state__icon" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
          <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
      <p class="empty-state__text">
        <?= $filterTipe || $filterEventId
            ? 'Tidak ada transaksi yang cocok.'
            : 'Belum ada transaksi di event yang kamu tangani.' ?>
      </p>
      <?php if (!$filterTipe && !$filterEventId) : ?>
      <a href="<?= BASE_URL ?>/staff/transaksi/tambah.php"
         class="btn btn--primary">Catat Transaksi Pertama</a>
      <?php endif; ?>
    </div>

    <?php else : ?>
    <div class="table-wrap">
      <table class="data-table" aria-label="Daftar transaksi">
        <thead>
          <tr>
            <th scope="col">Tanggal</th>
            <th scope="col">Event</th>
            <th scope="col">Tipe</th>
            <th scope="col">Kategori</th>
            <th scope="col">Jumlah</th>
            <th scope="col">Status</th>
            <th scope="col">Vendor</th>
            <th scope="col">Dicatat oleh</th>
            <th scope="col">Bukti</th>
            <th scope="col"><span class="sr-only">Aksi</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($transaksi as $trx) :
              // Staff hanya bisa edit transaksi yang dia sendiri buat
              $bisaEdit = (int)$trx['created_by'] === $userId;
          ?>
          <tr>
            <td class="data-table__cell">
              <time datetime="<?= $trx['tanggal_transaksi'] ?>">
                <?= date('d M Y', strtotime($trx['tanggal_transaksi'])) ?>
              </time>
            </td>
            <td class="data-table__cell data-table__cell--primary">
              <a href="<?= BASE_URL ?>/staff/events/detail.php?id=<?= (int)$trx['event_id'] ?>"
                 class="table-link">
                <?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?>
              </a>
            </td>
            <td class="data-table__cell">
              <span class="badge badge--<?= $trx['tipe'] ?>">
                <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
              </span>
            </td>
            <td class="data-table__cell">
              <?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td class="data-table__cell data-table__cell--amount
                       <?= $trx['tipe'] === 'pemasukan' ? 'text-green' : 'text-red' ?>">
              <?= $trx['tipe'] === 'pemasukan' ? '+' : '−' ?>
              <?= format_rupiah($trx['jumlah']) ?>
            </td>
            <td class="data-table__cell">
              <?= badge_status_approval($trx['status_approval']) ?>
              <?php if ($trx['status_pembayaran'] === 'belum_lunas') : ?>
              <?= badge_status_pembayaran('belum_lunas', $trx['tipe']) ?>
              <?php endif; ?>
              <?php if ($trx['status_approval'] === 'rejected' && !empty($trx['catatan_approval'])) : ?>
              <div class="text-muted" style="font-size:12px;margin-top:4px;max-width:180px;">
                <?= htmlspecialchars($trx['catatan_approval'], ENT_QUOTES, 'UTF-8') ?>
              </div>
              <?php endif; ?>
            </td>
            <td class="data-table__cell text-muted">
              <?= $trx['nama_vendor']
                  ? htmlspecialchars($trx['nama_vendor'], ENT_QUOTES, 'UTF-8')
                  : '—' ?>
            </td>
            <td class="data-table__cell text-muted">
              <?= htmlspecialchars($trx['nama_user'], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td class="data-table__cell">
              <?php if ($trx['bukti_file']) : ?>
              <a href="<?= BASE_URL . '/serve-bukti.php?id=' . (int)$trx['id'] ?>"
                 target="_blank" rel="noopener" class="trx-bukti">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                        stroke="currentColor" stroke-width="1.8"/>
                  <polyline points="14 2 14 8 20 8"
                            stroke="currentColor" stroke-width="1.8"/>
                </svg>
                Lihat
              </a>
              <?php else : ?>
              <span class="trx-bukti--none">—</span>
              <?php endif; ?>
            </td>
            <td class="data-table__cell data-table__cell--actions">
              <a href="<?= BASE_URL ?>/staff/transaksi/detail.php?id=<?= (int)$trx['id'] ?>"
                 class="btn-icon" title="Detail / Cetak Struk">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="1.8"/>
                  <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round"/>
                  <path d="M11 8v6M8 11h6" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round"/>
                </svg>
              </a>
              <?php if ($bisaEdit) : ?>
              <a href="<?= BASE_URL ?>/staff/transaksi/edit.php?id=<?= (int)$trx['id'] ?>"
                 class="btn-icon" title="Edit transaksi">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </a>
              <?php else : ?>
              <span class="text-muted" style="font-size:12px"
                    title="Hanya pembuat transaksi yang bisa mengedit">—</span>
              <?php endif; ?>
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
$page_type = 'staff';
require_once __DIR__ . '/../../includes/footer.php';
?>