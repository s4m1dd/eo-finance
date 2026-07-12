<?php
/**
 * EO Finance — Detail transaksi (staff).
 *
 * Staff hanya bisa melihat transaksi dari event yang ditugaskan kepadanya.
 * Guard: JOIN penugasan memastikan staff tidak bisa akses transaksi event lain.
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
$trxId  = (int) ($_GET['id'] ?? 0);

if ($trxId <= 0) {
    header('Location: ' . BASE_URL . '/staff/transaksi/index.php');
    exit();
}

// ── Ambil data transaksi + guard penugasan ────────────────────────────────────
// Staff hanya bisa akses transaksi dari event yang dia sendiri ditugaskan.
$stmt = $mysqli->prepare("
    SELECT t.id, t.tipe, t.kategori, t.jumlah, t.deskripsi,
           t.bukti_file, t.bukti_mime, t.tanggal_transaksi, t.created_at,
           t.event_id, t.created_by,
           t.status_approval, t.catatan_approval, t.approved_at, t.status_pembayaran,
           e.nama_event, e.tanggal_mulai, e.tanggal_selesai,
           e.lokasi, e.status AS status_event,
           k.nama AS nama_klien,
           v.nama AS nama_vendor, v.kategori_layanan AS layanan_vendor,
           u.nama AS nama_creator,
           approver.nama AS nama_approver,
           eo.nama_eo
    FROM transaksi t
    INNER JOIN penugasan p ON p.event_id = t.event_id
                           AND p.eo_id   = t.eo_id
                           AND p.user_id = ?
    INNER JOIN events e  ON t.event_id  = e.id
    INNER JOIN eo    eo  ON t.eo_id     = eo.id
    LEFT  JOIN klien k   ON e.klien_id  = k.id
    LEFT  JOIN vendor v  ON t.vendor_id = v.id
    INNER JOIN users u   ON t.created_by = u.id
    LEFT  JOIN users approver ON t.approved_by = approver.id
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
$bisaEdit   = (int) $trx['created_by'] === $userId;

// ── Flash message ─────────────────────────────────────────────────────────────
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$labelStatus = [
    'draft'      => 'Draft',
    'aktif'      => 'Aktif',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

$pageTitle = 'Detail Transaksi';
$page_type = 'staff';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-staff.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <!-- Header -->
    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/staff/transaksi/index.php"
             class="breadcrumb__item">Transaksi</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">
            <?= htmlspecialchars($nomorStruk, ENT_QUOTES, 'UTF-8') ?>
          </span>
        </nav>
        <h1 class="page-title">Detail Transaksi</h1>
        <div class="page-header__meta"
             style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px">
          <span class="badge badge--<?= $trx['tipe'] ?>">
            <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
          </span>
          <?= badge_status_approval($trx['status_approval']) ?>
          <?php if ($trx['status_pembayaran'] === 'belum_lunas') : ?>
          <?= badge_status_pembayaran('belum_lunas', $trx['tipe']) ?>
          <?php endif; ?>
          <span style="font-size:13px;color:var(--text-secondary)">
            <?= htmlspecialchars($nomorStruk, ENT_QUOTES, 'UTF-8') ?>
          </span>
        </div>
      </div>
      <div class="page-header__right">
        <?php if ($bisaEdit) : ?>
        <a href="<?= BASE_URL ?>/staff/transaksi/edit.php?id=<?= $trxId ?>"
           class="btn btn--secondary btn--sm">Edit</a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/staff/transaksi/struk.php?id=<?= $trxId ?>"
           target="_blank" rel="noopener"
           class="btn btn--primary btn--sm">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
               aria-hidden="true">
            <path d="M6 9V2h12v7" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round"/>
            <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0
                     01-2 2h-2" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round"/>
            <rect x="6" y="14" width="12" height="8" rx="1"
                  stroke="currentColor" stroke-width="1.8"/>
          </svg>
          Cetak Struk
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

    <!-- Jumlah utama -->
    <div class="event-info-card" style="margin-bottom:16px">
      <div style="display:flex;align-items:center;justify-content:space-between;
                  flex-wrap:wrap;gap:12px">
        <div>
          <p style="font-size:12px;font-weight:600;text-transform:uppercase;
                    letter-spacing:.05em;color:var(--text-secondary);
                    margin-bottom:4px">Jumlah</p>
          <p style="font-size:28px;font-weight:700;
                    color:<?= $trx['tipe'] === 'pemasukan'
                                ? 'var(--success)' : 'var(--danger)' ?>">
            <?= $trx['tipe'] === 'pemasukan' ? '+' : '−' ?>
            <?= format_rupiah($trx['jumlah']) ?>
          </p>
        </div>
        <div style="text-align:right">
          <p style="font-size:12px;color:var(--text-secondary)">
            <?= htmlspecialchars($trx['nama_eo'], ENT_QUOTES, 'UTF-8') ?>
          </p>
          <p style="font-size:13px;font-weight:600;color:var(--text-primary)">
            <?= htmlspecialchars($nomorStruk, ENT_QUOTES, 'UTF-8') ?>
          </p>
        </div>
      </div>
    </div>

    <div class="event-detail-grid">
      <div class="event-detail-grid__main">

        <!-- Detail transaksi -->
        <div class="event-info-card">
          <h2 class="event-info-card__title">Detail Transaksi</h2>
          <div class="event-info-list">

            <div class="event-info-list__item">
              <span class="event-info-list__label">Nomor Struk</span>
              <span class="event-info-list__value">
                <?= htmlspecialchars($nomorStruk, ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>

            <div class="event-info-list__item">
              <span class="event-info-list__label">Tanggal</span>
              <span class="event-info-list__value">
                <?= date('d M Y', strtotime($trx['tanggal_transaksi'])) ?>
              </span>
            </div>

            <div class="event-info-list__item">
              <span class="event-info-list__label">Tipe</span>
              <span class="event-info-list__value">
                <span class="badge badge--<?= $trx['tipe'] ?>">
                  <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
                </span>
              </span>
            </div>

            <div class="event-info-list__item">
              <span class="event-info-list__label">Kategori</span>
              <span class="event-info-list__value">
                <?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>

            <?php if ($trx['nama_vendor']) : ?>
            <div class="event-info-list__item">
              <span class="event-info-list__label">Vendor</span>
              <span class="event-info-list__value">
                <?= htmlspecialchars($trx['nama_vendor'], ENT_QUOTES, 'UTF-8') ?>
                <?php if ($trx['layanan_vendor']) : ?>
                <span style="color:var(--text-secondary);font-size:12px">
                  · <?= htmlspecialchars($trx['layanan_vendor'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php endif; ?>
              </span>
            </div>
            <?php endif; ?>

            <?php if ($trx['deskripsi']) : ?>
            <div class="event-info-list__item">
              <span class="event-info-list__label">Deskripsi</span>
              <span class="event-info-list__value">
                <?= nl2br(htmlspecialchars($trx['deskripsi'], ENT_QUOTES, 'UTF-8')) ?>
              </span>
            </div>
            <?php endif; ?>

            <div class="event-info-list__item">
              <span class="event-info-list__label">Status Approval</span>
              <span class="event-info-list__value">
                <?= badge_status_approval($trx['status_approval']) ?>
                <?php if ($trx['status_approval'] !== 'pending' && $trx['nama_approver']) : ?>
                <span style="display:block;font-size:12px;color:var(--text-secondary);margin-top:4px">
                  oleh <?= htmlspecialchars($trx['nama_approver'], ENT_QUOTES, 'UTF-8') ?>
                  · <?= date('d M Y, H:i', strtotime($trx['approved_at'])) ?> WIB
                </span>
                <?php endif; ?>
              </span>
            </div>

            <div class="event-info-list__item">
              <span class="event-info-list__label">Status Pembayaran</span>
              <span class="event-info-list__value">
                <?= badge_status_pembayaran($trx['status_pembayaran'], $trx['tipe']) ?>
              </span>
            </div>

            <?php if ($trx['status_approval'] === 'rejected' && $trx['catatan_approval']) : ?>
            <div class="event-info-list__item">
              <span class="event-info-list__label">Alasan Penolakan</span>
              <span class="event-info-list__value" style="color:var(--danger)">
                <?= htmlspecialchars($trx['catatan_approval'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <?php endif; ?>

            <div class="event-info-list__item">
              <span class="event-info-list__label">Dicatat oleh</span>
              <span class="event-info-list__value">
                <?= htmlspecialchars($trx['nama_creator'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>

            <div class="event-info-list__item">
              <span class="event-info-list__label">Waktu catat</span>
              <span class="event-info-list__value">
                <?= date('d M Y, H:i', strtotime($trx['created_at'])) ?> WIB
              </span>
            </div>

          </div>
        </div>

        <!-- Bukti -->
        <?php if ($trx['bukti_file']) : ?>
        <div class="event-info-card" style="margin-top:16px">
          <h2 class="event-info-card__title">Bukti Pembayaran</h2>
          <div style="margin-top:12px">
            <a href="<?= BASE_URL . '/serve-bukti.php?id=' . $trxId ?>"
               target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:6px;
                      font-size:13px;color:var(--primary);font-weight:500;
                      text-decoration:none">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                      stroke="currentColor" stroke-width="1.8"/>
                <polyline points="14 2 14 8 20 8"
                          stroke="currentColor" stroke-width="1.8"/>
              </svg>
              <?= htmlspecialchars($trx['bukti_file'], ENT_QUOTES, 'UTF-8') ?>
            </a>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /main -->

      <!-- Sidebar: info event -->
      <aside class="event-detail-grid__aside">
        <div class="event-info-card">
          <h2 class="event-info-card__title">Event Terkait</h2>
          <div class="event-info-list">

            <div class="event-info-list__item">
              <span class="event-info-list__label">Nama Event</span>
              <span class="event-info-list__value">
                <a href="<?= BASE_URL ?>/staff/events/detail.php?id=<?= (int)$trx['event_id'] ?>"
                   style="color:var(--primary);text-decoration:none;font-weight:500">
                  <?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </span>
            </div>

            <div class="event-info-list__item">
              <span class="event-info-list__label">Status</span>
              <span class="event-info-list__value">
                <span class="badge badge--<?= $trx['status_event'] ?>">
                  <?= $labelStatus[$trx['status_event']] ?? $trx['status_event'] ?>
                </span>
              </span>
            </div>

            <div class="event-info-list__item">
              <span class="event-info-list__label">Tanggal</span>
              <span class="event-info-list__value">
                <?= date('d M Y', strtotime($trx['tanggal_mulai'])) ?>
                &mdash;
                <?= date('d M Y', strtotime($trx['tanggal_selesai'])) ?>
              </span>
            </div>

            <?php if ($trx['lokasi']) : ?>
            <div class="event-info-list__item">
              <span class="event-info-list__label">Lokasi</span>
              <span class="event-info-list__value">
                <?= htmlspecialchars($trx['lokasi'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <?php endif; ?>

            <?php if ($trx['nama_klien']) : ?>
            <div class="event-info-list__item">
              <span class="event-info-list__label">Klien</span>
              <span class="event-info-list__value">
                <?= htmlspecialchars($trx['nama_klien'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <?php endif; ?>

          </div>
        </div>

        <!-- Aksi cepat -->
        <div style="margin-top:12px;display:flex;flex-direction:column;gap:8px">
          <a href="<?= BASE_URL ?>/staff/transaksi/struk.php?id=<?= $trxId ?>"
             target="_blank" rel="noopener"
             class="btn btn--primary"
             style="width:100%;text-align:center;justify-content:center">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true">
              <path d="M6 9V2h12v7" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round"/>
              <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0
                       01-2 2h-2" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round"/>
              <rect x="6" y="14" width="12" height="8" rx="1"
                    stroke="currentColor" stroke-width="1.8"/>
            </svg>
            Cetak Struk
          </a>
          <?php if ($bisaEdit) : ?>
          <a href="<?= BASE_URL ?>/staff/transaksi/edit.php?id=<?= $trxId ?>"
             class="btn btn--secondary"
             style="width:100%;text-align:center;justify-content:center">
            Edit Transaksi
          </a>
          <?php endif; ?>
          <a href="<?= BASE_URL ?>/staff/transaksi/index.php"
             class="btn btn--text"
             style="width:100%;text-align:center;justify-content:center">
            ← Kembali ke Daftar
          </a>
        </div>

      </aside>
    </div><!-- /event-detail-grid -->

  </div>
</div>

<?php
$page_type = 'staff';
require_once __DIR__ . '/../../includes/footer.php';
?>