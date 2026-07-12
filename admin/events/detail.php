<?php
/**
 * EO Finance — Detail event admin.
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
$eventId = (int) ($_GET['id'] ?? 0);

if ($eventId <= 0) {
    header('Location: ' . BASE_URL . '/admin/events/index.php');
    exit();
}

// ── Ambil data event ──────────────────────────────────────────────────────────
$stmtEv = $mysqli->prepare("
    SELECT e.*, k.nama AS nama_klien, u.nama AS nama_creator
    FROM events e
    LEFT JOIN klien k ON e.klien_id = k.id
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id = ? AND e.eo_id = ?
    LIMIT 1
");
$stmtEv->bind_param('ii', $eventId, $eoId);
$stmtEv->execute();
$event = $stmtEv->get_result()->fetch_assoc();
$stmtEv->close();

if ($event === null) {
    $_SESSION['__flash']['error'] = 'Event tidak ditemukan.';
    header('Location: ' . BASE_URL . '/admin/events/index.php');
    exit();
}

// ── Flash message ─────────────────────────────────────────────────────────────
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Hitung sisa anggaran ──────────────────────────────────────────────────────
$budget = hitung_sisa_anggaran($eventId, $mysqli, $eoId);

// ── Data untuk chartDetailEvent: proporsi Terpakai vs Sisa anggaran ───────────
// Sisa di-clip ke 0 untuk donut (kalau over-budget, "sisa" negatif tidak
// punya arti proporsi; status merah pada progress bar sudah cukup jelas
// menandakan kondisi tsb).
$detailEventLabels = ['Terpakai', 'Sisa'];
$detailEventValues = [$budget['total_pengeluaran'], max($budget['sisa'], 0)];

// ── Daftar transaksi event ────────────────────────────────────────────────────
$stmtTrx = $mysqli->prepare("
    SELECT t.id, t.tipe, t.kategori, t.jumlah,
           t.tanggal_transaksi, t.deskripsi, t.bukti_file,
           u.nama AS nama_user, v.nama AS nama_vendor
    FROM transaksi t
    INNER JOIN users u ON t.created_by = u.id
    LEFT  JOIN vendor v ON t.vendor_id = v.id
    WHERE t.event_id = ? AND t.eo_id = ?
    ORDER BY t.tanggal_transaksi DESC, t.created_at DESC
");
$stmtTrx->bind_param('ii', $eventId, $eoId);
$stmtTrx->execute();
$transaksi = $stmtTrx->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTrx->close();

// ── Daftar staff yang ditugaskan ──────────────────────────────────────────────
$stmtStaff = $mysqli->prepare("
    SELECT u.id, u.nama, u.email, p.role_dalam_event
    FROM penugasan p
    INNER JOIN users u ON p.user_id = u.id
    WHERE p.event_id = ? AND p.eo_id = ?
    ORDER BY u.nama ASC
");
$stmtStaff->bind_param('ii', $eventId, $eoId);
$stmtStaff->execute();
$staffDitugaskan = $stmtStaff->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtStaff->close();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$labelStatus = [
    'draft' => 'Draft', 'aktif' => 'Aktif',
    'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan',
];

$pageTitle = $event['nama_event'];
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <!-- Header -->
    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/admin/events/index.php"
             class="breadcrumb__item">Event</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">
            <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </nav>
        <h1 class="page-title">
          <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <div class="page-header__meta" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px">
          <span class="badge badge--<?= $event['status'] ?>">
            <?= $labelStatus[$event['status']] ?>
          </span>
          <?php if ($event['nama_klien']) : ?>
          <span style="font-size:13px;color:var(--text-secondary)">
            Klien: <?= htmlspecialchars($event['nama_klien'], ENT_QUOTES, 'UTF-8') ?>
          </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/admin/events/invoice.php?event_id=<?= $eventId ?>"
           target="_blank" rel="noopener"
           class="btn btn--outline">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"
                  stroke="currentColor" stroke-width="1.8"/>
            <polyline points="14 2 14 8 20 8" stroke="currentColor" stroke-width="1.8"/>
            <line x1="8" y1="13" x2="16" y2="13" stroke="currentColor" stroke-width="1.8"/>
            <line x1="8" y1="17" x2="12" y2="17" stroke="currentColor" stroke-width="1.8"/>
          </svg>
          Invoice
        </a>
        <a href="<?= BASE_URL ?>/admin/events/edit.php?id=<?= $eventId ?>"
           class="btn btn--secondary">Edit Event</a>
        <a href="<?= BASE_URL ?>/admin/transaksi/tambah.php?event_id=<?= $eventId ?>"
           class="btn btn--primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
               aria-hidden="true">
            <path d="M12 5v14M5 12h14" stroke="currentColor"
                  stroke-width="2" stroke-linecap="round"/>
          </svg>
          Tambah Transaksi
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

    <!-- Info event + anggaran -->
    <div class="event-detail-grid">
      <div class="event-detail-grid__main">

        <!-- Info umum -->
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
        <section aria-labelledby="section-trx">
          <div class="section-header-bar">
            <h2 class="section-header-bar__title" id="section-trx">
              Transaksi (<?= count($transaksi) ?>)
            </h2>
            <a href="<?= BASE_URL ?>/admin/transaksi/tambah.php?event_id=<?= $eventId ?>"
               class="section-header-bar__link">+ Tambah</a>
          </div>

          <?php if (empty($transaksi)) : ?>
          <div class="empty-state">
            <p class="empty-state__text">Belum ada transaksi untuk event ini.</p>
          </div>
          <?php else : ?>
          <div class="table-wrap">
            <table class="data-table" aria-label="Daftar transaksi event">
              <thead>
                <tr>
                  <th scope="col">Tanggal</th>
                  <th scope="col">Tipe</th>
                  <th scope="col">Kategori</th>
                  <th scope="col">Jumlah</th>
                  <th scope="col">Dicatat oleh</th>
                  <th scope="col">Vendor</th>
                  <th scope="col"><span class="sr-only">Aksi</span></th>
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
                  <td class="data-table__cell data-table__cell--amount
                             <?= $trx['tipe'] === 'pemasukan' ? 'text-green' : 'text-red' ?>">
                    <?= $trx['tipe'] === 'pemasukan' ? '+' : '−' ?>
                    <?= format_rupiah($trx['jumlah']) ?>
                  </td>
                  <td class="data-table__cell">
                    <?= htmlspecialchars($trx['nama_user'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="data-table__cell">
                    <?= $trx['nama_vendor']
                        ? htmlspecialchars($trx['nama_vendor'], ENT_QUOTES, 'UTF-8')
                        : '<span class="text-muted">—</span>' ?>
                  </td>
                  <td class="data-table__cell data-table__cell--actions">
                    <a href="<?= BASE_URL ?>/admin/transaksi/edit.php?id=<?= (int)$trx['id'] ?>"
                       class="btn-icon" title="Edit">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"
                              stroke="currentColor" stroke-width="1.8"
                              stroke-linecap="round"/>
                        <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"
                              stroke="currentColor" stroke-width="1.8"
                              stroke-linecap="round"/>
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

      </div><!-- /main -->

      <!-- Aside: anggaran + staff -->
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
            <div style="height:180px;margin-top:var(--space-4)">
              <canvas id="chartDetailEvent" role="img"
                      aria-label="Grafik proporsi anggaran terpakai vs sisa"></canvas>
            </div>
          </div>
        </div>

        <!-- Staff ditugaskan -->
        <div class="event-info-card">
          <h2 class="event-info-card__title">
            Staff (<?= count($staffDitugaskan) ?>)
          </h2>
          <?php if (empty($staffDitugaskan)) : ?>
          <p class="text-muted" style="font-size:14px">
            Belum ada staff ditugaskan.
          </p>
          <?php else : ?>
          <div class="penugasan-list">
            <?php foreach ($staffDitugaskan as $stf) : ?>
            <div class="penugasan-item">
              <div>
                <p class="penugasan-item__event">
                  <?= htmlspecialchars($stf['nama'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php if ($stf['role_dalam_event']) : ?>
                <p class="penugasan-item__role">
                  <?= htmlspecialchars($stf['role_dalam_event'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <?php endif; ?>
              </div>
              <div class="penugasan-item__actions">
                <form method="POST"
                      action="<?= BASE_URL ?>/proses/admin/proses-event.php"
                      onsubmit="return confirm('Lepas penugasan staff ini?')">
                  <input type="hidden" name="csrf_token"
                         value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="lepas_staff">
                  <input type="hidden" name="event_id" value="<?= $eventId ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$stf['id'] ?>">
                  <button type="submit" class="btn-icon" title="Lepas penugasan"
                          style="color:var(--danger)">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                      <path d="M18 6L6 18M6 6l12 12" stroke="currentColor"
                            stroke-width="2" stroke-linecap="round"/>
                    </svg>
                  </button>
                </form>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div style="margin-top:12px">
            <a href="<?= BASE_URL ?>/admin/staff/index.php"
               class="btn btn--secondary btn--sm btn--full">
              Kelola Penugasan Staff
            </a>
          </div>
        </div>

      </div><!-- /aside -->
    </div><!-- /event-detail-grid -->

  </div>
</div>

<?php
$page_type = 'admin';

// ── Embed data grafik ke variabel window sesuai admin.js ─────────────────────
// admin.js membaca: window.dataDetailEvent
?>
<script>
window.dataDetailEvent = <?= json_encode([
    'labels' => $detailEventLabels,
    'data'   => $detailEventValues,
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
require_once __DIR__ . '/../../includes/footer.php';
?>