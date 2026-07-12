<?php
/**
 * EO Finance — Laporan keuangan admin.
 *
 * Menampilkan ringkasan keuangan per event atau per periode,
 * dengan filter event, bulan, dan tahun.
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
require_role('admin_eo');

$eoId = get_current_eo_id();

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Parameter filter ──────────────────────────────────────────────────────────
$filterEventId = (int) ($_GET['event_id'] ?? 0);
$filterBulan   = (int) ($_GET['bulan']    ?? 0);
$filterTahun   = (int) ($_GET['tahun']    ?? date('Y'));

if ($filterBulan < 0 || $filterBulan > 12) $filterBulan = 0;
if ($filterTahun < 2000 || $filterTahun > 2100) $filterTahun = (int) date('Y');

// ── Dropdown events milik EO ──────────────────────────────────────────────────
$stmtEv = $mysqli->prepare(
    'SELECT id, nama_event, status FROM events
     WHERE eo_id = ? ORDER BY tanggal_mulai DESC'
);
$stmtEv->bind_param('i', $eoId);
$stmtEv->execute();
$daftarEvents = $stmtEv->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEv->close();

// ── Bangun WHERE untuk query transaksi ────────────────────────────────────────
$where  = "WHERE e.eo_id = ? AND t.status_approval = 'approved'";
$params = [$eoId];
$types  = 'i';

if ($filterEventId > 0) {
    $where   .= ' AND t.event_id = ?';
    $params[] = $filterEventId;
    $types   .= 'i';
}
if ($filterBulan > 0) {
    $where   .= ' AND MONTH(t.tanggal_transaksi) = ?';
    $params[] = $filterBulan;
    $types   .= 'i';
}
if ($filterTahun > 0) {
    $where   .= ' AND YEAR(t.tanggal_transaksi) = ?';
    $params[] = $filterTahun;
    $types   .= 'i';
}

// ── Ringkasan total ───────────────────────────────────────────────────────────
$stmtRingkasan = $mysqli->prepare(
    "SELECT
        COUNT(t.id) AS total_transaksi,
        COALESCE(SUM(CASE WHEN t.tipe='pemasukan'   THEN t.jumlah ELSE 0 END), 0) AS total_pemasukan,
        COALESCE(SUM(CASE WHEN t.tipe='pengeluaran' THEN t.jumlah ELSE 0 END), 0) AS total_pengeluaran
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     $where"
);
$stmtRingkasan->bind_param($types, ...$params);
$stmtRingkasan->execute();
$ringkasan = $stmtRingkasan->get_result()->fetch_assoc();
$stmtRingkasan->close();

$totalPemasukan   = (float) $ringkasan['total_pemasukan'];
$totalPengeluaran = (float) $ringkasan['total_pengeluaran'];
$totalProfit      = $totalPemasukan - $totalPengeluaran;

// ── Rekapitulasi per event ────────────────────────────────────────────────────
// Bangun kondisi JOIN untuk filter bulan/tahun pada LEFT JOIN transaksi
$joinCond = "t.status_approval = 'approved'";
if ($filterBulan > 0) {
    $joinCond = 'MONTH(t.tanggal_transaksi) = ' . $filterBulan . ' AND ' . $joinCond;
}
if ($filterTahun > 0) {
    $joinCond = 'YEAR(t.tanggal_transaksi) = ' . $filterTahun . ' AND ' . $joinCond;
}

$stmtPerEvent = $mysqli->prepare(
    "SELECT
        e.id, e.nama_event, e.anggaran, e.status,
        COALESCE(SUM(CASE WHEN t.tipe='pemasukan'   THEN t.jumlah ELSE 0 END), 0) AS pemasukan,
        COALESCE(SUM(CASE WHEN t.tipe='pengeluaran' THEN t.jumlah ELSE 0 END), 0) AS pengeluaran,
        COUNT(t.id) AS jumlah_transaksi
     FROM events e
     LEFT JOIN transaksi t ON t.event_id = e.id
        AND ($joinCond)
     WHERE e.eo_id = ?
       " . ($filterEventId > 0 ? 'AND e.id = ?' : '') . "
     GROUP BY e.id
     ORDER BY e.tanggal_mulai DESC"
);

// Param sederhana: eoId + opsional filterEventId
if ($filterEventId > 0) {
    $stmtPerEvent->bind_param('ii', $eoId, $filterEventId);
} else {
    $stmtPerEvent->bind_param('i', $eoId);
}
$stmtPerEvent->execute();
$rekapEvent = $stmtPerEvent->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPerEvent->close();

// ── Transaksi per kategori (agregasi) ────────────────────────────────────────
$stmtKategori = $mysqli->prepare(
    "SELECT t.tipe, t.kategori,
            COUNT(t.id) AS jumlah,
            SUM(t.jumlah) AS total
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     $where
     GROUP BY t.tipe, t.kategori
     ORDER BY t.tipe ASC, total DESC"
);
$stmtKategori->bind_param($types, ...$params);
$stmtKategori->execute();
$rekapKategori = $stmtKategori->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtKategori->close();

// ── Daftar transaksi detail (untuk tabel laporan) ─────────────────────────────
$stmtDetail = $mysqli->prepare(
    "SELECT t.id, t.tipe, t.kategori, t.jumlah,
            t.tanggal_transaksi, t.deskripsi, t.bukti_file,
            e.nama_event,
            u.nama AS nama_user,
            v.nama AS nama_vendor
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     INNER JOIN users  u ON t.created_by = u.id
     LEFT  JOIN vendor v ON t.vendor_id  = v.id
     $where
     ORDER BY t.tanggal_transaksi DESC, t.created_at DESC"
);
$stmtDetail->bind_param($types, ...$params);
$stmtDetail->execute();
$detailTrx = $stmtDetail->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtDetail->close();

// ── Data untuk chartLaporan: Pemasukan vs Pengeluaran per Event ───────────────
$laporanLabels         = array_column($rekapEvent, 'nama_event');
$laporanDataPemasukan  = array_map('floatval', array_column($rekapEvent, 'pemasukan'));
$laporanDataPengeluaran = array_map('floatval', array_column($rekapEvent, 'pengeluaran'));

// ── Data untuk chartKategoriLaporan: Pengeluaran per Kategori (donut) ─────────
// Hanya ambil baris tipe 'pengeluaran' — donut proporsi paling berguna untuk
// lihat kategori mana yang paling banyak makan biaya, bukan campur pemasukan.
$kategoriLaporanLabels = [];
$kategoriLaporanValues = [];
foreach ($rekapKategori as $rk) {
    if ($rk['tipe'] === 'pengeluaran') {
        $kategoriLaporanLabels[] = $rk['kategori'];
        $kategoriLaporanValues[] = (float) $rk['total'];
    }
}

// ── Data untuk dikirim ke export ──────────────────────────────────────────────
$queryStringExport = http_build_query([
    'event_id' => $filterEventId,
    'bulan'    => $filterBulan,
    'tahun'    => $filterTahun,
    'format'   => 'csv',
]);

$labelStatus = [
    'draft'=>'Draft','aktif'=>'Aktif',
    'selesai'=>'Selesai','dibatalkan'=>'Dibatalkan',
];

$namaBulan = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
    5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
    9=>'September',10=>'Oktober',11=>'November',12=>'Desember',
];

$periodeLabel = ($filterBulan > 0 ? $namaBulan[$filterBulan] . ' ' : '') . $filterTahun;

$pageTitle = 'Laporan';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <!-- Header -->
    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Laporan Keuangan</h1>
        <p class="page-subtitle">Periode: <?= htmlspecialchars($periodeLabel, ENT_QUOTES, 'UTF-8') ?>
          <br><small>Hanya menghitung transaksi yang sudah disetujui admin.
          <a href="<?= BASE_URL ?>/admin/transaksi/approval.php">Lihat transaksi menunggu persetujuan →</a></small>
        </p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/admin/laporan/export.php?<?= $queryStringExport ?>&format=csv"
           class="btn btn--secondary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"
                  stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <polyline points="7 10 12 15 17 10"
                      stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <line x1="12" y1="15" x2="12" y2="3"
                  stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          Export CSV
        </a>
        <a href="<?= BASE_URL ?>/admin/laporan/export.php?<?= $queryStringExport ?>&format=pdf"
           class="btn btn--primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                  stroke="currentColor" stroke-width="1.8"/>
            <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="1.8"/>
          </svg>
          Export PDF
        </a>
      </div>
    </div>

    <?php foreach ($flashMessage as $type => $pesan) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
      <span class="alert__text"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endforeach; ?>

    <!-- Filter -->
    <div class="laporan-filter">
      <form method="GET" action="" style="display:contents">

        <div class="laporan-filter__group">
          <label class="laporan-filter__label" for="filter_event">Event</label>
          <select id="filter_event" name="event_id" class="form-input filter-select">
            <option value="0">Semua Event</option>
            <?php foreach ($daftarEvents as $ev) : ?>
            <option value="<?= (int)$ev['id'] ?>"
                    <?= $filterEventId === (int)$ev['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="laporan-filter__group">
          <label class="laporan-filter__label" for="filter_bulan">Bulan</label>
          <select id="filter_bulan" name="bulan" class="form-input filter-select">
            <option value="0">Semua Bulan</option>
            <?php foreach ($namaBulan as $num => $nama) : ?>
            <option value="<?= $num ?>"
                    <?= $filterBulan === $num ? 'selected' : '' ?>>
              <?= $nama ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="laporan-filter__group">
          <label class="laporan-filter__label" for="filter_tahun">Tahun</label>
          <select id="filter_tahun" name="tahun" class="form-input filter-select">
            <?php for ($y = (int)date('Y'); $y >= 2024; $y--) : ?>
            <option value="<?= $y ?>" <?= $filterTahun === $y ? 'selected' : '' ?>>
              <?= $y ?>
            </option>
            <?php endfor; ?>
          </select>
        </div>

        <div class="laporan-filter__group" style="align-self:flex-end">
          <button type="submit" class="btn btn--primary">Tampilkan</button>
        </div>

      </form>
    </div>

    <!-- Kartu ringkasan -->
    <div class="laporan-summary">
      <div class="laporan-summary-card">
        <p class="laporan-summary-card__label">Total Pemasukan</p>
        <p class="laporan-summary-card__value laporan-summary-card__value--green">
          <?= format_rupiah($totalPemasukan) ?>
        </p>
      </div>
      <div class="laporan-summary-card">
        <p class="laporan-summary-card__label">Total Pengeluaran</p>
        <p class="laporan-summary-card__value laporan-summary-card__value--red">
          <?= format_rupiah($totalPengeluaran) ?>
        </p>
      </div>
      <div class="laporan-summary-card">
        <p class="laporan-summary-card__label">Profit / Selisih</p>
        <p class="laporan-summary-card__value
                  <?= $totalProfit >= 0
                      ? 'laporan-summary-card__value--green'
                      : 'laporan-summary-card__value--red' ?>">
          <?= format_rupiah($totalProfit) ?>
        </p>
      </div>
    </div>

    <!-- Grafik: Pemasukan vs Pengeluaran per Event -->
    <?php if (!empty($rekapEvent)) : ?>
    <div class="chart-card" style="margin-bottom:var(--space-5)">
      <div class="chart-card__header">
        <div>
          <h2 class="chart-card__title">Pemasukan vs Pengeluaran per Event</h2>
          <span class="chart-card__subtitle"><?= htmlspecialchars($periodeLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
      <div class="chart-card__body">
        <canvas id="chartLaporan" role="img"
                aria-label="Grafik pemasukan dan pengeluaran per event"></canvas>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tabel rekapitulasi per event -->
    <?php if (!empty($rekapEvent)) : ?>
    <section aria-labelledby="rekap-event-title">
      <div class="section-header-bar">
        <h2 class="section-header-bar__title" id="rekap-event-title">
          Rekapitulasi per Event
        </h2>
      </div>
      <div class="table-wrap">
        <table class="data-table" aria-label="Rekapitulasi keuangan per event">
          <thead>
            <tr>
              <th scope="col">Event</th>
              <th scope="col">Status</th>
              <th scope="col">Anggaran</th>
              <th scope="col">Pemasukan</th>
              <th scope="col">Pengeluaran</th>
              <th scope="col">Profit</th>
              <th scope="col">Serapan</th>
              <th scope="col">Transaksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rekapEvent as $re) :
                $profit   = (float)$re['pemasukan'] - (float)$re['pengeluaran'];
                $serapan  = (float)$re['anggaran'] > 0
                            ? round(((float)$re['pengeluaran'] / (float)$re['anggaran']) * 100, 1)
                            : 0;
                $warna    = $serapan > 90 ? 'merah' : ($serapan > 70 ? 'kuning' : 'hijau');
            ?>
            <tr>
              <td class="data-table__cell data-table__cell--primary">
                <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$re['id'] ?>"
                   class="table-link">
                  <?= htmlspecialchars($re['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </td>
              <td class="data-table__cell">
                <span class="badge badge--<?= $re['status'] ?>">
                  <?= $labelStatus[$re['status']] ?? $re['status'] ?>
                </span>
              </td>
              <td class="data-table__cell data-table__cell--amount">
                <?= format_rupiah($re['anggaran']) ?>
              </td>
              <td class="data-table__cell data-table__cell--amount text-green">
                <?= format_rupiah($re['pemasukan']) ?>
              </td>
              <td class="data-table__cell data-table__cell--amount text-red">
                <?= format_rupiah($re['pengeluaran']) ?>
              </td>
              <td class="data-table__cell data-table__cell--amount
                         <?= $profit >= 0 ? 'text-green' : 'text-red' ?>">
                <?= format_rupiah($profit) ?>
              </td>
              <td class="data-table__cell">
                <div class="budget-cell" style="min-width:120px">
                  <div class="progress-bar">
                    <div class="progress-bar__fill progress-bar__fill--<?= $warna ?>"
                         style="width:<?= min($serapan, 100) ?>%"></div>
                  </div>
                  <span class="budget-cell__pct"><?= $serapan ?>%</span>
                </div>
              </td>
              <td class="data-table__cell text-muted">
                <?= (int)$re['jumlah_transaksi'] ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <!-- Grafik: Pengeluaran per Kategori -->
    <?php if (!empty($rekapKategori)) : ?>
    <div class="chart-card" style="margin-bottom:var(--space-5)">
      <div class="chart-card__header">
        <div>
          <h2 class="chart-card__title">Pengeluaran per Kategori</h2>
          <span class="chart-card__subtitle"><?= htmlspecialchars($periodeLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      </div>
      <div class="chart-card__body">
        <canvas id="chartKategoriLaporan" role="img"
                aria-label="Grafik pengeluaran per kategori"></canvas>
      </div>
    </div>
    <?php endif; ?>

    <!-- Tabel rekap per kategori -->
    <?php if (!empty($rekapKategori)) : ?>
    <section aria-labelledby="rekap-kategori-title">
      <div class="section-header-bar">
        <h2 class="section-header-bar__title" id="rekap-kategori-title">
          Rekapitulasi per Kategori
        </h2>
      </div>
      <div class="table-wrap">
        <table class="data-table" aria-label="Rekapitulasi per kategori transaksi">
          <thead>
            <tr>
              <th scope="col">Tipe</th>
              <th scope="col">Kategori</th>
              <th scope="col">Jumlah Transaksi</th>
              <th scope="col">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rekapKategori as $rk) : ?>
            <tr>
              <td class="data-table__cell">
                <span class="badge badge--<?= $rk['tipe'] ?>">
                  <?= $rk['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
                </span>
              </td>
              <td class="data-table__cell data-table__cell--primary">
                <?= htmlspecialchars($rk['kategori'], ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="data-table__cell text-muted"><?= (int)$rk['jumlah'] ?></td>
              <td class="data-table__cell data-table__cell--amount
                         <?= $rk['tipe'] === 'pemasukan' ? 'text-green' : 'text-red' ?>">
                <?= format_rupiah($rk['total']) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <!-- Tabel detail transaksi -->
    <section aria-labelledby="detail-trx-title">
      <div class="section-header-bar">
        <h2 class="section-header-bar__title" id="detail-trx-title">
          Detail Transaksi
          <span style="font-weight:400;font-size:14px;color:var(--text-secondary)">
            (<?= count($detailTrx) ?> transaksi)
          </span>
        </h2>
      </div>

      <?php if (empty($detailTrx)) : ?>
      <div class="empty-state">
        <p class="empty-state__text">Tidak ada transaksi untuk filter yang dipilih.</p>
      </div>
      <?php else : ?>
      <div class="table-wrap">
        <table class="data-table" aria-label="Detail semua transaksi">
          <thead>
            <tr>
              <th scope="col">Tanggal</th>
              <th scope="col">Event</th>
              <th scope="col">Tipe</th>
              <th scope="col">Kategori</th>
              <th scope="col">Vendor</th>
              <th scope="col">Jumlah</th>
              <th scope="col">Dicatat oleh</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($detailTrx as $trx) : ?>
            <tr>
              <td class="data-table__cell">
                <time datetime="<?= $trx['tanggal_transaksi'] ?>">
                  <?= date('d M Y', strtotime($trx['tanggal_transaksi'])) ?>
                </time>
              </td>
              <td class="data-table__cell data-table__cell--primary">
                <?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="data-table__cell">
                <span class="badge badge--<?= $trx['tipe'] ?>">
                  <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
                </span>
              </td>
              <td class="data-table__cell">
                <?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="data-table__cell text-muted">
                <?= $trx['nama_vendor']
                    ? htmlspecialchars($trx['nama_vendor'], ENT_QUOTES, 'UTF-8')
                    : '—' ?>
              </td>
              <td class="data-table__cell data-table__cell--amount
                         <?= $trx['tipe'] === 'pemasukan' ? 'text-green' : 'text-red' ?>">
                <?= $trx['tipe'] === 'pemasukan' ? '+' : '−' ?>
                <?= format_rupiah($trx['jumlah']) ?>
              </td>
              <td class="data-table__cell text-muted">
                <?= htmlspecialchars($trx['nama_user'], ENT_QUOTES, 'UTF-8') ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="background:var(--bg-light);font-weight:600">
              <td class="data-table__cell" colspan="5" style="text-align:right">
                Total
              </td>
              <td class="data-table__cell data-table__cell--amount
                         <?= $totalProfit >= 0 ? 'text-green' : 'text-red' ?>">
                <?= format_rupiah($totalProfit) ?>
              </td>
              <td class="data-table__cell"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </section>

  </div>
</div>

<?php
$page_type = 'admin';

// ── Embed data grafik ke variabel window terpisah sesuai admin.js ────────────
// admin.js membaca: window.dataLaporan, window.dataKategoriLaporan
?>
<script>
window.dataLaporan = <?= json_encode([
    'labels'          => $laporanLabels,
    'dataPemasukan'   => $laporanDataPemasukan,
    'dataPengeluaran' => $laporanDataPengeluaran,
], JSON_UNESCAPED_UNICODE) ?>;

window.dataKategoriLaporan = <?= json_encode([
    'labels' => $kategoriLaporanLabels,
    'data'   => $kategoriLaporanValues,
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
require_once __DIR__ . '/../../includes/footer.php';
?>