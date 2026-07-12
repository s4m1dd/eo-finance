<?php
/**
 * EO Finance — Dashboard Admin EO.
 *
 * Menampilkan ringkasan keuangan bulan ini, event aktif, dan transaksi terbaru.
 * Semua query difilter by eo_id untuk isolasi data multi-tenant.
 *
 * TODO: Ganti $eo_id = 1 dengan get_current_eo_id() setelah login fungsional.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role('admin_eo');

// ── Tenant ID — hardcode sementara, ganti dengan get_current_eo_id() nanti ───
$eo_id = get_current_eo_id();

// ════════════════════════════════════════════════════════════════════════════
// QUERY 1 — Total kas bulan ini (pemasukan - pengeluaran)
// JOIN ke events agar filter eo_id bisa diterapkan lewat relasi yang benar.
// MONTH/YEAR berdasarkan created_at transaksi, bukan tanggal event.
// ════════════════════════════════════════════════════════════════════════════
$bulanIni = (int) date('m');
$tahunIni = (int) date('Y');

$stmtKas = $mysqli->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN t.tipe = 'pemasukan'   THEN t.jumlah ELSE 0 END), 0) AS total_pemasukan,
        COALESCE(SUM(CASE WHEN t.tipe = 'pengeluaran' THEN t.jumlah ELSE 0 END), 0) AS total_pengeluaran
    FROM transaksi t
    INNER JOIN events e ON t.event_id = e.id
    WHERE e.eo_id = ?
      AND MONTH(t.created_at) = ?
      AND YEAR(t.created_at)  = ?
      AND t.status_approval = 'approved'
");
$stmtKas->bind_param('iii', $eo_id, $bulanIni, $tahunIni);
$stmtKas->execute();
$rowKas          = $stmtKas->get_result()->fetch_assoc();
$stmtKas->close();

$totalPemasukan   = (float) ($rowKas['total_pemasukan']  ?? 0);
$totalPengeluaran = (float) ($rowKas['total_pengeluaran'] ?? 0);
$totalKas         = $totalPemasukan - $totalPengeluaran;

// ════════════════════════════════════════════════════════════════════════════
// QUERY 2 — Jumlah event aktif (status draft atau aktif)
// ════════════════════════════════════════════════════════════════════════════
$stmtEventAktif = $mysqli->prepare("
    SELECT COUNT(*) AS jumlah
    FROM events
    WHERE eo_id = ?
      AND status IN ('draft', 'aktif')
");
$stmtEventAktif->bind_param('i', $eo_id);
$stmtEventAktif->execute();
$jumlahEventAktif = (int) $stmtEventAktif->get_result()->fetch_assoc()['jumlah'];
$stmtEventAktif->close();

// ════════════════════════════════════════════════════════════════════════════
// QUERY 3 — Total tagihan vendor (transaksi yang terhubung ke vendor)
// Estimasi: SUM semua transaksi pengeluaran yang memiliki vendor_id
// ════════════════════════════════════════════════════════════════════════════
$stmtVendor = $mysqli->prepare("
    SELECT COALESCE(SUM(t.jumlah), 0) AS total_tagihan
    FROM transaksi t
    INNER JOIN events e ON t.event_id = e.id
    WHERE e.eo_id      = ?
      AND t.vendor_id  IS NOT NULL
      AND t.tipe       = 'pengeluaran'
      AND t.status_approval = 'approved'
");
$stmtVendor->bind_param('i', $eo_id);
$stmtVendor->execute();
$totalTagihanVendor = (float) $stmtVendor->get_result()->fetch_assoc()['total_tagihan'];
$stmtVendor->close();

// ════════════════════════════════════════════════════════════════════════════
// QUERY 3b — Total Piutang & Hutang (transaksi approved yang belum_lunas)
// ════════════════════════════════════════════════════════════════════════════
$stmtPH = $mysqli->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN tipe='pemasukan'   THEN jumlah ELSE 0 END), 0) AS total_piutang,
        COALESCE(SUM(CASE WHEN tipe='pengeluaran' THEN jumlah ELSE 0 END), 0) AS total_hutang
    FROM transaksi
    WHERE eo_id = ? AND status_approval = 'approved' AND status_pembayaran = 'belum_lunas'
");
$stmtPH->bind_param('i', $eo_id);
$stmtPH->execute();
$rowPH = $stmtPH->get_result()->fetch_assoc();
$totalPiutangDash = (float) ($rowPH['total_piutang'] ?? 0);
$totalHutangDash  = (float) ($rowPH['total_hutang']  ?? 0);
$stmtPH->close();

// ════════════════════════════════════════════════════════════════════════════
// NOTIFIKASI: EVENT MENDEKATI TANGGAL MULAI (H-7, H-3, H-1)
// Dikirim sekali per event per jangkauan (cek apakah sudah ada notif hari ini)
// ════════════════════════════════════════════════════════════════════════════
$stmtDekat = $mysqli->prepare("
    SELECT e.id, e.nama_event, DATEDIFF(e.tanggal_mulai, CURDATE()) AS sisa_hari
    FROM events e
    WHERE e.eo_id = ?
      AND e.status IN ('draft','aktif')
      AND DATEDIFF(e.tanggal_mulai, CURDATE()) IN (7, 3, 1)
");
$stmtDekat->bind_param('i', $eo_id);
$stmtDekat->execute();
$eventsDekat = $stmtDekat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtDekat->close();

foreach ($eventsDekat as $evD) {
    $sisaHari = (int) $evD['sisa_hari'];
    $pesan    = sprintf(
        'Event "%s" akan dimulai dalam %d hari lagi. Pastikan persiapan sudah matang.',
        $evD['nama_event'], $sisaHari
    );
    // Cek apakah notifikasi jenis ini sudah dikirim hari ini untuk event ini
    $stmtCekN = $mysqli->prepare(
        "SELECT id FROM notifikasi
         WHERE eo_id = ? AND tipe = 'event_dekat'
           AND link = ?
           AND DATE(created_at) = CURDATE()
         LIMIT 1"
    );
    $linkN = '/admin/events/detail.php?id=' . (int)$evD['id'];
    $stmtCekN->bind_param('is', $eo_id, $linkN);
    $stmtCekN->execute();
    $sudahKirim = $stmtCekN->get_result()->fetch_assoc();
    $stmtCekN->close();

    if ($sudahKirim === null) {
        kirim_notifikasi_ke_semua_admin(
            $mysqli, $eo_id,
            'event_dekat',
            sprintf('H-%d: %s', $sisaHari, $evD['nama_event']),
            $pesan,
            $linkN
        );
    }
}

// ════════════════════════════════════════════════════════════════════════════
// QUERY 4 — Daftar event aktif lengkap dengan nama klien
// ORDER BY tanggal_mulai ASC: event terdekat tampil paling atas
// ════════════════════════════════════════════════════════════════════════════
$stmtEvents = $mysqli->prepare("
    SELECT
        e.id,
        e.nama_event,
        e.tanggal_mulai,
        e.tanggal_selesai,
        e.anggaran,
        e.status,
        k.nama AS nama_klien
    FROM events e
    LEFT JOIN klien k ON e.klien_id = k.id
    WHERE e.eo_id  = ?
      AND e.status IN ('draft', 'aktif')
    ORDER BY e.tanggal_mulai ASC
");
$stmtEvents->bind_param('i', $eo_id);
$stmtEvents->execute();
$resultEvents = $stmtEvents->get_result();

// Ambil semua event lalu hitung sisa anggaran masing-masing
// hitung_sisa_anggaran() menjalankan 2 query per event — acceptable untuk dashboard
$daftarEvents = [];
while ($row = $resultEvents->fetch_assoc()) {
    $row['budget_info'] = hitung_sisa_anggaran((int) $row['id'], $mysqli, $eo_id);
    $daftarEvents[]     = $row;
}
$stmtEvents->close();

// ════════════════════════════════════════════════════════════════════════════
// QUERY 5 — Lima transaksi terbaru di semua event EO ini
// JOIN users untuk nama pembuat, JOIN events untuk nama event
// ════════════════════════════════════════════════════════════════════════════
$stmtTrx = $mysqli->prepare("
    SELECT
        t.id,
        t.tipe,
        t.kategori,
        t.jumlah,
        t.status_approval,
        t.tanggal_transaksi,
        t.created_at,
        e.nama_event,
        u.nama AS nama_user
    FROM transaksi t
    INNER JOIN events e ON t.event_id = e.id
    INNER JOIN users  u ON t.created_by = u.id
    WHERE e.eo_id = ?
    ORDER BY t.created_at DESC
    LIMIT 5
");
$stmtTrx->bind_param('i', $eo_id);
$stmtTrx->execute();
$daftarTrx = $stmtTrx->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTrx->close();

// ════════════════════════════════════════════════════════════════════════════
// QUERY CHART 1 — Tren Keuangan: pemasukan & pengeluaran 6 bulan terakhir
// ════════════════════════════════════════════════════════════════════════════
$trenLabels      = [];
$trenPemasukan   = [];
$trenPengeluaran = [];

for ($i = 5; $i >= 0; $i--) {
    $ts    = mktime(0, 0, 0, (int) date('m') - $i, 1, (int) date('Y'));
    $bln   = (int) date('m', $ts);
    $thn   = (int) date('Y', $ts);

    $trenLabels[] = date('M Y', $ts); // contoh: "Jan 2026"

    $stmtT = $mysqli->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN t.tipe = 'pemasukan'   THEN t.jumlah ELSE 0 END), 0) AS pms,
            COALESCE(SUM(CASE WHEN t.tipe = 'pengeluaran' THEN t.jumlah ELSE 0 END), 0) AS pgl
        FROM transaksi t
        INNER JOIN events e ON t.event_id = e.id
        WHERE e.eo_id = ?
          AND MONTH(t.created_at) = ?
          AND YEAR(t.created_at)  = ?
          AND t.status_approval = 'approved'
    ");
    $stmtT->bind_param('iii', $eo_id, $bln, $thn);
    $stmtT->execute();
    $rowT = $stmtT->get_result()->fetch_assoc();
    $stmtT->close();

    $trenPemasukan[]   = (float) ($rowT['pms'] ?? 0);
    $trenPengeluaran[] = (float) ($rowT['pgl'] ?? 0);
}

// ════════════════════════════════════════════════════════════════════════════
// QUERY CHART 2 — Pengeluaran per Kategori bulan ini
// ════════════════════════════════════════════════════════════════════════════
$stmtKat = $mysqli->prepare("
    SELECT t.kategori, COALESCE(SUM(t.jumlah), 0) AS total
    FROM transaksi t
    INNER JOIN events e ON t.event_id = e.id
    WHERE e.eo_id      = ?
      AND t.tipe        = 'pengeluaran'
      AND MONTH(t.created_at) = ?
      AND YEAR(t.created_at)  = ?
      AND t.status_approval = 'approved'
    GROUP BY t.kategori
    ORDER BY total DESC
");
$stmtKat->bind_param('iii', $eo_id, $bulanIni, $tahunIni);
$stmtKat->execute();
$rowsKat = $stmtKat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtKat->close();

$kategoriLabels = array_column($rowsKat, 'kategori');
$kategoriValues = array_map('floatval', array_column($rowsKat, 'total'));

// ════════════════════════════════════════════════════════════════════════════
// QUERY CHART 3 — Profit per Event aktif (pemasukan - pengeluaran)
// ════════════════════════════════════════════════════════════════════════════
$stmtProfit = $mysqli->prepare("
    SELECT
        e.nama_event,
        COALESCE(SUM(CASE WHEN t.tipe = 'pemasukan'   THEN t.jumlah ELSE 0 END), 0)
      - COALESCE(SUM(CASE WHEN t.tipe = 'pengeluaran' THEN t.jumlah ELSE 0 END), 0) AS profit
    FROM events e
    LEFT JOIN transaksi t ON t.event_id = e.id AND t.status_approval = 'approved'
    WHERE e.eo_id  = ?
      AND e.status IN ('draft', 'aktif')
    GROUP BY e.id, e.nama_event
    ORDER BY profit DESC
    LIMIT 10
");
$stmtProfit->bind_param('i', $eo_id);
$stmtProfit->execute();
$rowsProfit = $stmtProfit->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtProfit->close();

$profitLabels = array_column($rowsProfit, 'nama_event');
$profitValues = array_map('floatval', array_column($rowsProfit, 'profit'));

// ════════════════════════════════════════════════════════════════════════════
// QUERY CHART 4 — Realisasi Anggaran: anggaran vs aktual per event aktif
// ════════════════════════════════════════════════════════════════════════════
$stmtAng = $mysqli->prepare("
    SELECT
        e.nama_event,
        e.anggaran,
        COALESCE(SUM(CASE WHEN t.tipe = 'pengeluaran' THEN t.jumlah ELSE 0 END), 0) AS aktual
    FROM events e
    LEFT JOIN transaksi t ON t.event_id = e.id AND t.status_approval = 'approved'
    WHERE e.eo_id  = ?
      AND e.status IN ('draft', 'aktif')
    GROUP BY e.id, e.nama_event, e.anggaran
    ORDER BY e.tanggal_mulai ASC
    LIMIT 8
");
$stmtAng->bind_param('i', $eo_id);
$stmtAng->execute();
$rowsAng = $stmtAng->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtAng->close();

$anggaranLabels  = array_column($rowsAng, 'nama_event');
$anggaranBudget  = array_map('floatval', array_column($rowsAng, 'anggaran'));
$anggaranAktual  = array_map('floatval', array_column($rowsAng, 'aktual'));

// ── Setup variabel tampilan ───────────────────────────────────────────────────
$pageTitle = 'Dashboard';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <!-- ── Greeting & breadcrumb ──────────────────────────────── -->
    <div class="page-header">
      <div class="page-header__left">
        <p class="page-greeting"><?= greeting() ?>, <?= htmlspecialchars($_SESSION['nama'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>!</p>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">
          Ringkasan keuangan
          <?= date('F Y') ?> <!-- misal: "Juli 2026" -->
        </p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/admin/events/tambah.php"
           class="btn btn--primary">
          <span class="btn__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M12 5v14M5 12h14" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          Buat Event Baru
        </a>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         KARTU STATISTIK — 4 metrik utama
    ════════════════════════════════════════════════════════ -->
    <div class="stats-grid">

      <!-- Kartu 1: Total Kas Bulan Ini -->
      <article class="stat-card <?= $totalKas >= 0 ? 'stat-card--positive' : 'stat-card--negative' ?>">
        <div class="stat-card__header">
          <span class="stat-card__label">Total Kas Bulan Ini</span>
          <span class="stat-card__icon stat-card__icon--blue" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
        </div>
        <p class="stat-card__value">
          <?= format_rupiah($totalKas) ?>
        </p>
        <div class="stat-card__detail">
          <span class="stat-card__detail-item stat-card__detail-item--green">
            ↑ <?= format_rupiah($totalPemasukan) ?> masuk
          </span>
          <span class="stat-card__detail-sep" aria-hidden="true">·</span>
          <span class="stat-card__detail-item stat-card__detail-item--red">
            ↓ <?= format_rupiah($totalPengeluaran) ?> keluar
          </span>
        </div>
      </article>

      <!-- Kartu 2: Event Aktif -->
      <article class="stat-card">
        <div class="stat-card__header">
          <span class="stat-card__label">Event Aktif</span>
          <span class="stat-card__icon stat-card__icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="4" width="18" height="18" rx="2"
                    stroke="currentColor" stroke-width="1.8"/>
              <path d="M3 10h18M8 2v4M16 2v4"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
        </div>
        <p class="stat-card__value"><?= $jumlahEventAktif ?></p>
        <p class="stat-card__meta">event sedang berjalan</p>
      </article>

      <!-- Kartu 3: Total Pemasukan Bulan Ini -->
      <article class="stat-card">
        <div class="stat-card__header">
          <span class="stat-card__label">Pemasukan Bulan Ini</span>
          <span class="stat-card__icon stat-card__icon--teal" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M3 3v18h18" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round"/>
              <path d="M7 16l4-5 4 3 4-7" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </div>
        <p class="stat-card__value"><?= format_rupiah($totalPemasukan) ?></p>
        <p class="stat-card__meta">total bulan ini</p>
      </article>

      <!-- Kartu 4: Tagihan Vendor -->
      <article class="stat-card">
        <div class="stat-card__header">
          <span class="stat-card__label">Tagihan Vendor</span>
          <span class="stat-card__icon stat-card__icon--orange" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                    stroke="currentColor" stroke-width="1.8"/>
              <rect x="9" y="14" width="6" height="8"
                    stroke="currentColor" stroke-width="1.8"/>
            </svg>
          </span>
        </div>
        <p class="stat-card__value"><?= format_rupiah($totalTagihanVendor) ?></p>
        <p class="stat-card__meta">total pengeluaran ke vendor</p>
      </article>

      <!-- Kartu 5: Piutang -->
      <article class="stat-card">
        <div class="stat-card__header">
          <span class="stat-card__label">Piutang</span>
          <span class="stat-card__icon stat-card__icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </div>
        <p class="stat-card__value"><?= format_rupiah($totalPiutangDash) ?></p>
        <p class="stat-card__meta">
          <a href="<?= BASE_URL ?>/admin/piutang-hutang/index.php">klien belum bayar →</a>
        </p>
      </article>

      <!-- Kartu 6: Hutang -->
      <article class="stat-card">
        <div class="stat-card__header">
          <span class="stat-card__label">Hutang</span>
          <span class="stat-card__icon stat-card__icon--orange" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </div>
        <p class="stat-card__value"><?= format_rupiah($totalHutangDash) ?></p>
        <p class="stat-card__meta">
          <a href="<?= BASE_URL ?>/admin/piutang-hutang/index.php">belum dibayar ke vendor →</a>
        </p>
      </article>

    </div><!-- /stats-grid -->


    <!-- ════════════════════════════════════════════════════════
         GRAFIK — 4 canvas untuk Chart.js (diisi minggu depan)
    ════════════════════════════════════════════════════════ -->
    <div class="charts-grid">

      <div class="chart-card">
        <div class="chart-card__header">
          <h2 class="chart-card__title">Tren Keuangan</h2>
          <span class="chart-card__subtitle">6 bulan terakhir</span>
        </div>
        <div class="chart-card__body">
          <canvas id="chartTren" role="img" aria-label="Grafik tren keuangan 6 bulan terakhir"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <div class="chart-card__header">
          <h2 class="chart-card__title">Pengeluaran per Kategori</h2>
          <span class="chart-card__subtitle">Bulan ini</span>
        </div>
        <div class="chart-card__body">
          <canvas id="chartKategori" role="img" aria-label="Grafik pengeluaran per kategori bulan ini"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <div class="chart-card__header">
          <h2 class="chart-card__title">Profit per Event</h2>
          <span class="chart-card__subtitle">Semua event aktif</span>
        </div>
        <div class="chart-card__body">
          <canvas id="chartProfit" role="img" aria-label="Grafik profit per event aktif"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <div class="chart-card__header">
          <h2 class="chart-card__title">Realisasi Anggaran</h2>
          <span class="chart-card__subtitle">Anggaran vs aktual</span>
        </div>
        <div class="chart-card__body">
          <canvas id="chartAnggaran" role="img" aria-label="Grafik realisasi anggaran vs aktual"></canvas>
        </div>
      </div>

    </div><!-- /charts-grid -->


    <!-- ════════════════════════════════════════════════════════
         TABEL EVENT AKTIF
    ════════════════════════════════════════════════════════ -->
    <section class="dashboard-section" aria-labelledby="section-events">
      <div class="section-header-bar">
        <h2 class="section-header-bar__title" id="section-events">
          Event Aktif
        </h2>
        <a href="<?= BASE_URL ?>/admin/events/index.php"
           class="section-header-bar__link">
          Lihat semua
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
               aria-hidden="true">
            <path d="M5 12h14M13 6l6 6-6 6"
                  stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
      </div>

      <?php if (empty($daftarEvents)) : ?>
      <!-- Empty state -->
      <div class="empty-state">
        <div class="empty-state__icon" aria-hidden="true">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
            <rect x="3" y="4" width="18" height="18" rx="2"
                  stroke="currentColor" stroke-width="1.5"/>
            <path d="M3 10h18M8 2v4M16 2v4"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            <path d="M8 15h8M8 18h5"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </div>
        <p class="empty-state__text">Belum ada event aktif</p>
        <a href="<?= BASE_URL ?>/admin/events/tambah.php"
           class="btn btn--primary">
          Buat Event Pertama
        </a>
      </div>

      <?php else : ?>
      <div class="table-wrap">
        <table class="data-table" aria-label="Daftar event aktif">
          <thead>
            <tr>
              <th scope="col">Nama Event</th>
              <th scope="col">Tanggal</th>
              <th scope="col">Klien</th>
              <th scope="col">Sisa Anggaran</th>
              <th scope="col">Status</th>
              <th scope="col"><span class="sr-only">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarEvents as $event) :
                $bi    = $event['budget_info'];     // shortcut ke array hitung_sisa_anggaran()
                $pct   = (float) $bi['persentase_terpakai'];
                $warna = $bi['status_warna'];       // 'hijau' | 'kuning' | 'merah'
            ?>
            <tr>
              <!-- Nama Event -->
              <td class="data-table__cell data-table__cell--primary">
                <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int) $event['id'] ?>"
                   class="table-link">
                  <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </td>

              <!-- Tanggal mulai – selesai -->
              <td class="data-table__cell">
                <span class="date-range">
                  <?= htmlspecialchars(
                        date('d M Y', strtotime($event['tanggal_mulai'])),
                        ENT_QUOTES, 'UTF-8'
                    ) ?>
                  <span class="date-range__sep" aria-label="sampai">–</span>
                  <?= htmlspecialchars(
                        date('d M Y', strtotime($event['tanggal_selesai'])),
                        ENT_QUOTES, 'UTF-8'
                    ) ?>
                </span>
              </td>

              <!-- Klien (nullable) -->
              <td class="data-table__cell">
                <?php if ($event['nama_klien']) : ?>
                  <?= htmlspecialchars($event['nama_klien'], ENT_QUOTES, 'UTF-8') ?>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>

              <!-- Sisa Anggaran + Progress Bar -->
              <td class="data-table__cell">
                <div class="budget-cell">
                  <span class="budget-cell__amount budget-cell__amount--<?= $warna ?>">
                    <?= $bi['formatted']['sisa'] ?>
                  </span>
                  <div class="progress-bar" role="progressbar"
                       aria-valuenow="<?= $pct ?>"
                       aria-valuemin="0"
                       aria-valuemax="100"
                       aria-label="<?= $pct ?>% anggaran terpakai">
                    <div class="progress-bar__fill progress-bar__fill--<?= $warna ?>"
                         style="width: <?= min($pct, 100) ?>%">
                    </div>
                  </div>
                  <span class="budget-cell__pct">
                    <?= number_format($pct, 1) ?>% terpakai
                  </span>
                </div>
              </td>

              <!-- Badge Status -->
              <td class="data-table__cell">
                <span class="badge badge--<?= htmlspecialchars($event['status'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= $event['status'] === 'aktif' ? 'Aktif' : 'Draft' ?>
                </span>
              </td>

              <!-- Aksi -->
              <td class="data-table__cell data-table__cell--actions">
                <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int) $event['id'] ?>"
                   class="btn-icon" title="Lihat detail event"
                   aria-label="Detail event <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                          stroke="currentColor" stroke-width="1.8"/>
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                  </svg>
                </a>
                <a href="<?= BASE_URL ?>/admin/events/edit.php?id=<?= (int) $event['id'] ?>"
                   class="btn-icon" title="Edit event"
                   aria-label="Edit event <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"
                          stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                    <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"
                          stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  </svg>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div><!-- /table-wrap -->
      <?php endif; ?>
    </section>


    <!-- ════════════════════════════════════════════════════════
         TABEL TRANSAKSI TERBARU
    ════════════════════════════════════════════════════════ -->
    <section class="dashboard-section" aria-labelledby="section-transaksi">
      <div class="section-header-bar">
        <h2 class="section-header-bar__title" id="section-transaksi">
          Transaksi Terbaru
        </h2>
        <a href="<?= BASE_URL ?>/admin/transaksi/index.php"
           class="section-header-bar__link">
          Lihat semua
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
               aria-hidden="true">
            <path d="M5 12h14M13 6l6 6-6 6"
                  stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
      </div>

      <?php if (empty($daftarTrx)) : ?>
      <div class="empty-state">
        <div class="empty-state__icon" aria-hidden="true">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"
                  stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          </svg>
        </div>
        <p class="empty-state__text">Belum ada transaksi</p>
        <a href="<?= BASE_URL ?>/admin/transaksi/tambah.php"
           class="btn btn--primary">
          Catat Transaksi Pertama
        </a>
      </div>

      <?php else : ?>
      <div class="table-wrap">
        <table class="data-table" aria-label="Lima transaksi terbaru">
          <thead>
            <tr>
              <th scope="col">Event</th>
              <th scope="col">Tipe</th>
              <th scope="col">Kategori</th>
              <th scope="col">Jumlah</th>
              <th scope="col">Status</th>
              <th scope="col">Dicatat oleh</th>
              <th scope="col">Tanggal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarTrx as $trx) : ?>
            <tr>
              <!-- Event -->
              <td class="data-table__cell data-table__cell--primary">
                <?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?>
              </td>

              <!-- Tipe dengan badge warna -->
              <td class="data-table__cell">
                <span class="badge badge--<?= htmlspecialchars($trx['tipe'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
                </span>
              </td>

              <!-- Kategori -->
              <td class="data-table__cell">
                <?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?>
              </td>

              <!-- Jumlah dengan warna sesuai tipe -->
              <td class="data-table__cell data-table__cell--amount
                         <?= $trx['tipe'] === 'pemasukan' ? 'text-green' : 'text-red' ?>">
                <?= $trx['tipe'] === 'pemasukan' ? '+' : '-' ?>
                <?= format_rupiah($trx['jumlah']) ?>
              </td>

              <!-- Status approval -->
              <td class="data-table__cell">
                <?= badge_status_approval($trx['status_approval']) ?>
              </td>

              <!-- Nama user yang mencatat -->
              <td class="data-table__cell">
                <?= htmlspecialchars($trx['nama_user'], ENT_QUOTES, 'UTF-8') ?>
              </td>

              <!-- Tanggal transaksi -->
              <td class="data-table__cell text-muted">
                <time datetime="<?= htmlspecialchars($trx['tanggal_transaksi'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars(
                        date('d M Y', strtotime($trx['tanggal_transaksi'])),
                        ENT_QUOTES, 'UTF-8'
                    ) ?>
                </time>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div><!-- /table-wrap -->
      <?php endif; ?>
    </section>

  </div><!-- /admin-content -->
</div><!-- /admin-layout -->

<?php
$page_type = 'admin';

// ── Embed data grafik ke variabel window terpisah sesuai admin.js ────────────
// admin.js membaca: window.dataTren, window.dataKategori,
//                   window.dataProfit, window.dataAnggaran
// Key name di dalam tiap objek harus cocok persis dengan yang dibaca admin.js.
?>
<script>
window.dataTren = <?= json_encode([
    'labels'          => $trenLabels,
    'dataPemasukan'   => $trenPemasukan,
    'dataPengeluaran' => $trenPengeluaran,
], JSON_UNESCAPED_UNICODE) ?>;

window.dataKategori = <?= json_encode([
    'labels' => $kategoriLabels,
    'data'   => $kategoriValues,
], JSON_UNESCAPED_UNICODE) ?>;

window.dataProfit = <?= json_encode([
    'labels' => $profitLabels,
    'data'   => $profitValues,
], JSON_UNESCAPED_UNICODE) ?>;

window.dataAnggaran = <?= json_encode([
    'labels'       => $anggaranLabels,
    'dataAnggaran' => $anggaranBudget,
    'dataRealisasi' => $anggaranAktual,
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
require_once __DIR__ . '/../includes/footer.php';
?>