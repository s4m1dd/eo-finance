<?php
/**
 * EO Finance — Daftar transaksi admin.
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

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Filter ────────────────────────────────────────────────────────────────────
$filterTipe    = $_GET['tipe']     ?? '';
$filterEventId = (int) ($_GET['event_id'] ?? 0);
$filterCari    = trim($_GET['cari'] ?? '');
$page          = max(1, (int) ($_GET['page'] ?? 1));
$perPage       = ITEMS_PER_PAGE;
$offset        = ($page - 1) * $perPage;

if (!in_array($filterTipe, ['pemasukan', 'pengeluaran'], true)) {
    $filterTipe = '';
}

// ── WHERE clause dinamis ──────────────────────────────────────────────────────
$where  = 'WHERE e.eo_id = ?';
$params = [$eoId];
$types  = 'i';

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
if ($filterCari !== '') {
    $where   .= ' AND (t.kategori LIKE ? OR e.nama_event LIKE ?)';
    $like     = '%' . $filterCari . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

// ── Total ─────────────────────────────────────────────────────────────────────
$stmtCount = $mysqli->prepare(
    "SELECT COUNT(*) AS total
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     $where"
);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalData = (int) $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

// ── Ringkasan pemasukan & pengeluaran (hanya yang sudah approved) ────────────
$stmtSum = $mysqli->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN t.tipe='pemasukan'   THEN t.jumlah ELSE 0 END),0) AS total_masuk,
        COALESCE(SUM(CASE WHEN t.tipe='pengeluaran' THEN t.jumlah ELSE 0 END),0) AS total_keluar
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     $where AND t.status_approval = 'approved'"
);
$stmtSum->bind_param($types, ...$params);
$stmtSum->execute();
$ringkasan = $stmtSum->get_result()->fetch_assoc();
$stmtSum->close();

// ── Jumlah transaksi yang masih menunggu approval (untuk badge tombol) ───────
$stmtPending = $mysqli->prepare(
    "SELECT COUNT(*) AS total FROM transaksi WHERE eo_id = ? AND status_approval = 'pending'"
);
$stmtPending->bind_param('i', $eoId);
$stmtPending->execute();
$totalPending = (int) $stmtPending->get_result()->fetch_assoc()['total'];
$stmtPending->close();

// ── Data transaksi ────────────────────────────────────────────────────────────
$stmtTrx = $mysqli->prepare(
    "SELECT t.id, t.tipe, t.kategori, t.jumlah, t.status_approval, t.catatan_approval, t.status_pembayaran,
            t.tanggal_transaksi, t.bukti_file,
            e.nama_event, e.id AS event_id,
            u.nama AS nama_user,
            v.nama AS nama_vendor
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     INNER JOIN users  u ON t.created_by = u.id
     LEFT  JOIN vendor v ON t.vendor_id  = v.id
     $where
     ORDER BY t.tanggal_transaksi DESC, t.created_at DESC
     LIMIT ? OFFSET ?"
);
$paramsFull   = array_merge($params, [$perPage, $offset]);
$typesFull    = $types . 'ii';
$stmtTrx->bind_param($typesFull, ...$paramsFull);
$stmtTrx->execute();
$transaksi = $stmtTrx->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTrx->close();

// ── Dropdown event untuk filter ───────────────────────────────────────────────
$stmtEvDrop = $mysqli->prepare(
    'SELECT id, nama_event FROM events WHERE eo_id = ? ORDER BY tanggal_mulai DESC'
);
$stmtEvDrop->bind_param('i', $eoId);
$stmtEvDrop->execute();
$daftarEvents = $stmtEvDrop->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEvDrop->close();

$baseUrl = BASE_URL . '/admin/transaksi/index.php?'
         . ($filterTipe    ? 'tipe='     . urlencode($filterTipe)          . '&' : '')
         . ($filterEventId ? 'event_id=' . $filterEventId                  . '&' : '')
         . ($filterCari    ? 'cari='     . urlencode($filterCari)          . '&' : '');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Transaksi';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Transaksi</h1>
        <p class="page-subtitle"><?= $totalData ?> transaksi ditemukan</p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/admin/transaksi/approval.php"
           class="btn btn--outline-danger" style="position:relative;">
          Approval Transaksi
          <?php if ($totalPending > 0) : ?>
          <span class="nav__badge" style="position:absolute;top:-8px;right:-8px;">
            <?= $totalPending ?>
          </span>
          <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/admin/transaksi/tambah.php"
           class="btn btn--primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          Tambah Transaksi
        </a>
      </div>
    </div>

    <?php foreach ($flashMessage as $type => $pesan) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
      <span class="alert__text"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endforeach; ?>

    <!-- Ringkasan -->
    <div class="trx-summary">
      <div class="trx-summary__item">
        <span class="trx-summary__label">Total Pemasukan <small>(disetujui)</small></span>
        <span class="trx-summary__value trx-summary__value--green">
          <?= format_rupiah($ringkasan['total_masuk']) ?>
        </span>
      </div>
      <div class="trx-summary__item">
        <span class="trx-summary__label">Total Pengeluaran <small>(disetujui)</small></span>
        <span class="trx-summary__value trx-summary__value--red">
          <?= format_rupiah($ringkasan['total_keluar']) ?>
        </span>
      </div>
      <div class="trx-summary__item">
        <span class="trx-summary__label">Selisih</span>
        <span class="trx-summary__value
          <?= ((float)$ringkasan['total_masuk'] - (float)$ringkasan['total_keluar']) >= 0
              ? 'trx-summary__value--green' : 'trx-summary__value--red' ?>">
          <?= format_rupiah((float)$ringkasan['total_masuk'] - (float)$ringkasan['total_keluar']) ?>
        </span>
      </div>
    </div>
    <?php if ($totalPending > 0) : ?>
    <p class="page-subtitle" style="margin-top:-8px;margin-bottom:var(--space-4);">
      <?= $totalPending ?> transaksi belum dihitung di atas karena masih
      <a href="<?= BASE_URL ?>/admin/transaksi/approval.php">menunggu persetujuan</a>.
    </p>
    <?php endif; ?>

    <!-- Filter -->
    <form method="GET" action="" class="table-filter-bar">
      <div class="table-search">
        <span class="table-search__icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="1.8"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
        </span>
        <input type="text" name="cari" class="table-search__input"
               placeholder="Cari kategori atau event..."
               value="<?= htmlspecialchars($filterCari, ENT_QUOTES, 'UTF-8') ?>">
      </div>

      <select name="tipe" class="filter-select" aria-label="Filter tipe">
        <option value="">Semua Tipe</option>
        <option value="pemasukan"   <?= $filterTipe === 'pemasukan'   ? 'selected' : '' ?>>Pemasukan</option>
        <option value="pengeluaran" <?= $filterTipe === 'pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option>
      </select>

      <select name="event_id" class="filter-select" aria-label="Filter event">
        <option value="">Semua Event</option>
        <?php foreach ($daftarEvents as $ev) : ?>
        <option value="<?= (int)$ev['id'] ?>"
                <?= $filterEventId === (int)$ev['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
      <?php if ($filterTipe || $filterEventId || $filterCari) : ?>
      <a href="<?= BASE_URL ?>/admin/transaksi/index.php"
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
        <?= $filterTipe || $filterEventId || $filterCari
            ? 'Tidak ada transaksi yang cocok dengan filter.'
            : 'Belum ada transaksi.' ?>
      </p>
      <?php if (!$filterTipe && !$filterEventId && !$filterCari) : ?>
      <a href="<?= BASE_URL ?>/admin/transaksi/tambah.php" class="btn btn--primary">
        Catat Transaksi Pertama
      </a>
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
          <?php foreach ($transaksi as $trx) : ?>
          <tr>
            <td class="data-table__cell">
              <time datetime="<?= $trx['tanggal_transaksi'] ?>">
                <?= date('d M Y', strtotime($trx['tanggal_transaksi'])) ?>
              </time>
            </td>
            <td class="data-table__cell data-table__cell--primary">
              <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$trx['event_id'] ?>"
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
            <td class="data-table__cell">
              <?= $trx['nama_vendor']
                  ? htmlspecialchars($trx['nama_vendor'], ENT_QUOTES, 'UTF-8')
                  : '<span class="text-muted">—</span>' ?>
            </td>
            <td class="data-table__cell">
              <?= htmlspecialchars($trx['nama_user'], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td class="data-table__cell">
              <?php if ($trx['bukti_file']) : ?>
              <a href="<?= BASE_URL . '/serve-bukti.php?id=' . (int)$trx['id'] ?>"
                 target="_blank" rel="noopener" class="trx-bukti">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                        stroke="currentColor" stroke-width="1.8"/>
                  <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="1.8"/>
                </svg>
                Lihat
              </a>
              <?php else : ?>
              <span class="trx-bukti--none">—</span>
              <?php endif; ?>
            </td>
            <td class="data-table__cell data-table__cell--actions">
              <a href="<?= BASE_URL ?>/admin/transaksi/detail.php?id=<?= (int)$trx['id'] ?>"
                 class="btn-icon" title="Detail / Cetak Struk">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="1.8"/>
                  <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round"/>
                  <path d="M11 8v6M8 11h6" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round"/>
                </svg>
              </a>
              <a href="<?= BASE_URL ?>/admin/transaksi/edit.php?id=<?= (int)$trx['id'] ?>"
                 class="btn-icon" title="Edit">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </a>
              <form method="POST"
                    action="<?= BASE_URL ?>/proses/admin/proses-transaksi.php"
                    onsubmit="return confirm('Hapus transaksi ini?')">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="hapus">
                <input type="hidden" name="id" value="<?= (int)$trx['id'] ?>">
                <button type="submit" class="btn-icon" title="Hapus"
                        style="color:var(--danger)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <polyline points="3 6 5 6 21 6"
                              stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"
                          stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M10 11v6M14 11v6M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"
                          stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
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