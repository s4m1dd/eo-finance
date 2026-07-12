<?php
/**
 * EO Finance — Struk / cetak bukti transaksi (staff).
 *
 * Standalone, auto-print. Staff hanya bisa cetak transaksi dari event
 * yang dia sendiri ditugaskan (guard via JOIN penugasan).
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
    header('Location: ' . BASE_URL . '/staff/ganti-password.php');
    exit();
}

$userId = get_current_user_id();
$eoId   = get_current_eo_id();
$trxId  = (int) ($_GET['id'] ?? 0);

if ($trxId <= 0) {
    header('Location: ' . BASE_URL . '/staff/transaksi/index.php');
    exit();
}

// ── Ambil data transaksi + guard penugasan ────────────────────────────────────
$stmt = $mysqli->prepare("
    SELECT t.id, t.tipe, t.kategori, t.jumlah, t.deskripsi,
           t.bukti_file, t.bukti_mime, t.tanggal_transaksi, t.created_at,
           t.event_id,
           e.nama_event, e.tanggal_mulai, e.tanggal_selesai,
           e.lokasi, e.status AS status_event,
           k.nama AS nama_klien,
           v.nama AS nama_vendor, v.kategori_layanan AS layanan_vendor,
           u.nama AS nama_creator,
           eo.nama_eo, eo.telepon AS telepon_eo, eo.email AS email_eo
    FROM transaksi t
    INNER JOIN penugasan p ON p.event_id = t.event_id
                           AND p.eo_id   = t.eo_id
                           AND p.user_id = ?
    INNER JOIN events e  ON t.event_id  = e.id
    INNER JOIN eo    eo  ON t.eo_id     = eo.id
    LEFT  JOIN klien k   ON e.klien_id  = k.id
    LEFT  JOIN vendor v  ON t.vendor_id = v.id
    INNER JOIN users u   ON t.created_by = u.id
    WHERE t.id = ? AND t.eo_id = ?
    LIMIT 1
");
$stmt->bind_param('iii', $userId, $trxId, $eoId);
$stmt->execute();
$trx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($trx === null) {
    $_SESSION['__flash']['error'] = 'Transaksi tidak ditemukan atau kamu tidak memiliki akses.';
    header('Location: ' . BASE_URL . '/staff/transaksi/index.php');
    exit();
}

$nomorStruk = 'TRX-' . str_pad($trx['id'], 6, '0', STR_PAD_LEFT);
$isImage    = $trx['bukti_file'] && $trx['bukti_mime']
              && str_starts_with($trx['bukti_mime'], 'image/');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width">
  <title>Struk <?= htmlspecialchars($nomorStruk, ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($trx['nama_eo'], ENT_QUOTES, 'UTF-8') ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Arial, sans-serif;
      font-size: 11pt;
      color: #1D1D1F;
      background: #F5F5F7;
    }

    .struk-wrap {
      max-width: 520px;
      margin: 32px auto;
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 20px rgba(0,0,0,.12);
      overflow: hidden;
    }

    .struk-head {
      background: #1D1D1F;
      color: #fff;
      padding: 24px 28px 20px;
    }

    .struk-head__eo {
      font-size: 13pt;
      font-weight: 700;
      letter-spacing: -.01em;
      margin-bottom: 2px;
    }

    .struk-head__sub {
      font-size: 9pt;
      color: rgba(255,255,255,.55);
    }

    .struk-head__title {
      margin-top: 16px;
      font-size: 9pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: rgba(255,255,255,.55);
    }

    .struk-head__nomor {
      font-size: 18pt;
      font-weight: 700;
      letter-spacing: -.02em;
      margin-top: 2px;
    }

    .struk-amount {
      padding: 20px 28px;
      border-bottom: 1px solid #E8E8ED;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
    }

    .struk-amount__label {
      font-size: 9pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #6E6E73;
    }

    .struk-amount__value {
      font-size: 22pt;
      font-weight: 700;
      letter-spacing: -.02em;
    }

    .struk-amount__value--green { color: #1D9E75; }
    .struk-amount__value--red   { color: #E24B4A; }

    .struk-body { padding: 8px 28px 24px; }

    .struk-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 16px;
      padding: 11px 0;
      border-bottom: 1px solid #F0F0F5;
    }

    .struk-row:last-child { border-bottom: none; }

    .struk-row__label {
      font-size: 9.5pt;
      color: #6E6E73;
      white-space: nowrap;
      min-width: 110px;
      flex-shrink: 0;
    }

    .struk-row__value {
      font-size: 9.5pt;
      font-weight: 500;
      color: #1D1D1F;
      text-align: right;
    }

    .badge {
      display: inline-block;
      padding: 2px 9px;
      border-radius: 99px;
      font-size: 8.5pt;
      font-weight: 600;
    }

    .badge--pemasukan   { background: #EAFAF4; color: #065F46; }
    .badge--pengeluaran { background: #FEF2F2; color: #B91C1C; }

    .struk-section {
      font-size: 8pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #AEAEB2;
      padding: 14px 0 4px;
    }

    .struk-bukti { padding: 0 28px 24px; }

    .struk-bukti__title {
      font-size: 8pt;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #AEAEB2;
      margin-bottom: 10px;
    }

    .struk-bukti__img {
      width: 100%;
      max-height: 220px;
      object-fit: contain;
      border-radius: 8px;
      border: 1px solid #E8E8ED;
    }

    .struk-bukti__link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 9.5pt;
      color: #0071E3;
      text-decoration: none;
      font-weight: 500;
    }

    .struk-foot {
      padding: 14px 28px;
      background: #F5F5F7;
      border-top: 1px solid #E8E8ED;
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 4px;
    }

    .struk-foot__text { font-size: 8.5pt; color: #AEAEB2; }

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
      box-shadow: 0 4px 14px rgba(0,113,227,.35);
    }

    .btn-kembali {
      background: #fff;
      color: #1D1D1F;
      border: 1.5px solid #D2D2D7;
      padding: 9px 18px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 500;
      text-align: center;
      text-decoration: none;
      display: block;
    }

    @media print {
      body         { background: #fff; }
      .screen-only { display: none !important; }
      .struk-wrap  {
        margin: 0;
        max-width: 100%;
        border-radius: 0;
        box-shadow: none;
      }
      @page { margin: 1cm 1.5cm; size: A5 portrait; }
    }
  </style>
</head>
<body>

<div class="struk-wrap">

  <div class="struk-head">
    <p class="struk-head__eo">
      <?= htmlspecialchars($trx['nama_eo'], ENT_QUOTES, 'UTF-8') ?>
    </p>
    <?php if ($trx['email_eo'] || $trx['telepon_eo']) : ?>
    <p class="struk-head__sub">
      <?php if ($trx['email_eo'])   echo htmlspecialchars($trx['email_eo'],   ENT_QUOTES, 'UTF-8'); ?>
      <?php if ($trx['email_eo'] && $trx['telepon_eo']) echo ' · '; ?>
      <?php if ($trx['telepon_eo']) echo htmlspecialchars($trx['telepon_eo'], ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <?php endif; ?>
    <p class="struk-head__title">Bukti Transaksi</p>
    <p class="struk-head__nomor">
      <?= htmlspecialchars($nomorStruk, ENT_QUOTES, 'UTF-8') ?>
    </p>
  </div>

  <div class="struk-amount">
    <p class="struk-amount__label">
      <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
    </p>
    <p class="struk-amount__value struk-amount__value--<?=
        $trx['tipe'] === 'pemasukan' ? 'green' : 'red' ?>">
      <?= $trx['tipe'] === 'pemasukan' ? '+' : '−' ?>
      <?= format_rupiah($trx['jumlah']) ?>
    </p>
  </div>

  <div class="struk-body">

    <p class="struk-section">Informasi Transaksi</p>

    <div class="struk-row">
      <span class="struk-row__label">Tanggal</span>
      <span class="struk-row__value">
        <?= date('d M Y', strtotime($trx['tanggal_transaksi'])) ?>
      </span>
    </div>

    <div class="struk-row">
      <span class="struk-row__label">Tipe</span>
      <span class="struk-row__value">
        <span class="badge badge--<?= $trx['tipe'] ?>">
          <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
        </span>
      </span>
    </div>

    <div class="struk-row">
      <span class="struk-row__label">Kategori</span>
      <span class="struk-row__value">
        <?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>

    <?php if ($trx['nama_vendor']) : ?>
    <div class="struk-row">
      <span class="struk-row__label">Vendor</span>
      <span class="struk-row__value">
        <?= htmlspecialchars($trx['nama_vendor'], ENT_QUOTES, 'UTF-8') ?>
        <?php if ($trx['layanan_vendor']) : ?>
        <br><small style="color:#6E6E73;font-weight:400">
          <?= htmlspecialchars($trx['layanan_vendor'], ENT_QUOTES, 'UTF-8') ?>
        </small>
        <?php endif; ?>
      </span>
    </div>
    <?php endif; ?>

    <?php if ($trx['deskripsi']) : ?>
    <div class="struk-row">
      <span class="struk-row__label">Deskripsi</span>
      <span class="struk-row__value" style="max-width:260px">
        <?= nl2br(htmlspecialchars($trx['deskripsi'], ENT_QUOTES, 'UTF-8')) ?>
      </span>
    </div>
    <?php endif; ?>

    <p class="struk-section">Event Terkait</p>

    <div class="struk-row">
      <span class="struk-row__label">Nama Event</span>
      <span class="struk-row__value" style="max-width:260px">
        <?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>

    <div class="struk-row">
      <span class="struk-row__label">Tanggal Event</span>
      <span class="struk-row__value">
        <?= date('d M Y', strtotime($trx['tanggal_mulai'])) ?>
        <?php if ($trx['tanggal_mulai'] !== $trx['tanggal_selesai']) : ?>
        &mdash; <?= date('d M Y', strtotime($trx['tanggal_selesai'])) ?>
        <?php endif; ?>
      </span>
    </div>

    <?php if ($trx['lokasi']) : ?>
    <div class="struk-row">
      <span class="struk-row__label">Lokasi</span>
      <span class="struk-row__value" style="max-width:260px">
        <?= htmlspecialchars($trx['lokasi'], ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>
    <?php endif; ?>

    <?php if ($trx['nama_klien']) : ?>
    <div class="struk-row">
      <span class="struk-row__label">Klien</span>
      <span class="struk-row__value">
        <?= htmlspecialchars($trx['nama_klien'], ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>
    <?php endif; ?>

  </div><!-- /struk-body -->

  <?php if ($isImage) : ?>
  <div class="struk-bukti">
    <p class="struk-bukti__title">Bukti Pembayaran</p>
    <img src="<?= BASE_URL . '/serve-bukti.php?id=' . $trxId ?>"
         alt="Bukti pembayaran <?= htmlspecialchars($nomorStruk, ENT_QUOTES, 'UTF-8') ?>"
         class="struk-bukti__img">
  </div>
  <?php elseif ($trx['bukti_file']) : ?>
  <div class="struk-bukti">
    <p class="struk-bukti__title">Bukti Pembayaran</p>
    <a href="<?= BASE_URL . '/serve-bukti.php?id=' . $trxId ?>"
       target="_blank" rel="noopener" class="struk-bukti__link">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
              stroke="currentColor" stroke-width="1.8"/>
        <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="1.8"/>
      </svg>
      Lihat file bukti
    </a>
  </div>
  <?php endif; ?>

  <div class="struk-foot">
    <span class="struk-foot__text">
      Dicatat oleh: <?= htmlspecialchars($trx['nama_creator'], ENT_QUOTES, 'UTF-8') ?>
    </span>
    <span class="struk-foot__text">
      <?= date('d M Y, H:i', strtotime($trx['created_at'])) ?> WIB
    </span>
  </div>

</div><!-- /struk-wrap -->

<div class="screen-only" aria-hidden="true">
  <button class="btn-cetak" onclick="window.print()">
    🖨️&nbsp; Cetak / Simpan PDF
  </button>
  <a href="<?= BASE_URL ?>/staff/transaksi/detail.php?id=<?= $trxId ?>"
     class="btn-kembali">← Kembali</a>
</div>

<script>
  window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 400);
  });
</script>

</body>
</html>