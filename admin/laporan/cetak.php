<?php
/**
 * EO Finance — Halaman cetak laporan keuangan.
 *
 * Versi print-ready dari laporan/index.php.
 * Dibuka di tab baru, browser langsung menampilkan dialog cetak
 * via window.print() yang dipanggil otomatis saat halaman dimuat.
 *
 * Filter yang diterima (sama dengan index.php):
 *   ?event_id=N  → filter satu event
 *   ?bulan=N     → filter bulan (1-12)
 *   ?tahun=N     → filter tahun
 *
 * Tidak menggunakan header.php / footer.php / navbar —
 * halaman ini standalone agar tidak ada elemen UI yang ikut tercetak.
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

// ── Parameter filter ──────────────────────────────────────────────────────────
$filterEventId = (int) ($_GET['event_id'] ?? 0);
$filterBulan   = (int) ($_GET['bulan']    ?? 0);
$filterTahun   = (int) ($_GET['tahun']    ?? date('Y'));

if ($filterBulan < 0 || $filterBulan > 12)     $filterBulan = 0;
if ($filterTahun < 2000 || $filterTahun > 2100) $filterTahun = (int) date('Y');

// ── Ambil nama EO ─────────────────────────────────────────────────────────────
$stmtEO = $mysqli->prepare('SELECT nama_eo FROM eo WHERE id = ? LIMIT 1');
$stmtEO->bind_param('i', $eoId);
$stmtEO->execute();
$namaEO = $stmtEO->get_result()->fetch_assoc()['nama_eo'] ?? APP_NAME;
$stmtEO->close();

// ── Nama event jika filter spesifik ──────────────────────────────────────────
$namaEventFilter = 'Semua Event';
if ($filterEventId > 0) {
    $stmtEvNama = $mysqli->prepare(
        'SELECT nama_event FROM events WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtEvNama->bind_param('ii', $filterEventId, $eoId);
    $stmtEvNama->execute();
    $rowEv = $stmtEvNama->get_result()->fetch_assoc();
    $stmtEvNama->close();
    if ($rowEv) $namaEventFilter = $rowEv['nama_event'];
}

// ── Bangun WHERE ──────────────────────────────────────────────────────────────
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

// ── Ringkasan ─────────────────────────────────────────────────────────────────
$stmtSum = $mysqli->prepare(
    "SELECT
        COUNT(t.id) AS total_transaksi,
        COALESCE(SUM(CASE WHEN t.tipe='pemasukan'   THEN t.jumlah ELSE 0 END), 0) AS total_pemasukan,
        COALESCE(SUM(CASE WHEN t.tipe='pengeluaran' THEN t.jumlah ELSE 0 END), 0) AS total_pengeluaran
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     $where"
);
$stmtSum->bind_param($types, ...$params);
$stmtSum->execute();
$ringkasan        = $stmtSum->get_result()->fetch_assoc();
$stmtSum->close();

$totalPemasukan   = (float) $ringkasan['total_pemasukan'];
$totalPengeluaran = (float) $ringkasan['total_pengeluaran'];
$totalProfit      = $totalPemasukan - $totalPengeluaran;

// ── Rekapitulasi per event ────────────────────────────────────────────────────
// BUGFIX (Minggu 5): filter bulan/tahun sebelumnya ditaruh di WHERE, padahal
// query ini pakai LEFT JOIN transaksi. Akibatnya event yang TIDAK punya
// transaksi pada bulan/tahun yang difilter ikut hilang dari rekap (harusnya
// tetap muncul dengan nilai Rp 0), karena WHERE menyaring baris hasil JOIN
// — termasuk baris event "kosong" yang seharusnya dipertahankan LEFT JOIN.
// Solusi: filter bulan/tahun dipindah ke klausa ON (ikut menentukan baris
// transaksi mana yang dicocokkan), sedangkan filter event/eo_id tetap di
// WHERE (menentukan baris event mana yang ditampilkan).
$onEvent = "t.status_approval = 'approved'"
    . ($filterBulan > 0 ? ' AND MONTH(t.tanggal_transaksi) = ?' : '')
    . ($filterTahun > 0 ? ' AND YEAR(t.tanggal_transaksi)  = ?' : '');

$whereEvent = 'WHERE e.eo_id = ?'
    . ($filterEventId > 0 ? ' AND e.id = ?' : '');

$paramEv  = [];
$typesEv  = '';
if ($filterBulan   > 0) { $paramEv[] = $filterBulan;   $typesEv .= 'i'; }
if ($filterTahun   > 0) { $paramEv[] = $filterTahun;   $typesEv .= 'i'; }
$paramEv[] = $eoId; $typesEv .= 'i';
if ($filterEventId > 0) { $paramEv[] = $filterEventId; $typesEv .= 'i'; }

$stmtEv = $mysqli->prepare(
    "SELECT e.id, e.nama_event, e.anggaran, e.status,
            COALESCE(SUM(CASE WHEN t.tipe='pemasukan'   THEN t.jumlah ELSE 0 END), 0) AS pemasukan,
            COALESCE(SUM(CASE WHEN t.tipe='pengeluaran' THEN t.jumlah ELSE 0 END), 0) AS pengeluaran,
            COUNT(t.id) AS jumlah_transaksi
     FROM events e
     LEFT JOIN transaksi t ON t.event_id = e.id AND ($onEvent)
     $whereEvent
     GROUP BY e.id
     ORDER BY e.tanggal_mulai DESC"
);
$stmtEv->bind_param($typesEv, ...$paramEv);
$stmtEv->execute();
$rekapEvent = $stmtEv->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEv->close();

// ── Rekap per kategori ────────────────────────────────────────────────────────
$stmtKat = $mysqli->prepare(
    "SELECT t.tipe, t.kategori,
            COUNT(t.id) AS jumlah,
            SUM(t.jumlah) AS total
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     $where
     GROUP BY t.tipe, t.kategori
     ORDER BY t.tipe ASC, total DESC"
);
$stmtKat->bind_param($types, ...$params);
$stmtKat->execute();
$rekapKategori = $stmtKat->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtKat->close();

// ── Detail transaksi ──────────────────────────────────────────────────────────
$stmtTrx = $mysqli->prepare(
    "SELECT t.tanggal_transaksi, e.nama_event, t.tipe,
            t.kategori, t.jumlah, t.deskripsi,
            v.nama AS nama_vendor, u.nama AS nama_user
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     INNER JOIN users  u ON t.created_by = u.id
     LEFT  JOIN vendor v ON t.vendor_id  = v.id
     $where
     ORDER BY t.tanggal_transaksi ASC, t.created_at ASC"
);
$stmtTrx->bind_param($types, ...$params);
$stmtTrx->execute();
$detailTrx = $stmtTrx->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTrx->close();

// ── Label & periode ───────────────────────────────────────────────────────────
$namaBulan = [
    1=>'Januari', 2=>'Februari', 3=>'Maret',    4=>'April',
    5=>'Mei',     6=>'Juni',     7=>'Juli',      8=>'Agustus',
    9=>'September',10=>'Oktober',11=>'November', 12=>'Desember',
];
$labelStatus = [
    'draft'=>'Draft','aktif'=>'Aktif',
    'selesai'=>'Selesai','dibatalkan'=>'Dibatalkan',
];
$periodeLabel = ($filterBulan > 0 ? $namaBulan[$filterBulan] . ' ' : '') . $filterTahun;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width">
  <title>Laporan Keuangan — <?= htmlspecialchars($namaEO, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    /* ── Reset ──────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
      font-size: 11pt;
      color: #1D1D1F;
      background: #fff;
      padding: 0;
    }

    /* ── Wrapper cetak ───────────────────────────────────── */
    .page {
      max-width: 960px;
      margin: auto;
      padding: 32px 40px;
    }

    /* ── Header laporan ──────────────────────────────────── */
    .lap-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 14px;
      border-bottom: 2px solid #1D1D1F;
      margin-bottom: 18px;
    }

    .lap-brand {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 6px;
    }

    .lap-brand-logo {
      width: 32px;
      height: 32px;
    }

    .lap-brand-name {
      font-size: 14pt;
      font-weight: 700;
      color: #1D1D1F;
    }

    .lap-title {
      font-size: 20pt;
      font-weight: 700;
      color: #1D1D1F;
      line-height: 1.2;
    }

    .lap-subtitle {
      font-size: 10pt;
      color: #6E6E73;
      margin-top: 3px;
    }

    .lap-meta {
      text-align: right;
      font-size: 9pt;
      color: #6E6E73;
      line-height: 1.8;
    }

    /* ── Ringkasan 4 kotak ───────────────────────────────── */
    .lap-summary {
      display: flex;
      border: 1px solid #D2D2D7;
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 22px;
    }

    .lap-sum-item {
      flex: 1;
      padding: 12px 16px;
      border-right: 1px solid #D2D2D7;
    }

    .lap-sum-item:last-child { border-right: none; }

    .lap-sum-label {
      font-size: 8pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #6E6E73;
      margin-bottom: 4px;
    }

    .lap-sum-value {
      font-size: 14pt;
      font-weight: 700;
      color: #1D1D1F;
    }

    .color-green { color: #1D9E75; }
    .color-red   { color: #E24B4A; }

    /* ── Section title ───────────────────────────────────── */
    .section-title {
      font-size: 9pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #6E6E73;
      margin: 20px 0 8px;
      padding-bottom: 4px;
      border-bottom: 1px solid #E8E8ED;
    }

    /* ── Tabel ───────────────────────────────────────────── */
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 6px;
      font-size: 9.5pt;
    }

    thead { background: #F5F5F7; }

    th {
      padding: 7px 10px;
      text-align: left;
      font-size: 8pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #6E6E73;
      border-bottom: 1.5px solid #D2D2D7;
      white-space: nowrap;
    }

    td {
      padding: 7px 10px;
      border-bottom: 1px solid #E8E8ED;
      vertical-align: middle;
      color: #1D1D1F;
    }

    tbody tr:last-child td { border-bottom: none; }

    tbody tr:nth-child(even) { background: #FAFAFA; }

    tfoot td {
      font-weight: 700;
      background: #F5F5F7;
      border-top: 1.5px solid #D2D2D7;
    }

    .amount {
      text-align: right;
      font-variant-numeric: tabular-nums;
      white-space: nowrap;
    }

    .progress-wrap {
      display: flex;
      align-items: center;
      gap: 6px;
      min-width: 100px;
    }

    .progress-bar {
      flex: 1;
      height: 6px;
      background: #E8E8ED;
      border-radius: 99px;
      overflow: hidden;
    }

    .progress-fill-hijau  { height: 100%; background: #1D9E75; border-radius: 99px; }
    .progress-fill-kuning { height: 100%; background: #EF9F27; border-radius: 99px; }
    .progress-fill-merah  { height: 100%; background: #E24B4A; border-radius: 99px; }

    .progress-pct { font-size: 8pt; color: #6E6E73; white-space: nowrap; }

    /* Badge */
    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 99px;
      font-size: 8pt;
      font-weight: 600;
    }
    .badge-pemasukan   { background: #EAFAF4; color: #065F46; }
    .badge-pengeluaran { background: #FEF2F2; color: #B91C1C; }
    .badge-aktif       { background: #EAFAF4; color: #065F46; }
    .badge-draft       { background: #F5F5F7; color: #6E6E73; border: 1px solid #D2D2D7; }
    .badge-selesai     { background: #EBF4FF; color: #1E40AF; }
    .badge-dibatalkan  { background: #FEF2F2; color: #B91C1C; }

    /* ── Footer cetak ────────────────────────────────────── */
    .lap-footer {
      margin-top: 28px;
      padding-top: 10px;
      border-top: 1px solid #D2D2D7;
      display: flex;
      justify-content: space-between;
      font-size: 8pt;
      color: #AEAEB2;
    }

    /* ── Tombol aksi — hanya tampil di layar ─────────────── */
    .screen-only {
      position: fixed;
      bottom: 24px;
      right: 24px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      z-index: 999;
    }

    .btn-cetak {
      background: #0071E3;
      color: #fff;
      border: none;
      padding: 11px 22px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(0, 113, 227, .35);
    }

    .btn-kembali {
      background: #fff;
      color: #1D1D1F;
      border: 1.5px solid #D2D2D7;
      padding: 9px 18px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
      display: block;
    }

    /* ── Print: sembunyikan tombol, atur margin ──────────── */
    @media print {
      .screen-only { display: none !important; }
      body         { padding: 0; }
      .page        { padding: 0; max-width: 100%; }
      @page        { margin: 1.5cm 1.5cm 1.5cm 1.5cm; size: A4; }
      table        { page-break-inside: auto; }
      tr           { page-break-inside: avoid; }
      .section-title { page-break-after: avoid; }
      h2, h3       { page-break-after: avoid; }
    }
  </style>
</head>
<body>

<div class="page">

  <!-- ── Header ────────────────────────────────────────────── -->
  <div class="lap-header">
    <div>
      <div class="lap-brand">
        <svg class="lap-brand-logo" viewBox="0 0 28 28" fill="none"
             xmlns="http://www.w3.org/2000/svg">
          <rect width="28" height="28" rx="8" fill="#0071E3"/>
          <path d="M8 20L12 13L16 16.5L20 8"
                stroke="white" stroke-width="2.2"
                stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="20" cy="8" r="2" fill="white"/>
        </svg>
        <span class="lap-brand-name"><?= APP_NAME ?></span>
      </div>
      <p class="lap-title">Laporan Keuangan</p>
      <p class="lap-subtitle">
        <?= htmlspecialchars($namaEO, ENT_QUOTES, 'UTF-8') ?>
        &nbsp;·&nbsp;
        <?= htmlspecialchars($namaEventFilter, ENT_QUOTES, 'UTF-8') ?>
        &nbsp;·&nbsp;
        <?= htmlspecialchars($periodeLabel, ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>
    <div class="lap-meta">
      <p>Dicetak: <?= date('d M Y, H:i') ?> WIB</p>
      <p>Total transaksi: <?= (int)$ringkasan['total_transaksi'] ?></p>
    </div>
  </div>

  <!-- ── Ringkasan ──────────────────────────────────────────── -->
  <div class="lap-summary">
    <div class="lap-sum-item">
      <p class="lap-sum-label">Total Pemasukan</p>
      <p class="lap-sum-value color-green">
        <?= format_rupiah($totalPemasukan) ?>
      </p>
    </div>
    <div class="lap-sum-item">
      <p class="lap-sum-label">Total Pengeluaran</p>
      <p class="lap-sum-value color-red">
        <?= format_rupiah($totalPengeluaran) ?>
      </p>
    </div>
    <div class="lap-sum-item">
      <p class="lap-sum-label">Profit / Selisih</p>
      <p class="lap-sum-value <?= $totalProfit >= 0 ? 'color-green' : 'color-red' ?>">
        <?= format_rupiah($totalProfit) ?>
      </p>
    </div>
    <div class="lap-sum-item">
      <p class="lap-sum-label">Jumlah Transaksi</p>
      <p class="lap-sum-value"><?= (int)$ringkasan['total_transaksi'] ?></p>
    </div>
  </div>

  <!-- ── Rekapitulasi per Event ──────────────────────────────── -->
  <?php if (!empty($rekapEvent)) : ?>
  <p class="section-title">Rekapitulasi per Event</p>
  <table>
    <thead>
      <tr>
        <th>Event</th>
        <th>Status</th>
        <th class="amount">Anggaran</th>
        <th class="amount">Pemasukan</th>
        <th class="amount">Pengeluaran</th>
        <th class="amount">Profit</th>
        <th>Serapan</th>
        <th style="text-align:center">Trx</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rekapEvent as $re) :
          $profit  = (float)$re['pemasukan'] - (float)$re['pengeluaran'];
          $serapan = (float)$re['anggaran'] > 0
                     ? round(((float)$re['pengeluaran'] / (float)$re['anggaran']) * 100, 1)
                     : 0;
          $warna   = $serapan > 90 ? 'merah' : ($serapan > 70 ? 'kuning' : 'hijau');
      ?>
      <tr>
        <td><?= htmlspecialchars($re['nama_event'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <span class="badge badge-<?= $re['status'] ?>">
            <?= $labelStatus[$re['status']] ?? $re['status'] ?>
          </span>
        </td>
        <td class="amount"><?= format_rupiah($re['anggaran']) ?></td>
        <td class="amount color-green"><?= format_rupiah($re['pemasukan']) ?></td>
        <td class="amount color-red"><?= format_rupiah($re['pengeluaran']) ?></td>
        <td class="amount <?= $profit >= 0 ? 'color-green' : 'color-red' ?>">
          <?= format_rupiah($profit) ?>
        </td>
        <td>
          <div class="progress-wrap">
            <div class="progress-bar">
              <div class="progress-fill-<?= $warna ?>"
                   style="width:<?= min($serapan, 100) ?>%"></div>
            </div>
            <span class="progress-pct"><?= $serapan ?>%</span>
          </div>
        </td>
        <td style="text-align:center"><?= (int)$re['jumlah_transaksi'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" style="text-align:right">Total</td>
        <td class="amount color-green"><?= format_rupiah($totalPemasukan) ?></td>
        <td class="amount color-red"><?= format_rupiah($totalPengeluaran) ?></td>
        <td class="amount <?= $totalProfit >= 0 ? 'color-green' : 'color-red' ?>">
          <?= format_rupiah($totalProfit) ?>
        </td>
        <td colspan="2"></td>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>

  <!-- ── Rekapitulasi per Kategori ───────────────────────────── -->
  <?php if (!empty($rekapKategori)) : ?>
  <p class="section-title">Rekapitulasi per Kategori</p>
  <table>
    <thead>
      <tr>
        <th>Tipe</th>
        <th>Kategori</th>
        <th style="text-align:center">Jumlah Trx</th>
        <th class="amount">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rekapKategori as $rk) : ?>
      <tr>
        <td>
          <span class="badge badge-<?= $rk['tipe'] ?>">
            <?= $rk['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
          </span>
        </td>
        <td><?= htmlspecialchars($rk['kategori'], ENT_QUOTES, 'UTF-8') ?></td>
        <td style="text-align:center"><?= (int)$rk['jumlah'] ?></td>
        <td class="amount <?= $rk['tipe'] === 'pemasukan' ? 'color-green' : 'color-red' ?>">
          <?= format_rupiah($rk['total']) ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <!-- ── Detail Transaksi ─────────────────────────────────────── -->
  <?php if (!empty($detailTrx)) : ?>
  <p class="section-title">
    Detail Transaksi (<?= count($detailTrx) ?>)
  </p>
  <table>
    <thead>
      <tr>
        <th>Tanggal</th>
        <th>Event</th>
        <th>Tipe</th>
        <th>Kategori</th>
        <th>Vendor</th>
        <th class="amount">Jumlah (Rp)</th>
        <th>Dicatat oleh</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($detailTrx as $trx) : ?>
      <tr>
        <td style="white-space:nowrap">
          <?= date('d/m/Y', strtotime($trx['tanggal_transaksi'])) ?>
        </td>
        <td><?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <span class="badge badge-<?= $trx['tipe'] ?>">
            <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
          </span>
        </td>
        <td><?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <?= $trx['nama_vendor']
              ? htmlspecialchars($trx['nama_vendor'], ENT_QUOTES, 'UTF-8')
              : '—' ?>
        </td>
        <td class="amount <?= $trx['tipe'] === 'pemasukan' ? 'color-green' : 'color-red' ?>">
          <?= $trx['tipe'] === 'pemasukan' ? '+' : '−' ?>
          <?= number_format((float)$trx['jumlah'], 0, ',', '.') ?>
        </td>
        <td><?= htmlspecialchars($trx['nama_user'], ENT_QUOTES, 'UTF-8') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5" style="text-align:right;padding-right:10px">
          Profit / Selisih
        </td>
        <td class="amount <?= $totalProfit >= 0 ? 'color-green' : 'color-red' ?>">
          <?= $totalProfit >= 0 ? '+' : '−' ?>
          <?= number_format(abs($totalProfit), 0, ',', '.') ?>
        </td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  <?php else : ?>
  <p style="color:#6E6E73;font-size:10pt;text-align:center;padding:24px 0">
    Tidak ada transaksi untuk filter yang dipilih.
  </p>
  <?php endif; ?>

  <!-- ── Footer ─────────────────────────────────────────────── -->
  <div class="lap-footer">
    <span>
      <?= APP_NAME ?> &nbsp;·&nbsp;
      <?= htmlspecialchars($namaEO, ENT_QUOTES, 'UTF-8') ?>
    </span>
    <span>Dicetak: <?= date('d M Y, H:i') ?> WIB</span>
  </div>

</div><!-- /page -->

<!-- ── Tombol layar (tidak ikut tercetak) ──────────────────── -->
<div class="screen-only" aria-hidden="true">
  <button class="btn-cetak" onclick="window.print()">
    🖨️&nbsp; Cetak / Simpan PDF
  </button>
  <a href="javascript:history.back()" class="btn-kembali">
    ← Kembali
  </a>
</div>

<script>
  // Buka dialog cetak otomatis saat halaman selesai dimuat
  window.addEventListener('load', function () {
    // Tunda 400ms agar render CSS selesai sebelum dialog muncul
    setTimeout(function () { window.print(); }, 400);
  });
</script>

</body>
</html>