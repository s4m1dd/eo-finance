<?php
/**
 * EO Finance — Invoice ke Klien.
 *
 * Halaman standalone (tanpa header/navbar/footer) yang langsung bisa
 * dicetak atau disimpan sebagai PDF via browser.
 * Diakses via ?event_id=N
 *
 * Menampilkan:
 * - Identitas EO (nama, email, telepon)
 * - Identitas klien (nama, email, telepon)
 * - Detail event (nama, tanggal, lokasi)
 * - Daftar transaksi PEMASUKAN yang sudah approved sebagai rincian tagihan
 * - Total, terbilang, dan ruang tanda tangan
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

$eoId    = get_current_eo_id();
$eventId = (int) ($_GET['event_id'] ?? 0);

if ($eventId <= 0) {
    header('Location: ' . BASE_URL . '/admin/events/index.php'); exit();
}

// ── Data event + klien ────────────────────────────────────────────────────────
$stmtEv = $mysqli->prepare("
    SELECT e.id, e.nama_event, e.deskripsi, e.tanggal_mulai, e.tanggal_selesai,
           e.lokasi, e.status, e.anggaran,
           k.nama AS nama_klien, k.email AS email_klien,
           k.telepon AS telepon_klien, k.alamat AS alamat_klien,
           eo.nama_eo, eo.email AS email_eo, eo.telepon AS telepon_eo,
           eo.alamat AS alamat_eo
    FROM events e
    LEFT JOIN klien k ON e.klien_id = k.id
    INNER JOIN eo   ON e.eo_id = eo.id
    WHERE e.id = ? AND e.eo_id = ?
    LIMIT 1
");
$stmtEv->bind_param('ii', $eventId, $eoId);
$stmtEv->execute();
$event = $stmtEv->get_result()->fetch_assoc();
$stmtEv->close();

if ($event === null) {
    header('Location: ' . BASE_URL . '/admin/events/index.php'); exit();
}

// ── Daftar transaksi pemasukan (approved) sebagai rincian tagihan ─────────────
$stmtTrx = $mysqli->prepare("
    SELECT t.id, t.kategori, t.jumlah, t.deskripsi, t.tanggal_transaksi,
           v.nama AS nama_vendor
    FROM transaksi t
    LEFT JOIN vendor v ON t.vendor_id = v.id
    WHERE t.event_id = ? AND t.eo_id = ?
      AND t.tipe = 'pemasukan'
      AND t.status_approval = 'approved'
    ORDER BY t.tanggal_transaksi ASC, t.id ASC
");
$stmtTrx->bind_param('ii', $eventId, $eoId);
$stmtTrx->execute();
$daftarTrx = $stmtTrx->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTrx->close();

$total = array_sum(array_column($daftarTrx, 'jumlah'));

// ── Nomor invoice ─────────────────────────────────────────────────────────────
$nomorInvoice = sprintf(
    'INV-%s-%04d',
    date('Ymd', strtotime($event['tanggal_mulai'])),
    $eventId
);

// ── Terbilang ─────────────────────────────────────────────────────────────────
function terbilang(float $angka): string {
    $angka  = (int) $angka;
    $satuan = ['', 'satu','dua','tiga','empat','lima','enam','tujuh','delapan','sembilan',
               'sepuluh','sebelas'];
    if ($angka < 12)  return $satuan[$angka];
    if ($angka < 20)  return terbilang($angka - 10) . ' belas';
    if ($angka < 100) return terbilang((int)($angka/10)) . ' puluh' . ($angka%10 ? ' ' . terbilang($angka%10) : '');
    if ($angka < 200) return 'seratus' . ($angka - 100 > 0 ? ' ' . terbilang($angka - 100) : '');
    if ($angka < 1000) return terbilang((int)($angka/100)) . ' ratus' . ($angka%100 ? ' ' . terbilang($angka%100) : '');
    if ($angka < 2000) return 'seribu' . ($angka - 1000 > 0 ? ' ' . terbilang($angka - 1000) : '');
    if ($angka < 1000000) return terbilang((int)($angka/1000)) . ' ribu' . ($angka%1000 ? ' ' . terbilang($angka%1000) : '');
    if ($angka < 1000000000) return terbilang((int)($angka/1000000)) . ' juta' . ($angka%1000000 ? ' ' . terbilang($angka%1000000) : '');
    return terbilang((int)($angka/1000000000)) . ' miliar' . ($angka%1000000000 ? ' ' . terbilang($angka%1000000000) : '');
}
$terbilangTotal = ucfirst(terbilang((float)$total)) . ' Rupiah';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Invoice <?= htmlspecialchars($nomorInvoice, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($event['nama_eo'], ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Helvetica Neue', Arial, sans-serif;
      font-size: 13px;
      color: #1a1a2e;
      background: #f5f5f7;
      padding: 24px;
    }

    .invoice-sheet {
      max-width: 800px;
      margin: 0 auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 4px 24px rgba(0,0,0,.10);
      overflow: hidden;
    }

    /* ── Header ──────────────────────────────────────────── */
    .inv-header {
      background: #0071E3;
      color: #fff;
      padding: 36px 40px 32px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 24px;
    }
    .inv-header__brand { flex: 1; }
    .inv-header__brand-name {
      font-size: 22px;
      font-weight: 700;
      letter-spacing: -.3px;
      margin-bottom: 4px;
    }
    .inv-header__brand-sub { font-size: 12px; opacity: .85; line-height: 1.6; }

    .inv-header__meta { text-align: right; }
    .inv-header__meta-label {
      font-size: 11px;
      opacity: .75;
      text-transform: uppercase;
      letter-spacing: .06em;
      margin-bottom: 2px;
    }
    .inv-header__meta-number { font-size: 20px; font-weight: 700; }
    .inv-header__meta-tanggal { font-size: 12px; opacity: .85; margin-top: 4px; }

    /* ── Blok info pembuat & penerima ────────────────────── */
    .inv-parties {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0;
      border-bottom: 1px solid #e8e8ed;
    }
    .inv-party {
      padding: 28px 40px;
    }
    .inv-party + .inv-party {
      border-left: 1px solid #e8e8ed;
    }
    .inv-party__label {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #0071E3;
      margin-bottom: 10px;
    }
    .inv-party__name {
      font-size: 15px;
      font-weight: 700;
      margin-bottom: 4px;
    }
    .inv-party__detail {
      font-size: 12px;
      color: #555;
      line-height: 1.7;
    }

    /* ── Detail event ────────────────────────────────────── */
    .inv-event {
      padding: 20px 40px;
      background: #f9f9fb;
      border-bottom: 1px solid #e8e8ed;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 16px;
    }
    .inv-event__item dt {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #888;
      margin-bottom: 3px;
    }
    .inv-event__item dd {
      font-size: 13px;
      font-weight: 500;
    }

    /* ── Tabel rincian ───────────────────────────────────── */
    .inv-table-wrap { padding: 0 40px; }
    .inv-table {
      width: 100%;
      border-collapse: collapse;
      margin: 28px 0 0;
    }
    .inv-table th {
      font-size: 11px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: #888;
      padding: 8px 12px;
      border-bottom: 2px solid #e8e8ed;
      text-align: left;
    }
    .inv-table th:last-child { text-align: right; }
    .inv-table td {
      padding: 12px 12px;
      border-bottom: 1px solid #f0f0f5;
      vertical-align: top;
      font-size: 13px;
    }
    .inv-table td:last-child { text-align: right; font-weight: 500; }
    .inv-table tr:last-child td { border-bottom: none; }
    .inv-table__sub { font-size: 11px; color: #888; margin-top: 2px; }

    .inv-table__no { color: #aaa; font-size: 12px; }

    /* Total */
    .inv-total-wrap {
      padding: 20px 40px 28px;
      display: flex;
      justify-content: flex-end;
    }
    .inv-total {
      min-width: 260px;
    }
    .inv-total__row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 6px 0;
      font-size: 13px;
    }
    .inv-total__row--grand {
      border-top: 2px solid #1a1a2e;
      margin-top: 8px;
      padding-top: 12px;
      font-size: 16px;
      font-weight: 700;
    }
    .inv-total__terbilang {
      font-size: 12px;
      color: #555;
      font-style: italic;
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px dashed #e8e8ed;
      line-height: 1.5;
    }

    /* Kosong */
    .inv-empty {
      padding: 40px;
      text-align: center;
      color: #aaa;
      font-style: italic;
    }

    /* Footer invoice */
    .inv-footer {
      padding: 24px 40px 36px;
      border-top: 1px solid #e8e8ed;
    }
    .inv-sign {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      margin-top: 24px;
    }
    .inv-sign__box {
      text-align: center;
    }
    .inv-sign__label {
      font-size: 12px;
      color: #555;
      margin-bottom: 60px;
    }
    .inv-sign__line {
      border-top: 1px solid #1a1a2e;
      padding-top: 8px;
      font-size: 13px;
      font-weight: 600;
    }
    .inv-sign__sub {
      font-size: 11px;
      color: #888;
    }

    .inv-note {
      font-size: 12px;
      color: #888;
      line-height: 1.6;
    }

    /* Tombol cetak (tersembunyi saat print) */
    .inv-actions {
      text-align: center;
      padding: 20px;
      background: #f5f5f7;
    }
    .btn-print {
      background: #0071E3;
      color: #fff;
      border: none;
      padding: 10px 28px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      margin: 0 6px;
    }
    .btn-back {
      background: transparent;
      color: #0071E3;
      border: 1px solid #0071E3;
      padding: 10px 28px;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
    }

    @media print {
      body { background: #fff; padding: 0; }
      .invoice-sheet {
        box-shadow: none;
        border-radius: 0;
        max-width: 100%;
      }
      .inv-actions { display: none; }
    }
  </style>
</head>
<body>

<div class="inv-actions">
  <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= $eventId ?>"
     class="btn-back">← Kembali ke Event</a>
  <button class="btn-print" onclick="window.print()">🖨 Cetak / Simpan PDF</button>
</div>

<div class="invoice-sheet">

  <!-- Header -->
  <div class="inv-header">
    <div class="inv-header__brand">
      <div class="inv-header__brand-name">
        <?= htmlspecialchars($event['nama_eo'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="inv-header__brand-sub">
        <?php if ($event['email_eo'])    : ?><?= htmlspecialchars($event['email_eo'],    ENT_QUOTES, 'UTF-8') ?><br><?php endif; ?>
        <?php if ($event['telepon_eo'])  : ?><?= htmlspecialchars($event['telepon_eo'],  ENT_QUOTES, 'UTF-8') ?><br><?php endif; ?>
        <?php if ($event['alamat_eo'])   : ?><?= htmlspecialchars($event['alamat_eo'],   ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
      </div>
    </div>
    <div class="inv-header__meta">
      <div class="inv-header__meta-label">Invoice</div>
      <div class="inv-header__meta-number">
        <?= htmlspecialchars($nomorInvoice, ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="inv-header__meta-tanggal">
        Tanggal: <?= date('d F Y') ?>
      </div>
    </div>
  </div>

  <!-- Dari & Kepada -->
  <div class="inv-parties">
    <div class="inv-party">
      <div class="inv-party__label">Dari</div>
      <div class="inv-party__name">
        <?= htmlspecialchars($event['nama_eo'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="inv-party__detail">
        <?php if ($event['email_eo'])   echo htmlspecialchars($event['email_eo'],   ENT_QUOTES, 'UTF-8') . '<br>'; ?>
        <?php if ($event['telepon_eo']) echo htmlspecialchars($event['telepon_eo'], ENT_QUOTES, 'UTF-8') . '<br>'; ?>
        <?php if ($event['alamat_eo'])  echo nl2br(htmlspecialchars($event['alamat_eo'],  ENT_QUOTES, 'UTF-8')); ?>
      </div>
    </div>
    <div class="inv-party">
      <div class="inv-party__label">Kepada (Klien)</div>
      <?php if ($event['nama_klien']) : ?>
      <div class="inv-party__name">
        <?= htmlspecialchars($event['nama_klien'], ENT_QUOTES, 'UTF-8') ?>
      </div>
      <div class="inv-party__detail">
        <?php if ($event['email_klien'])   echo htmlspecialchars($event['email_klien'],   ENT_QUOTES, 'UTF-8') . '<br>'; ?>
        <?php if ($event['telepon_klien']) echo htmlspecialchars($event['telepon_klien'], ENT_QUOTES, 'UTF-8') . '<br>'; ?>
        <?php if ($event['alamat_klien'])  echo nl2br(htmlspecialchars($event['alamat_klien'],  ENT_QUOTES, 'UTF-8')); ?>
      </div>
      <?php else : ?>
      <div class="inv-party__detail" style="color:#aaa;font-style:italic">
        Tidak ada data klien — tambahkan klien ke event ini.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Detail event -->
  <dl class="inv-event">
    <div class="inv-event__item">
      <dt>Nama Event</dt>
      <dd><?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?></dd>
    </div>
    <div class="inv-event__item">
      <dt>Tanggal Pelaksanaan</dt>
      <dd>
        <?= date('d M Y', strtotime($event['tanggal_mulai'])) ?>
        <?php if ($event['tanggal_mulai'] !== $event['tanggal_selesai']) : ?>
        – <?= date('d M Y', strtotime($event['tanggal_selesai'])) ?>
        <?php endif; ?>
      </dd>
    </div>
    <?php if ($event['lokasi']) : ?>
    <div class="inv-event__item">
      <dt>Lokasi</dt>
      <dd><?= htmlspecialchars($event['lokasi'], ENT_QUOTES, 'UTF-8') ?></dd>
    </div>
    <?php endif; ?>
  </dl>

  <!-- Rincian tagihan -->
  <div class="inv-table-wrap">
    <?php if (empty($daftarTrx)) : ?>
    <div class="inv-empty">
      Belum ada transaksi pemasukan yang disetujui untuk event ini.
    </div>
    <?php else : ?>
    <table class="inv-table">
      <thead>
        <tr>
          <th style="width:40px">No</th>
          <th>Uraian</th>
          <th>Tanggal</th>
          <th>Jumlah</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($daftarTrx as $i => $trx) : ?>
        <tr>
          <td class="inv-table__no"><?= $i + 1 ?></td>
          <td>
            <?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?>
            <?php if ($trx['deskripsi']) : ?>
            <div class="inv-table__sub">
              <?= htmlspecialchars($trx['deskripsi'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>
          </td>
          <td style="white-space:nowrap">
            <?= date('d M Y', strtotime($trx['tanggal_transaksi'])) ?>
          </td>
          <td><?= format_rupiah($trx['jumlah']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <!-- Total -->
  <?php if (!empty($daftarTrx)) : ?>
  <div class="inv-total-wrap">
    <div class="inv-total">
      <div class="inv-total__row">
        <span>Subtotal</span>
        <span><?= format_rupiah($total) ?></span>
      </div>
      <div class="inv-total__row inv-total__row--grand">
        <span>Total</span>
        <span><?= format_rupiah($total) ?></span>
      </div>
      <div class="inv-total__terbilang">
        Terbilang: <em><?= htmlspecialchars($terbilangTotal, ENT_QUOTES, 'UTF-8') ?></em>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Tanda tangan -->
  <div class="inv-footer">
    <div class="inv-sign">
      <div class="inv-sign__box">
        <div class="inv-sign__label">Hormat kami,</div>
        <div class="inv-sign__line">
          <?= htmlspecialchars($event['nama_eo'], ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="inv-sign__sub">Event Organizer</div>
      </div>
      <div class="inv-sign__box">
        <div class="inv-sign__label">Diterima & disetujui oleh,</div>
        <div class="inv-sign__line">
          <?= $event['nama_klien']
              ? htmlspecialchars($event['nama_klien'], ENT_QUOTES, 'UTF-8')
              : '_____________________' ?>
        </div>
        <div class="inv-sign__sub">Klien</div>
      </div>
    </div>

    <p class="inv-note" style="margin-top:28px">
      Dokumen ini dibuat secara otomatis oleh sistem EO Finance pada
      <?= date('d F Y, H:i') ?> WIB.
      <?php if ($event['deskripsi']) : ?>
      <br>Catatan event: <?= htmlspecialchars($event['deskripsi'], ENT_QUOTES, 'UTF-8') ?>
      <?php endif; ?>
    </p>
  </div>

</div>

<script>
// Auto-print setelah halaman siap
setTimeout(function () { window.print(); }, 500);
</script>
</body>
</html>