<?php
/**
 * EO Finance — Kalender Event.
 *
 * Menampilkan semua event milik EO dalam tampilan kalender bulanan.
 * Event multi-hari ditandai sebagai "spanning" dari tanggal mulai
 * sampai tanggal selesai. Klik event membuka detail event.
 *
 * Navigasi antar bulan pakai query string ?bulan=M&tahun=Y.
 * Tidak ada dependensi JS eksternal — murni PHP + CSS Grid.
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

// ── Parameter bulan/tahun ─────────────────────────────────────────────────────
$now       = new DateTimeImmutable();
$bulan     = max(1, min(12, (int) ($_GET['bulan'] ?? $now->format('n'))));
$tahun     = max(2000, min(2100, (int) ($_GET['tahun'] ?? $now->format('Y'))));

$periodeObj  = new DateTimeImmutable(sprintf('%04d-%02d-01', $tahun, $bulan));
$periodeSebelum = $periodeObj->modify('-1 month');
$periodeSetelah = $periodeObj->modify('+1 month');

$namaHari  = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$namaBulan = [
    1  => 'Januari',   2  => 'Februari',  3  => 'Maret',
    4  => 'April',     5  => 'Mei',       6  => 'Juni',
    7  => 'Juli',      8  => 'Agustus',   9  => 'September',
    10 => 'Oktober',   11 => 'November',  12 => 'Desember',
];

$jumlahHari   = (int) $periodeObj->format('t');
$hariPertama  = (int) $periodeObj->format('w'); // 0=Minggu
$periodeAkhir = $periodeObj->modify('last day of this month');

// ── Ambil semua event yang "menyentuh" bulan ini ──────────────────────────────
// Event dianggap muncul di bulan ini jika tanggal_mulai <= akhir bulan
// DAN tanggal_selesai >= awal bulan (overlap).
$awalBulan = sprintf('%04d-%02d-01', $tahun, $bulan);
$akhirBulan = $periodeAkhir->format('Y-m-d');

$stmt = $mysqli->prepare(
    "SELECT e.id, e.nama_event, e.tanggal_mulai, e.tanggal_selesai,
            e.status, e.lokasi,
            k.nama AS nama_klien
     FROM events e
     LEFT JOIN klien k ON e.klien_id = k.id
     WHERE e.eo_id = ?
       AND e.tanggal_mulai <= ?
       AND e.tanggal_selesai >= ?
     ORDER BY e.tanggal_mulai ASC, e.id ASC"
);
$stmt->bind_param('iss', $eoId, $akhirBulan, $awalBulan);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ── Kelompokkan event per tanggal ─────────────────────────────────────────────
// Untuk setiap event multi-hari, sebar ke semua tanggal dalam bulan yang tercover.
$eventPerTanggal = []; // ['Y-m-d' => [event, ...]]

foreach ($events as $ev) {
    $start = new DateTimeImmutable($ev['tanggal_mulai']);
    $end   = new DateTimeImmutable($ev['tanggal_selesai']);

    // Klem ke batas bulan yang ditampilkan
    $iterStart = $start < $periodeObj        ? $periodeObj        : $start;
    $iterEnd   = $end   > $periodeAkhir      ? $periodeAkhir      : $end;

    $iter = $iterStart;
    while ($iter <= $iterEnd) {
        $key = $iter->format('Y-m-d');
        $eventPerTanggal[$key][] = $ev;
        $iter = $iter->modify('+1 day');
    }
}

// ── Warna per status (untuk pill event di kalender) ───────────────────────────
$warnaPill = [
    'aktif'      => ['bg' => '#D1FAE5', 'text' => '#065F46', 'border' => '#6EE7B7'],
    'draft'      => ['bg' => '#F3F4F6', 'text' => '#374151', 'border' => '#D1D5DB'],
    'selesai'    => ['bg' => '#DBEAFE', 'text' => '#1E40AF', 'border' => '#93C5FD'],
    'dibatalkan' => ['bg' => '#FEE2E2', 'text' => '#991B1B', 'border' => '#FCA5A5'],
];

$pageTitle = 'Kalender Event';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <!-- Header navigasi bulan -->
    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Kalender Event</h1>
        <p class="page-subtitle"><?= $namaBulan[$bulan] . ' ' . $tahun ?></p>
      </div>
      <div class="page-header__right" style="display:flex;gap:8px;align-items:center">
        <!-- Navigasi bulan -->
        <a href="?bulan=<?= $periodeSebelum->format('n') ?>&tahun=<?= $periodeSebelum->format('Y') ?>"
           class="btn btn--outline btn--sm" aria-label="Bulan sebelumnya">← Sebelumnya</a>

        <form method="GET" style="display:flex;gap:6px;align-items:center">
          <select name="bulan" class="form-input filter-select" style="width:auto"
                  onchange="this.form.submit()">
            <?php foreach ($namaBulan as $n => $nm) : ?>
            <option value="<?= $n ?>" <?= $bulan === $n ? 'selected' : '' ?>><?= $nm ?></option>
            <?php endforeach; ?>
          </select>
          <select name="tahun" class="form-input filter-select" style="width:auto"
                  onchange="this.form.submit()">
            <?php for ($y = 2023; $y <= 2028; $y++) : ?>
            <option value="<?= $y ?>" <?= $tahun === $y ? 'selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
          </select>
        </form>

        <a href="?bulan=<?= $periodeSetelah->format('n') ?>&tahun=<?= $periodeSetelah->format('Y') ?>"
           class="btn btn--outline btn--sm" aria-label="Bulan berikutnya">Berikutnya →</a>

        <?php
        $bulanIni = (int) $now->format('n');
        $tahunIni = (int) $now->format('Y');
        if ($bulan !== $bulanIni || $tahun !== $tahunIni) :
        ?>
        <a href="?bulan=<?= $bulanIni ?>&tahun=<?= $tahunIni ?>"
           class="btn btn--primary btn--sm">Hari Ini</a>
        <?php endif; ?>

        <a href="<?= BASE_URL ?>/admin/events/tambah.php" class="btn btn--primary btn--sm">
          + Event Baru
        </a>
      </div>
    </div>

    <!-- Legenda -->
    <div class="kal-legend">
      <?php foreach ($warnaPill as $status => $warna) : ?>
      <span class="kal-legend__item" style="background:<?= $warna['bg'] ?>;color:<?= $warna['text'] ?>;border-color:<?= $warna['border'] ?>">
        <?= ucfirst($status) ?>
      </span>
      <?php endforeach; ?>
      <?php if ($bulan === $bulanIni && $tahun === $tahunIni) : ?>
      <span class="kal-legend__item kal-legend__item--hari-ini">Hari Ini</span>
      <?php endif; ?>
    </div>

    <!-- Grid kalender -->
    <div class="kal-grid">
      <!-- Header nama hari -->
      <?php foreach ($namaHari as $nh) : ?>
      <div class="kal-header"><?= $nh ?></div>
      <?php endforeach; ?>

      <!-- Offset hari pertama bulan -->
      <?php for ($i = 0; $i < $hariPertama; $i++) : ?>
      <div class="kal-hari kal-hari--kosong"></div>
      <?php endfor; ?>

      <!-- Hari-hari dalam bulan -->
      <?php
      $hariIni = $now->format('Y-m-d');
      for ($hari = 1; $hari <= $jumlahHari; $hari++) :
          $tglStr  = sprintf('%04d-%02d-%02d', $tahun, $bulan, $hari);
          $isHariIni = $tglStr === $hariIni;
          $evHariIni = $eventPerTanggal[$tglStr] ?? [];
          $isWeekend = in_array(
              (new DateTimeImmutable($tglStr))->format('w'),
              ['0', '6'],
              true
          );
      ?>
      <div class="kal-hari<?= $isHariIni ? ' kal-hari--hari-ini' : '' ?><?= $isWeekend ? ' kal-hari--weekend' : '' ?>">
        <span class="kal-hari__angka<?= $isHariIni ? ' kal-hari__angka--hari-ini' : '' ?>">
          <?= $hari ?>
        </span>

        <?php if (!empty($evHariIni)) : ?>
        <div class="kal-hari__events">
          <?php
          // Tampilkan max 3 event per hari, sisanya "…dan N lagi"
          $maxTampil = 3;
          $total     = count($evHariIni);
          $tampil    = array_slice($evHariIni, 0, $maxTampil);
          ?>
          <?php foreach ($tampil as $ev) :
            $warna   = $warnaPill[$ev['status']] ?? $warnaPill['draft'];
            $isStart = $ev['tanggal_mulai'] === $tglStr;
            $isEnd   = $ev['tanggal_selesai'] === $tglStr;
            $isSameDay = $ev['tanggal_mulai'] === $ev['tanggal_selesai'];
            $labelStr  = htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8');
          ?>
          <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$ev['id'] ?>"
             class="kal-event"
             style="background:<?= $warna['bg'] ?>;color:<?= $warna['text'] ?>;border-left-color:<?= $warna['border'] ?>"
             title="<?= $labelStr . ($ev['nama_klien'] ? ' — ' . htmlspecialchars($ev['nama_klien'], ENT_QUOTES, 'UTF-8') : '') . PHP_EOL . 'Mulai: ' . date('d M Y', strtotime($ev['tanggal_mulai'])) . PHP_EOL . 'Selesai: ' . date('d M Y', strtotime($ev['tanggal_selesai'])) ?>">
            <?php if ($isStart || $isSameDay) : ?>
            <span class="kal-event__dot"></span>
            <?php endif; ?>
            <span class="kal-event__nama"><?= $labelStr ?></span>
          </a>
          <?php endforeach; ?>

          <?php if ($total > $maxTampil) : ?>
          <span class="kal-event kal-event--more">
            +<?= $total - $maxTampil ?> lagi
          </span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endfor; ?>

      <!-- Padding penutup kalender supaya grid selesai di baris penuh -->
      <?php
      $totalKotak  = $hariPertama + $jumlahHari;
      $sisaKotak   = (7 - ($totalKotak % 7)) % 7;
      for ($i = 0; $i < $sisaKotak; $i++) :
      ?>
      <div class="kal-hari kal-hari--kosong"></div>
      <?php endfor; ?>

    </div><!-- /kal-grid -->

    <!-- Daftar event bulan ini (di bawah kalender, sebagai referensi cepat) -->
    <?php
    // Ambil ulang semua event bulan ini (tidak per hari, tapi per event unik)
    $uniqueEvents = [];
    foreach ($events as $ev) {
        $uniqueEvents[$ev['id']] = $ev;
    }
    $uniqueEvents = array_values($uniqueEvents);
    usort($uniqueEvents, fn($a, $b) => strcmp($a['tanggal_mulai'], $b['tanggal_mulai']));
    ?>
    <?php if (!empty($uniqueEvents)) : ?>
    <div class="card" style="margin-top:24px">
      <div class="card__header">
        <h2 class="card__title">
          Event <?= $namaBulan[$bulan] . ' ' . $tahun ?>
          <span style="font-weight:400;color:var(--text-secondary);font-size:14px">
            — <?= count($uniqueEvents) ?> event
          </span>
        </h2>
      </div>
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th scope="col">Event</th>
              <th scope="col">Klien</th>
              <th scope="col">Mulai</th>
              <th scope="col">Selesai</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($uniqueEvents as $ev) : ?>
            <tr>
              <td class="data-table__cell data-table__cell--primary">
                <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$ev['id'] ?>"
                   class="table-link">
                  <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </td>
              <td class="data-table__cell text-muted">
                <?= $ev['nama_klien']
                    ? htmlspecialchars($ev['nama_klien'], ENT_QUOTES, 'UTF-8')
                    : '—' ?>
              </td>
              <td class="data-table__cell" style="white-space:nowrap">
                <?= date('d M Y', strtotime($ev['tanggal_mulai'])) ?>
              </td>
              <td class="data-table__cell" style="white-space:nowrap">
                <?= date('d M Y', strtotime($ev['tanggal_selesai'])) ?>
              </td>
              <td class="data-table__cell">
                <span class="badge badge--<?= htmlspecialchars($ev['status'], ENT_QUOTES, 'UTF-8') ?>">
                  <?= ucfirst($ev['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>