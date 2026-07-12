<?php
/**
 * EO Finance — Detail event untuk staff.
 *
 * Staff hanya bisa melihat event yang ada di tabel penugasan miliknya.
 * Akses ke event lain (lewat manipulasi ?id=) diblokir via cek penugasan.
 * Halaman ini read-only — tidak ada tombol edit event.
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

// Guard first_login
if ((int) ($_SESSION['first_login'] ?? 0) === 1) {
    $_SESSION['__flash']['warning'] = 'Silakan ganti password terlebih dahulu.';
    header('Location: ' . BASE_URL . '/staff/ganti-password.php');
    exit();
}

$userId  = get_current_user_id();
$eoId    = get_current_eo_id();
$eventId = (int) ($_GET['id'] ?? 0);

if ($eventId <= 0) {
    header('Location: ' . BASE_URL . '/staff/events/index.php');
    exit();
}

// ── Validasi akses: staff harus ada di tabel penugasan untuk event ini ────────
// Ini mencegah IDOR — staff tidak bisa mengakses event lain hanya dengan
// mengubah ?id= di URL, meskipun event tersebut milik EO yang sama.
$stmtCek = $mysqli->prepare(
    'SELECT role_dalam_event FROM penugasan
     WHERE event_id = ? AND user_id = ? AND eo_id = ?
     LIMIT 1'
);
$stmtCek->bind_param('iii', $eventId, $userId, $eoId);
$stmtCek->execute();
$penugasan = $stmtCek->get_result()->fetch_assoc();
$stmtCek->close();

if ($penugasan === null) {
    $_SESSION['__flash']['error'] = 'Kamu tidak memiliki akses ke event ini.';
    header('Location: ' . BASE_URL . '/staff/events/index.php');
    exit();
}

$roleDalamEvent = $penugasan['role_dalam_event'];

// ── Ambil data event lengkap ──────────────────────────────────────────────────
$stmtEv = $mysqli->prepare(
    "SELECT e.*, k.nama AS nama_klien, u.nama AS nama_creator
     FROM events e
     LEFT JOIN klien k ON e.klien_id = k.id
     LEFT JOIN users u ON e.created_by = u.id
     WHERE e.id = ? AND e.eo_id = ?
     LIMIT 1"
);
$stmtEv->bind_param('ii', $eventId, $eoId);
$stmtEv->execute();
$event = $stmtEv->get_result()->fetch_assoc();
$stmtEv->close();

if ($event === null) {
    $_SESSION['__flash']['error'] = 'Event tidak ditemukan.';
    header('Location: ' . BASE_URL . '/staff/events/index.php');
    exit();
}

// ── Hitung sisa anggaran ──────────────────────────────────────────────────────
$budget = hitung_sisa_anggaran($eventId, $mysqli, $eoId);

// ── Transaksi event — staff hanya lihat semua transaksi event ini ─────────────
// (bukan hanya yang dia input, agar bisa memahami kondisi keuangan event)
$stmtTrx = $mysqli->prepare(
    "SELECT t.id, t.tipe, t.kategori, t.jumlah,
            t.tanggal_transaksi, t.deskripsi, t.bukti_file,
            u.nama AS nama_user,
            v.nama AS nama_vendor
     FROM transaksi t
     INNER JOIN users  u ON t.created_by = u.id
     LEFT  JOIN vendor v ON t.vendor_id  = v.id
     WHERE t.event_id = ? AND t.eo_id = ?
     ORDER BY t.tanggal_transaksi DESC, t.created_at DESC"
);
$stmtTrx->bind_param('ii', $eventId, $eoId);
$stmtTrx->execute();
$transaksi = $stmtTrx->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTrx->close();

// ── Daftar staff lain yang ditugaskan di event yang sama ─────────────────────
$stmtRekan = $mysqli->prepare(
    "SELECT u.nama, p.role_dalam_event
     FROM penugasan p
     INNER JOIN users u ON p.user_id = u.id
     WHERE p.event_id = ? AND p.eo_id = ? AND p.user_id != ?
     ORDER BY u.nama ASC"
);
$stmtRekan->bind_param('iii', $eventId, $eoId, $userId);
$stmtRekan->execute();
$rekanStaff = $stmtRekan->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtRekan->close();

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

$isAktif = in_array($event['status'], ['draft', 'aktif'], true);

$labelStatus = [
    'draft'      => 'Draft',
    'aktif'      => 'Aktif',
    'selesai'    => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];

$pageTitle = $event['nama_event'];
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
          <a href="<?= BASE_URL ?>/staff/events/index.php"
             class="breadcrumb__item">Event Saya</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">
            <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </nav>
        <h1 class="page-title">
          <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <div style="display:flex;align-items:center;gap:10px;margin-top:6px;flex-wrap:wrap">
          <span class="badge badge--<?= $event['status'] ?>">
            <?= $labelStatus[$event['status']] ?>
          </span>
          <?php if ($roleDalamEvent) : ?>
          <span class="role-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true">
              <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.8"/>
              <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            Peranmu: <?= htmlspecialchars($roleDalamEvent, ENT_QUOTES, 'UTF-8') ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($isAktif) : ?>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/staff/transaksi/tambah.php?event_id=<?= $eventId ?>"
           class="btn btn--primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 5v14M5 12h14" stroke="currentColor"
                  stroke-width="2" stroke-linecap="round"/>
          </svg>
          Catat Transaksi
        </a>
      </div>
      <?php endif; ?>
    </div>

    <?php foreach ($flashMessage as $type => $pesan) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>"
         role="alert">
      <span class="alert__text">
        <?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?>
      </span>
    </div>
    <?php endforeach; ?>

    <!-- Banner read-only -->
    <div class="readonly-notice" role="note">
      <span class="readonly-notice__icon" aria-hidden="true">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
          <path d="M12 8v4M12 16h.01" stroke="currentColor"
                stroke-width="1.8" stroke-linecap="round"/>
        </svg>
      </span>
      Halaman ini hanya bisa dilihat. Hubungi Admin EO untuk mengubah data event.
    </div>

    <!-- Layout: konten utama + aside -->
    <div class="event-detail-grid">

      <!-- Kolom utama: info + transaksi -->
      <div class="event-detail-grid__main">

        <!-- Info event -->
        <div class="event-info-card">
          <h2 class="event-info-card__title">Informasi Event</h2>
          <div class="event-info-list">
            <div class="event-info-list__item">
              <span class="event-info-list__label">Tanggal</span>
              <span class="event-info-list__value">
                <?= date('d M Y', strtotime($event['tanggal_mulai'])) ?>
                &mdash;
                <?= date('d M Y', strtotime($event['tanggal_selesai'])) ?>
              </span>
            </div>
            <?php if ($event['lokasi']) : ?>
            <div class="event-info-list__item">
              <span class="event-info-list__label">Lokasi</span>
              <span class="event-info-list__value">
                <?= htmlspecialchars($event['lokasi'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <?php endif; ?>
            <?php if ($event['nama_klien']) : ?>
            <div class="event-info-list__item">
              <span class="event-info-list__label">Klien</span>
              <span class="event-info-list__value">
                <?= htmlspecialchars($event['nama_klien'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <?php endif; ?>
            <?php if ($event['deskripsi']) : ?>
            <div class="event-info-list__item">
              <span class="event-info-list__label">Deskripsi</span>
              <span class="event-info-list__value">
                <?= nl2br(htmlspecialchars($event['deskripsi'], ENT_QUOTES, 'UTF-8')) ?>
              </span>
            </div>
            <?php endif; ?>
            <div class="event-info-list__item">
              <span class="event-info-list__label">Dibuat oleh</span>
              <span class="event-info-list__value">
                <?= htmlspecialchars($event['nama_creator'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
          </div>
        </div>

        <!-- Tabel transaksi -->
        <section aria-labelledby="trx-title">
          <div class="section-header-bar">
            <h2 class="section-header-bar__title" id="trx-title">
              Transaksi (<?= count($transaksi) ?>)
            </h2>
            <?php if ($isAktif) : ?>
            <a href="<?= BASE_URL ?>/staff/transaksi/tambah.php?event_id=<?= $eventId ?>"
               class="section-header-bar__link">+ Catat</a>
            <?php endif; ?>
          </div>

          <?php if (empty($transaksi)) : ?>
          <div class="empty-state">
            <p class="empty-state__text">Belum ada transaksi untuk event ini.</p>
            <?php if ($isAktif) : ?>
            <a href="<?= BASE_URL ?>/staff/transaksi/tambah.php?event_id=<?= $eventId ?>"
               class="btn btn--primary">Catat Transaksi Pertama</a>
            <?php endif; ?>
          </div>
          <?php else : ?>
          <div class="table-wrap">
            <table class="data-table" aria-label="Daftar transaksi event">
              <thead>
                <tr>
                  <th scope="col">Tanggal</th>
                  <th scope="col">Tipe</th>
                  <th scope="col">Kategori</th>
                  <th scope="col">Vendor</th>
                  <th scope="col">Jumlah</th>
                  <th scope="col">Dicatat oleh</th>
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
            </table>
          </div>
          <?php endif; ?>
        </section>

      </div><!-- /main -->

      <!-- Aside: anggaran + rekan staff -->
      <div class="event-detail-grid__aside">

        <!-- Ringkasan anggaran -->
        <div class="event-info-card">
          <h2 class="event-info-card__title">Anggaran</h2>
          <div class="budget-info-bar">
            <div class="budget-info-bar__row">
              <span class="budget-info-bar__label">Total Anggaran</span>
              <span class="budget-info-bar__value">
                <?= $budget['formatted']['anggaran'] ?>
              </span>
            </div>
            <div class="budget-info-bar__row">
              <span class="budget-info-bar__label">Pengeluaran</span>
              <span class="budget-info-bar__value budget-info-bar__value--red">
                <?= $budget['formatted']['total_pengeluaran'] ?>
              </span>
            </div>
            <div class="budget-info-bar__row">
              <span class="budget-info-bar__label">Sisa</span>
              <span class="budget-info-bar__value
                           budget-info-bar__value--<?= $budget['sisa'] >= 0 ? 'green' : 'red' ?>">
                <?= $budget['formatted']['sisa'] ?>
              </span>
            </div>
            <!-- Progress bar serapan anggaran -->
            <div style="margin-top:8px">
              <div style="display:flex;justify-content:space-between;
                          font-size:12px;color:var(--text-secondary);margin-bottom:4px">
                <span>Terpakai</span>
                <strong><?= number_format($budget['persentase_terpakai'], 1) ?>%</strong>
              </div>
              <div class="progress-bar progress-bar--lg">
                <div class="progress-bar__fill
                            progress-bar__fill--<?= $budget['status_warna'] ?>"
                     style="width:<?= min($budget['persentase_terpakai'], 100) ?>%">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Rekan staff di event ini -->
        <div class="event-info-card">
          <h2 class="event-info-card__title">
            Rekan Tim
            <?php if (!empty($rekanStaff)) : ?>
            <span style="font-weight:400;font-size:13px;color:var(--text-secondary)">
              (<?= count($rekanStaff) ?> orang)
            </span>
            <?php endif; ?>
          </h2>
          <?php if (empty($rekanStaff)) : ?>
          <p class="text-muted" style="font-size:14px">
            Kamu satu-satunya staff di event ini.
          </p>
          <?php else : ?>
          <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach ($rekanStaff as $rk) : ?>
            <div style="display:flex;align-items:center;gap:10px">
              <span class="sidebar__avatar"
                    style="width:32px;height:32px;font-size:13px;flex-shrink:0"
                    aria-hidden="true">
                <?= mb_strtoupper(mb_substr($rk['nama'], 0, 1, 'UTF-8'), 'UTF-8') ?>
              </span>
              <div style="min-width:0">
                <p style="font-size:14px;font-weight:500;color:var(--text-primary)">
                  <?= htmlspecialchars($rk['nama'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php if ($rk['role_dalam_event']) : ?>
                <p style="font-size:12px;color:var(--text-secondary)">
                  <?= htmlspecialchars($rk['role_dalam_event'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

      </div><!-- /aside -->

    </div><!-- /event-detail-grid -->

  </div>
</div>

<?php
$page_type = 'staff';
require_once __DIR__ . '/../../includes/footer.php';
?>