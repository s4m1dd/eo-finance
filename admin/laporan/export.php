<?php
/**
 * EO Finance — Export laporan keuangan ke CSV atau PDF.
 *
 * Format ditentukan dari GET parameter `format`:
 *   csv → kirim file CSV langsung ke browser (download)
 *   pdf → generate HTML print-ready, browser bisa Ctrl+P ke PDF
 *
 * Filter yang diterima sama dengan laporan/index.php:
 *   event_id, bulan, tahun
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

// ── Parameter ─────────────────────────────────────────────────────────────────
$filterEventId = (int) ($_GET['event_id'] ?? 0);
$filterBulan   = (int) ($_GET['bulan']    ?? 0);
$filterTahun   = (int) ($_GET['tahun']    ?? date('Y'));
$format        = strtolower(trim($_GET['format'] ?? 'csv'));

if (!in_array($format, ['csv', 'pdf'], true)) $format = 'csv';
if ($filterBulan < 0 || $filterBulan > 12)    $filterBulan = 0;
if ($filterTahun < 2000 || $filterTahun > 2100) $filterTahun = (int) date('Y');

// ── Ambil nama EO untuk header laporan ───────────────────────────────────────
$stmtEoNama = $mysqli->prepare('SELECT nama_eo FROM eo WHERE id = ? LIMIT 1');
$stmtEoNama->bind_param('i', $eoId);
$stmtEoNama->execute();
$namaEO = $stmtEoNama->get_result()->fetch_assoc()['nama_eo'] ?? APP_NAME;
$stmtEoNama->close();

// ── Ambil nama event jika filter specific ─────────────────────────────────────
$namaEventFilter = 'Semua Event';
if ($filterEventId > 0) {
    $stmtEvNama = $mysqli->prepare(
        'SELECT nama_event FROM events WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtEvNama->bind_param('ii', $filterEventId, $eoId);
    $stmtEvNama->execute();
    $row = $stmtEvNama->get_result()->fetch_assoc();
    $stmtEvNama->close();
    if ($row) $namaEventFilter = $row['nama_event'];
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
$ringkasan = $stmtSum->get_result()->fetch_assoc();
$stmtSum->close();

$totalPemasukan   = (float) $ringkasan['total_pemasukan'];
$totalPengeluaran = (float) $ringkasan['total_pengeluaran'];
$totalProfit      = $totalPemasukan - $totalPengeluaran;

// ── Data transaksi detail ──────────────────────────────────────────────────────
$stmtTrx = $mysqli->prepare(
    "SELECT t.tanggal_transaksi, e.nama_event, t.tipe, t.kategori,
            t.jumlah, t.deskripsi, v.nama AS nama_vendor, u.nama AS nama_user
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

// ── Label periode ─────────────────────────────────────────────────────────────
$namaBulan = [
    1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',
    5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',
    9=>'September',10=>'Oktober',11=>'November',12=>'Desember',
];
$periodeLabel = ($filterBulan > 0 ? $namaBulan[$filterBulan] . ' ' : '') . $filterTahun;
$namaFile     = 'laporan_' . ($filterBulan > 0 ? $filterBulan . '_' : '') . $filterTahun;

// ════════════════════════════════════════════════════════════════════════════
// FORMAT: CSV
// ════════════════════════════════════════════════════════════════════════════
if ($format === 'csv') {
    // Header HTTP untuk download file
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $namaFile . '.csv"');
    header('Cache-Control: no-store, no-cache');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');

    // BOM UTF-8 agar Excel membuka file dengan encoding benar (terutama di Windows)
    fwrite($out, "\xEF\xBB\xBF");

    // ── Baris header laporan ──────────────────────────────────────────────────
    fputcsv($out, [APP_NAME . ' — Laporan Keuangan']);
    fputcsv($out, ['EO', $namaEO]);
    fputcsv($out, ['Event', $namaEventFilter]);
    fputcsv($out, ['Periode', $periodeLabel]);
    fputcsv($out, ['Digenerate', date('d M Y H:i')]);
    fputcsv($out, []); // baris kosong

    // ── Ringkasan ─────────────────────────────────────────────────────────────
    fputcsv($out, ['RINGKASAN']);
    fputcsv($out, ['Total Pemasukan',   format_rupiah($totalPemasukan)]);
    fputcsv($out, ['Total Pengeluaran', format_rupiah($totalPengeluaran)]);
    fputcsv($out, ['Profit / Selisih',  format_rupiah($totalProfit)]);
    fputcsv($out, ['Total Transaksi',   (int)$ringkasan['total_transaksi']]);
    fputcsv($out, []); // baris kosong

    // ── Header kolom detail ───────────────────────────────────────────────────
    fputcsv($out, [
        'Tanggal', 'Event', 'Tipe', 'Kategori',
        'Jumlah (Rp)', 'Vendor', 'Dicatat oleh', 'Deskripsi',
    ]);

    // ── Baris data ────────────────────────────────────────────────────────────
    foreach ($detailTrx as $trx) {
        fputcsv($out, [
            date('d/m/Y', strtotime($trx['tanggal_transaksi'])),
            $trx['nama_event'],
            $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran',
            $trx['kategori'],
            number_format((float)$trx['jumlah'], 0, ',', '.'),
            $trx['nama_vendor'] ?? '',
            $trx['nama_user'],
            $trx['deskripsi'] ?? '',
        ]);
    }

    // ── Baris total ───────────────────────────────────────────────────────────
    fputcsv($out, []);
    fputcsv($out, [
        '', '', '', 'TOTAL',
        number_format($totalProfit, 0, ',', '.'),
        '', '', '',
    ]);

    fclose($out);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// FORMAT: PDF (HTML print-ready)
// Tidak menggunakan library PDF eksternal — generate HTML yang dioptimasi
// untuk cetak, lalu user tinggal Ctrl+P dan pilih "Save as PDF".
// ════════════════════════════════════════════════════════════════════════════
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width">
  <title>Laporan Keuangan — <?= htmlspecialchars($namaEO, ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    /* Print-optimized CSS — standalone, tidak bergantung style.css */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
      font-size: 11px;
      color: #1D1D1F;
      padding: 24px;
      max-width: 1024px;
      margin: auto;
    }

    /* Header laporan */
    .lap-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 16px;
      border-bottom: 2px solid #1D1D1F;
      margin-bottom: 16px;
    }
    .lap-title    { font-size: 18px; font-weight: 700; }
    .lap-subtitle { font-size: 12px; color: #6E6E73; margin-top: 2px; }
    .lap-meta     { text-align: right; font-size: 11px; color: #6E6E73; line-height: 1.6; }

    /* Ringkasan */
    .lap-summary {
      display: flex;
      gap: 0;
      border: 1px solid #D2D2D7;
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 20px;
    }
    .lap-summary-item {
      flex: 1;
      padding: 12px 16px;
      border-right: 1px solid #D2D2D7;
    }
    .lap-summary-item:last-child { border-right: none; }
    .lap-summary-label {
      font-size: 10px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #6E6E73;
      margin-bottom: 4px;
    }
    .lap-summary-value { font-size: 15px; font-weight: 700; }
    .color-green { color: #1D9E75; }
    .color-red   { color: #E24B4A; }

    /* Tabel */
    .lap-section-title {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #6E6E73;
      margin-bottom: 8px;
      margin-top: 20px;
    }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    thead { background: #F5F5F7; }
    th {
      padding: 7px 10px;
      font-size: 10px;
      font-weight: 700;
      text-align: left;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #6E6E73;
      border-bottom: 1.5px solid #D2D2D7;
    }
    td {
      padding: 7px 10px;
      font-size: 11px;
      border-bottom: 1px solid #E8E8ED;
      vertical-align: middle;
    }
    tr:last-child td { border-bottom: none; }
    .amount { text-align: right; font-variant-numeric: tabular-nums; font-weight: 500; }
    .badge {
      display: inline-block;
      padding: 2px 7px;
      border-radius: 99px;
      font-size: 10px;
      font-weight: 600;
    }
    .badge-pemasukan   { background: #EAFAF4; color: #065F46; }
    .badge-pengeluaran { background: #FEF2F2; color: #B91C1C; }
    tfoot td { font-weight: 700; background: #F5F5F7; }

    /* Footer cetak */
    .lap-footer {
      border-top: 1px solid #D2D2D7;
      padding-top: 10px;
      font-size: 10px;
      color: #AEAEB2;
      display: flex;
      justify-content: space-between;
    }

    /* Tombol cetak — hanya tampil di browser, tidak di print */
    .print-btn {
      position: fixed;
      bottom: 24px;
      right: 24px;
      background: #0071E3;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0,113,227,.4);
    }

    @media print {
      .print-btn { display: none; }
      body { padding: 0; }
      @page { margin: 1.5cm; }
      table { page-break-inside: auto; }
      tr    { page-break-inside: avoid; }
    }
  </style>
</head>
<body>

  <!-- Header -->
  <div class="lap-header">
    <div>
      <p class="lap-title">Laporan Keuangan</p>
      <p class="lap-subtitle">
        <?= htmlspecialchars($namaEO, ENT_QUOTES, 'UTF-8') ?>
        · <?= htmlspecialchars($namaEventFilter, ENT_QUOTES, 'UTF-8') ?>
        · <?= htmlspecialchars($periodeLabel, ENT_QUOTES, 'UTF-8') ?>
      </p>
    </div>
    <div class="lap-meta">
      <p><?= APP_NAME ?></p>
      <p>Digenerate: <?= date('d M Y, H:i') ?></p>
    </div>
  </div>

  <!-- Ringkasan -->
  <div class="lap-summary">
    <div class="lap-summary-item">
      <p class="lap-summary-label">Total Pemasukan</p>
      <p class="lap-summary-value color-green"><?= format_rupiah($totalPemasukan) ?></p>
    </div>
    <div class="lap-summary-item">
      <p class="lap-summary-label">Total Pengeluaran</p>
      <p class="lap-summary-value color-red"><?= format_rupiah($totalPengeluaran) ?></p>
    </div>
    <div class="lap-summary-item">
      <p class="lap-summary-label">Profit / Selisih</p>
      <p class="lap-summary-value <?= $totalProfit >= 0 ? 'color-green' : 'color-red' ?>">
        <?= format_rupiah($totalProfit) ?>
      </p>
    </div>
    <div class="lap-summary-item">
      <p class="lap-summary-label">Total Transaksi</p>
      <p class="lap-summary-value"><?= (int)$ringkasan['total_transaksi'] ?></p>
    </div>
  </div>

  <!-- Tabel detail -->
  <?php if (!empty($detailTrx)) : ?>
  <p class="lap-section-title">Detail Transaksi</p>
  <table>
    <thead>
      <tr>
        <th>Tanggal</th>
        <th>Event</th>
        <th>Tipe</th>
        <th>Kategori</th>
        <th>Vendor</th>
        <th style="text-align:right">Jumlah (Rp)</th>
        <th>Dicatat oleh</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($detailTrx as $trx) : ?>
      <tr>
        <td><?= date('d/m/Y', strtotime($trx['tanggal_transaksi'])) ?></td>
        <td><?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?></td>
        <td>
          <span class="badge badge-<?= $trx['tipe'] ?>">
            <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
          </span>
        </td>
        <td><?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= $trx['nama_vendor']
                ? htmlspecialchars($trx['nama_vendor'], ENT_QUOTES, 'UTF-8')
                : '—' ?></td>
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
        <td colspan="5" style="text-align:right;padding-right:10px">Total Profit</td>
        <td class="amount <?= $totalProfit >= 0 ? 'color-green' : 'color-red' ?>">
          <?= format_rupiah($totalProfit) ?>
        </td>
        <td></td>
      </tr>
    </tfoot>
  </table>
  <?php else : ?>
  <p style="color:#6E6E73;font-size:13px;text-align:center;padding:24px 0">
    Tidak ada transaksi untuk filter yang dipilih.
  </p>
  <?php endif; ?>

  <!-- Footer -->
  <div class="lap-footer">
    <span><?= APP_NAME ?> · <?= htmlspecialchars($namaEO, ENT_QUOTES, 'UTF-8') ?></span>
    <span>Digenerate: <?= date('d M Y, H:i') ?></span>
  </div>

  <!-- Tombol cetak (disembunyikan saat print) -->
  <button class="print-btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>

</body>
</html>
<?php
exit();
?>