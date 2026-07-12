<?php
/**
 * EO Finance — Dashboard Staff EO.
 *
 * Menampilkan ringkasan aktivitas staff sendiri:
 *   - 3 stat card: kas bulan ini, event yang ditugaskan, transaksi yang dicatat
 *   - 2 grafik: aktivitas transaksi 7 hari terakhir (window.dataAktivitas)
 *     dan status transaksi di event yang ditugaskan ke staff ini (window.dataStatusTrx)
 *   - Tabel event yang ditugaskan ke staff ini
 *   - Tabel 5 transaksi terbaru yang dicatat staff ini
 *
 * Semua query difilter by eo_id (isolasi tenant) DAN user_id
 * (staff hanya melihat data yang relevan dengannya).
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
require_role('staff_eo');

// ── ID dari sesi login ────────────────────────────────────────────────────────
$eo_id   = get_current_eo_id();
$user_id = get_current_user_id();

$bulanIni = (int) date('m');
$tahunIni = (int) date('Y');

// ════════════════════════════════════════════════════════════════════════════
// QUERY 1 — Kas bulan ini dari event yang ditugaskan ke staff ini
// Hanya hitung transaksi pada event tempat staff ditugaskan.
// ════════════════════════════════════════════════════════════════════════════
$stmtKas = $mysqli->prepare("
    SELECT
        COALESCE(SUM(CASE WHEN t.tipe = 'pemasukan'   THEN t.jumlah ELSE 0 END), 0) AS total_pemasukan,
        COALESCE(SUM(CASE WHEN t.tipe = 'pengeluaran' THEN t.jumlah ELSE 0 END), 0) AS total_pengeluaran
    FROM transaksi t
    INNER JOIN events e ON t.event_id = e.id
    INNER JOIN penugasan p ON p.event_id = e.id AND p.user_id = ?
    WHERE e.eo_id = ?
      AND MONTH(t.created_at) = ?
      AND YEAR(t.created_at)  = ?
      AND t.status_approval = 'approved'
");
$stmtKas->bind_param('iiii', $user_id, $eo_id, $bulanIni, $tahunIni);
$stmtKas->execute();
$rowKas           = $stmtKas->get_result()->fetch_assoc();
$stmtKas->close();

$totalPemasukan   = (float) ($rowKas['total_pemasukan']  ?? 0);
$totalPengeluaran = (float) ($rowKas['total_pengeluaran'] ?? 0);
$totalKas         = $totalPemasukan - $totalPengeluaran;

// ════════════════════════════════════════════════════════════════════════════
// QUERY 2 — Jumlah event aktif yang ditugaskan ke staff ini
// ════════════════════════════════════════════════════════════════════════════
$stmtEvent = $mysqli->prepare("
    SELECT COUNT(*) AS jumlah
    FROM penugasan p
    INNER JOIN events e ON p.event_id = e.id
    WHERE p.user_id = ?
      AND e.eo_id   = ?
      AND e.status IN ('draft', 'aktif')
");
$stmtEvent->bind_param('ii', $user_id, $eo_id);
$stmtEvent->execute();
$jumlahEventSaya = (int) $stmtEvent->get_result()->fetch_assoc()['jumlah'];
$stmtEvent->close();

// ════════════════════════════════════════════════════════════════════════════
// QUERY 3 — Jumlah transaksi yang dicatat staff ini bulan ini
// ════════════════════════════════════════════════════════════════════════════
$stmtTrxCount = $mysqli->prepare("
    SELECT COUNT(*) AS jumlah
    FROM transaksi t
    INNER JOIN events e ON t.event_id = e.id
    WHERE t.created_by = ?
      AND e.eo_id      = ?
      AND MONTH(t.created_at) = ?
      AND YEAR(t.created_at)  = ?
");
$stmtTrxCount->bind_param('iiii', $user_id, $eo_id, $bulanIni, $tahunIni);
$stmtTrxCount->execute();
$jumlahTrxSaya = (int) $stmtTrxCount->get_result()->fetch_assoc()['jumlah'];
$stmtTrxCount->close();

// ════════════════════════════════════════════════════════════════════════════
// NOTIFIKASI: EVENT YANG DITUGASKAN KE STAFF INI MENDEKATI TANGGAL (H-3, H-1)
// ════════════════════════════════════════════════════════════════════════════
$stmtDekatStaff = $mysqli->prepare("
    SELECT e.id, e.nama_event, DATEDIFF(e.tanggal_mulai, CURDATE()) AS sisa_hari
    FROM penugasan p
    INNER JOIN events e ON p.event_id = e.id
    WHERE p.user_id = ? AND e.eo_id = ?
      AND e.status IN ('draft','aktif')
      AND DATEDIFF(e.tanggal_mulai, CURDATE()) IN (3, 1)
");
$stmtDekatStaff->bind_param('ii', $user_id, $eo_id);
$stmtDekatStaff->execute();
$eventsDekatStaff = $stmtDekatStaff->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtDekatStaff->close();

foreach ($eventsDekatStaff as $evDS) {
    $linkDS = '/staff/events/detail.php?id=' . (int)$evDS['id'];
    $stmtCekDS = $mysqli->prepare(
        "SELECT id FROM notifikasi
         WHERE user_id = ? AND tipe = 'event_dekat' AND link = ? AND DATE(created_at) = CURDATE() LIMIT 1"
    );
    $stmtCekDS->bind_param('is', $user_id, $linkDS);
    $stmtCekDS->execute();
    $sudahDS = $stmtCekDS->get_result()->fetch_assoc();
    $stmtCekDS->close();

    if ($sudahDS === null) {
        kirim_notifikasi(
            $mysqli, $eo_id, $user_id,
            'event_dekat',
            sprintf('H-%d: %s', (int)$evDS['sisa_hari'], $evDS['nama_event']),
            sprintf('Event "%s" akan dimulai dalam %d hari. Pastikan semua persiapan sudah selesai.', $evDS['nama_event'], (int)$evDS['sisa_hari']),
            $linkDS
        );
    }
}

// ════════════════════════════════════════════════════════════════════════════
// QUERY 4 — Daftar event yang ditugaskan ke staff ini (aktif & draft)
// ════════════════════════════════════════════════════════════════════════════
$stmtEvents = $mysqli->prepare("
    SELECT
        e.id,
        e.nama_event,
        e.tanggal_mulai,
        e.tanggal_selesai,
        e.anggaran,
        e.status,
        k.nama        AS nama_klien,
        p.role_dalam_event
    FROM penugasan p
    INNER JOIN events e ON p.event_id = e.id
    LEFT JOIN  klien  k ON e.klien_id = k.id
    WHERE p.user_id = ?
      AND e.eo_id   = ?
      AND e.status IN ('draft', 'aktif')
    ORDER BY e.tanggal_mulai ASC
");
$stmtEvents->bind_param('ii', $user_id, $eo_id);
$stmtEvents->execute();
$resultEvents = $stmtEvents->get_result();

$daftarEvents = [];
while ($row = $resultEvents->fetch_assoc()) {
    $row['budget_info'] = hitung_sisa_anggaran((int) $row['id'], $mysqli, $eo_id);
    $daftarEvents[]     = $row;
}
$stmtEvents->close();

// ════════════════════════════════════════════════════════════════════════════
// QUERY 5 — 5 transaksi terbaru yang dicatat oleh staff ini
// ════════════════════════════════════════════════════════════════════════════
$stmtTrx = $mysqli->prepare("
    SELECT
        t.id,
        t.tipe,
        t.kategori,
        t.jumlah,
        t.status_approval,
        t.tanggal_transaksi,
        e.nama_event
    FROM transaksi t
    INNER JOIN events e ON t.event_id = e.id
    WHERE t.created_by = ?
      AND e.eo_id      = ?
    ORDER BY t.created_at DESC
    LIMIT 5
");
$stmtTrx->bind_param('ii', $user_id, $eo_id);
$stmtTrx->execute();
$daftarTrx = $stmtTrx->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTrx->close();

// ════════════════════════════════════════════════════════════════════════════
// QUERY 6 — Data Chart: Aktivitas transaksi 7 hari terakhir (staff ini)
// Dipakai oleh staff.js → initChartAktivitas() → window.dataAktivitas
// ════════════════════════════════════════════════════════════════════════════
$aktivitasLabels = [];
$aktivitasData   = [];

for ($i = 6; $i >= 0; $i--) {
    $ts        = strtotime("-$i days");
    $tgl       = date('Y-m-d', $ts);
    $labelHari = date('D', $ts);   // Mon, Tue, ...

    // Terjemahkan ke Indonesia
    $hariMap = [
        'Mon' => 'Sen', 'Tue' => 'Sel', 'Wed' => 'Rab',
        'Thu' => 'Kam', 'Fri' => 'Jum', 'Sat' => 'Sab', 'Sun' => 'Min',
    ];
    $aktivitasLabels[] = $hariMap[$labelHari] ?? $labelHari;

    $stmtHari = $mysqli->prepare("
        SELECT COUNT(*) AS jml
        FROM transaksi t
        INNER JOIN events e ON t.event_id = e.id
        WHERE t.created_by  = ?
          AND e.eo_id       = ?
          AND DATE(t.created_at) = ?
    ");
    $stmtHari->bind_param('iis', $user_id, $eo_id, $tgl);
    $stmtHari->execute();
    $aktivitasData[] = (int) $stmtHari->get_result()->fetch_assoc()['jml'];
    $stmtHari->close();
}

// ════════════════════════════════════════════════════════════════════════════
// QUERY 7 — Data Chart: Status transaksi di event yang ditugaskan ke staff ini
// (semua waktu). Sebelumnya hanya menghitung transaksi yang DICATAT SENDIRI
// oleh staff (t.created_by = user_id), sehingga chart tampak kosong kalau
// transaksi pada event tsb dicatat oleh admin/staff lain. Sekarang dihitung
// dari semua transaksi pada event tempat staff ini ditugaskan (via tabel
// penugasan), tanpa memandang siapa yang mencatatnya.
// Donut kecil supaya staff langsung lihat berapa transaksi yang masih
// menunggu persetujuan, tanpa harus buka tabel transaksi.
// Dipakai oleh staff.js → initChartStatusTrx() → window.dataStatusTrx
// ════════════════════════════════════════════════════════════════════════════
$stmtStatus = $mysqli->prepare("
    SELECT t.status_approval, COUNT(*) AS jml
    FROM transaksi t
    INNER JOIN events e ON t.event_id = e.id
    INNER JOIN penugasan p ON p.event_id = e.id AND p.user_id = ?
    WHERE e.eo_id = ?
    GROUP BY t.status_approval
");
$stmtStatus->bind_param('ii', $user_id, $eo_id);
$stmtStatus->execute();
$rowsStatus = $stmtStatus->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtStatus->close();

// Normalisasi ke urutan tetap: Pending, Disetujui, Ditolak — supaya warna &
// label pada chart selalu konsisten walau salah satu status jumlahnya 0.
$statusMap = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($rowsStatus as $rs) {
    if (isset($statusMap[$rs['status_approval']])) {
        $statusMap[$rs['status_approval']] = (int) $rs['jml'];
    }
}
$statusTrxLabels = ['Menunggu', 'Disetujui', 'Ditolak'];
$statusTrxData   = [$statusMap['pending'], $statusMap['approved'], $statusMap['rejected']];
$totalStatusTrx  = array_sum($statusTrxData);

// ── DEBUG SEMENTARA — hapus setelah selesai troubleshooting ───────────────
if (isset($_GET['debug'])) {
    echo '<pre style="background:#111;color:#0f0;padding:12px;">';
    echo "user_id = $user_id\n";
    echo "eo_id   = $eo_id\n";
    echo "rowsStatus (hasil query mentah):\n";
    print_r($rowsStatus);
    echo "statusTrxData = ";
    print_r($statusTrxData);
    echo "totalStatusTrx = $totalStatusTrx\n";
    echo '</pre>';
}
// ── END DEBUG ───────────────────────────────────────────────────────────

// ── Setup variabel tampilan ───────────────────────────────────────────────────
$pageTitle = 'Dashboard';
$page_type = 'staff';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/navbar-staff.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <!-- ── Greeting ──────────────────────────────────────────── -->
    <div class="page-header">
      <div class="page-header__left">
        <p class="page-greeting"><?= greeting() ?>, <?= htmlspecialchars($_SESSION['nama'] ?? 'Staff', ENT_QUOTES, 'UTF-8') ?>!</p>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">
          Aktivitasmu bulan <?= date('F Y') ?>
        </p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/staff/transaksi/tambah.php"
           class="btn btn--primary">
          <span class="btn__icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M12 5v14M5 12h14" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round"/>
            </svg>
          </span>
          Catat Transaksi
        </a>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════
         KARTU STATISTIK — 3 kartu sesuai konteks staff
    ════════════════════════════════════════════════════════ -->
    <div class="stats-grid">

      <!-- Kartu 1: Kas Event Saya Bulan Ini -->
      <article class="stat-card <?= $totalKas >= 0 ? 'stat-card--positive' : 'stat-card--negative' ?>">
        <div class="stat-card__header">
          <span class="stat-card__label">Kas Event Saya</span>
          <span class="stat-card__icon stat-card__icon--blue" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M12 2v20M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
        </div>
        <p class="stat-card__value"><?= format_rupiah($totalKas) ?></p>
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

      <!-- Kartu 2: Event yang Ditugaskan -->
      <article class="stat-card">
        <div class="stat-card__header">
          <span class="stat-card__label">Event Ditugaskan</span>
          <span class="stat-card__icon stat-card__icon--green" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <rect x="3" y="4" width="18" height="18" rx="2"
                    stroke="currentColor" stroke-width="1.8"/>
              <path d="M3 10h18M8 2v4M16 2v4"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
        </div>
        <p class="stat-card__value"><?= $jumlahEventSaya ?></p>
        <p class="stat-card__meta">event aktif & draft</p>
      </article>

      <!-- Kartu 3: Transaksi yang Dicatat Bulan Ini -->
      <article class="stat-card">
        <div class="stat-card__header">
          <span class="stat-card__label">Transaksi Dicatat</span>
          <span class="stat-card__icon stat-card__icon--teal" aria-hidden="true">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
              <path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </span>
        </div>
        <p class="stat-card__value"><?= $jumlahTrxSaya ?></p>
        <p class="stat-card__meta">transaksi bulan ini</p>
      </article>

    </div><!-- /stats-grid -->


    <!-- ════════════════════════════════════════════════════════
         GRAFIK — Aktivitas 7 hari (chartAktivitas) +
                  Status Transaksi Saya (chartStatusTrx), via staff.js
    ════════════════════════════════════════════════════════ -->
    <div class="charts-grid">

      <div class="chart-card">
        <div class="chart-card__header">
          <div>
            <h2 class="chart-card__title">Aktivitas Transaksi</h2>
            <span class="chart-card__subtitle">7 hari terakhir</span>
          </div>
        </div>
        <div class="chart-card__body">
          <canvas id="chartAktivitas" role="img"
                  aria-label="Grafik jumlah transaksi 7 hari terakhir"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <div class="chart-card__header">
          <div>
            <h2 class="chart-card__title">Status Transaksi Saya</h2>
            <span class="chart-card__subtitle">Semua waktu</span>
          </div>
        </div>
        <?php if ($totalStatusTrx > 0) : ?>
        <div class="chart-card__body">
          <canvas id="chartStatusTrx" role="img"
                  aria-label="Grafik status transaksi yang saya catat: menunggu, disetujui, ditolak"></canvas>
        </div>
        <?php else : ?>
        <div class="chart-card__body chart-card__body--empty">
          <svg class="chart-placeholder-icon" width="40" height="40" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8"/>
            <path d="M12 8v5M12 16h.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
          </svg>
          <p class="chart-placeholder-text">Belum ada transaksi yang dicatat.</p>
        </div>
        <?php endif; ?>
      </div>

    </div>


    <!-- ════════════════════════════════════════════════════════
         TABEL EVENT YANG DITUGASKAN
    ════════════════════════════════════════════════════════ -->
    <section class="dashboard-section" aria-labelledby="section-events">
      <div class="section-header-bar">
        <h2 class="section-header-bar__title" id="section-events">
          Event Saya
        </h2>
        <a href="<?= BASE_URL ?>/staff/events/index.php"
           class="section-header-bar__link">
          Lihat semua
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M5 12h14M13 6l6 6-6 6"
                  stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
      </div>

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
        <p class="empty-state__text">Belum ada event yang ditugaskan kepadamu</p>
      </div>

      <?php else : ?>
      <div class="table-wrap">
        <table class="data-table" aria-label="Event yang ditugaskan">
          <thead>
            <tr>
              <th scope="col">Nama Event</th>
              <th scope="col">Tanggal</th>
              <th scope="col">Peranmu</th>
              <th scope="col">Sisa Anggaran</th>
              <th scope="col">Status</th>
              <th scope="col"><span class="sr-only">Aksi</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarEvents as $event) :
                $bi    = $event['budget_info'];
                $pct   = (float) $bi['persentase_terpakai'];
                $warna = $bi['status_warna'];
            ?>
            <tr>
              <td class="data-table__cell data-table__cell--primary">
                <a href="<?= BASE_URL ?>/staff/events/detail.php?id=<?= (int) $event['id'] ?>"
                   class="table-link">
                  <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </td>

              <td class="data-table__cell">
                <span class="date-range">
                  <?= htmlspecialchars(date('d M Y', strtotime($event['tanggal_mulai'])), ENT_QUOTES, 'UTF-8') ?>
                  <span class="date-range__sep" aria-label="sampai">–</span>
                  <?= htmlspecialchars(date('d M Y', strtotime($event['tanggal_selesai'])), ENT_QUOTES, 'UTF-8') ?>
                </span>
              </td>

              <td class="data-table__cell">
                <?php if ($event['role_dalam_event']) : ?>
                  <span class="role-badge">
                    <?= htmlspecialchars($event['role_dalam_event'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                <?php else : ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>

              <td class="data-table__cell">
                <div class="budget-cell">
                  <span class="budget-cell__amount budget-cell__amount--<?= $warna ?>">
                    <?= $bi['formatted']['sisa'] ?>
                  </span>
                  <div class="progress-bar" role="progressbar"
                       aria-valuenow="<?= $pct ?>"
                       aria-valuemin="0" aria-valuemax="100"
                       aria-label="<?= $pct ?>% anggaran terpakai">
                    <div class="progress-bar__fill progress-bar__fill--<?= $warna ?>"
                         style="width: <?= min($pct, 100) ?>%"></div>
                  </div>
                  <span class="budget-cell__pct"><?= number_format($pct, 1) ?>% terpakai</span>
                </div>
              </td>

              <td class="data-table__cell">
                <span class="badge badge--<?= htmlspecialchars($event['status'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= $event['status'] === 'aktif' ? 'Aktif' : 'Draft' ?>
                </span>
              </td>

              <td class="data-table__cell data-table__cell--actions">
                <a href="<?= BASE_URL ?>/staff/events/detail.php?id=<?= (int) $event['id'] ?>"
                   class="btn-icon" title="Lihat detail event"
                   aria-label="Detail event <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                          stroke="currentColor" stroke-width="1.8"/>
                    <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                  </svg>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>


    <!-- ════════════════════════════════════════════════════════
         TABEL TRANSAKSI TERBARU (yang dicatat staff ini)
    ════════════════════════════════════════════════════════ -->
    <section class="dashboard-section" aria-labelledby="section-transaksi">
      <div class="section-header-bar">
        <h2 class="section-header-bar__title" id="section-transaksi">
          Transaksi Terbaru
        </h2>
        <a href="<?= BASE_URL ?>/staff/transaksi/index.php"
           class="section-header-bar__link">
          Lihat semua
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true">
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
        <p class="empty-state__text">Belum ada transaksi yang kamu catat</p>
        <a href="<?= BASE_URL ?>/staff/transaksi/tambah.php" class="btn btn--primary">
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
              <th scope="col">Tanggal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarTrx as $trx) : ?>
            <tr>
              <td class="data-table__cell data-table__cell--primary">
                <?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="data-table__cell">
                <span class="badge badge--<?= htmlspecialchars($trx['tipe'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
                </span>
              </td>
              <td class="data-table__cell">
                <?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?>
              </td>
              <td class="data-table__cell data-table__cell--amount
                         <?= $trx['tipe'] === 'pemasukan' ? 'text-green' : 'text-red' ?>">
                <?= $trx['tipe'] === 'pemasukan' ? '+' : '-' ?>
                <?= format_rupiah($trx['jumlah']) ?>
              </td>
              <td class="data-table__cell">
                <?= badge_status_approval($trx['status_approval']) ?>
              </td>
              <td class="data-table__cell text-muted">
                <time datetime="<?= htmlspecialchars($trx['tanggal_transaksi'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars(date('d M Y', strtotime($trx['tanggal_transaksi'])), ENT_QUOTES, 'UTF-8') ?>
                </time>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

  </div><!-- /admin-content -->
</div><!-- /admin-layout -->

<!--
    Data grafik untuk staff.js → initChartAktivitas() & initChartStatusTrx().
    window.dataAktivitas = { labels: [...], data: [...] }
    window.dataStatusTrx = { labels: [...], data: [...] }
-->
<script>
window.dataAktivitas = {
    labels: <?= json_encode($aktivitasLabels, JSON_UNESCAPED_UNICODE) ?>,
    data:   <?= json_encode($aktivitasData,   JSON_UNESCAPED_UNICODE) ?>
};

window.dataStatusTrx = {
    labels: <?= json_encode($statusTrxLabels, JSON_UNESCAPED_UNICODE) ?>,
    data:   <?= json_encode($statusTrxData,   JSON_UNESCAPED_UNICODE) ?>
};
</script>

<?php
$page_type = 'staff';
require_once __DIR__ . '/../includes/footer.php';
?>