<?php
/**
 * EO Finance — Approval transaksi (admin).
 *
 * Menampilkan daftar transaksi berstatus 'pending' (dicatat oleh staff)
 * yang menunggu persetujuan admin. Admin bisa menyetujui atau menolak
 * (dengan alasan) langsung dari halaman ini.
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

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// ── Data transaksi pending ────────────────────────────────────────────────────
$stmtTrx = $mysqli->prepare(
    "SELECT t.id, t.tipe, t.kategori, t.jumlah, t.deskripsi,
            t.tanggal_transaksi, t.bukti_file, t.created_at,
            e.id AS event_id, e.nama_event,
            u.nama AS nama_staff,
            v.nama AS nama_vendor
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     INNER JOIN users  u ON t.created_by = u.id
     LEFT  JOIN vendor v ON t.vendor_id  = v.id
     WHERE t.eo_id = ? AND t.status_approval = 'pending'
     ORDER BY t.created_at ASC"
);
$stmtTrx->bind_param('i', $eoId);
$stmtTrx->execute();
$daftarPending = $stmtTrx->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtTrx->close();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Approval Transaksi';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Approval Transaksi</h1>
        <p class="page-subtitle">
          <?= count($daftarPending) ?> transaksi menunggu persetujuan
        </p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/admin/transaksi/index.php" class="btn btn--secondary">
          Lihat Semua Transaksi
        </a>
      </div>
    </div>

    <?php foreach ($flashMessage as $type => $pesan) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
      <span class="alert__text"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endforeach; ?>

    <?php if (empty($daftarPending)) : ?>
    <div class="empty-state">
      <div class="empty-state__icon" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
          <path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
      <p class="empty-state__text">Tidak ada transaksi yang menunggu persetujuan saat ini.</p>
    </div>

    <?php else : ?>
    <div class="approval-list">
      <?php foreach ($daftarPending as $trx) :
        $modalId = 'reject-modal-' . (int) $trx['id'];
      ?>
      <div class="approval-card">
        <div class="approval-card__main">
          <div class="approval-card__top">
            <span class="badge badge--<?= $trx['tipe'] ?>">
              <?= $trx['tipe'] === 'pemasukan' ? 'Pemasukan' : 'Pengeluaran' ?>
            </span>
            <?= badge_status_approval('pending') ?>
            <span class="approval-card__time">
              diajukan <?= time_ago($trx['created_at']) ?>
            </span>
          </div>

          <p class="approval-card__amount
                     <?= $trx['tipe'] === 'pemasukan' ? 'text-green' : 'text-red' ?>">
            <?= $trx['tipe'] === 'pemasukan' ? '+' : '−' ?>
            <?= format_rupiah($trx['jumlah']) ?>
          </p>

          <dl class="approval-card__meta">
            <div>
              <dt>Kategori</dt>
              <dd><?= htmlspecialchars($trx['kategori'], ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <div>
              <dt>Event</dt>
              <dd>
                <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$trx['event_id'] ?>" class="table-link">
                  <?= htmlspecialchars($trx['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </dd>
            </div>
            <div>
              <dt>Tanggal Transaksi</dt>
              <dd><?= date('d M Y', strtotime($trx['tanggal_transaksi'])) ?></dd>
            </div>
            <div>
              <dt>Diajukan oleh</dt>
              <dd><?= htmlspecialchars($trx['nama_staff'], ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php if ($trx['nama_vendor']) : ?>
            <div>
              <dt>Vendor</dt>
              <dd><?= htmlspecialchars($trx['nama_vendor'], ENT_QUOTES, 'UTF-8') ?></dd>
            </div>
            <?php endif; ?>
            <?php if ($trx['bukti_file']) : ?>
            <div>
              <dt>Bukti</dt>
              <dd>
                <a href="<?= BASE_URL . '/serve-bukti.php?id=' . (int)$trx['id'] ?>"
                   target="_blank" rel="noopener" class="trx-bukti">Lihat file</a>
              </dd>
            </div>
            <?php endif; ?>
          </dl>

          <?php if (!empty($trx['deskripsi'])) : ?>
          <p class="approval-card__desc">
            <?= nl2br(htmlspecialchars($trx['deskripsi'], ENT_QUOTES, 'UTF-8')) ?>
          </p>
          <?php endif; ?>
        </div>

        <div class="approval-card__actions">
          <form method="POST" action="<?= BASE_URL ?>/proses/admin/proses-transaksi.php"
                onsubmit="return confirm('Setujui transaksi ini?')">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="id" value="<?= (int)$trx['id'] ?>">
            <input type="hidden" name="kembali_ke"
                   value="<?= BASE_URL ?>/admin/transaksi/approval.php">
            <button type="submit" class="btn btn--primary btn--sm">Setujui</button>
          </form>

          <button type="button" class="btn btn--outline-danger btn--sm"
                  onclick="document.getElementById('<?= $modalId ?>').classList.add('modal--open')">
            Tolak
          </button>
        </div>
      </div>

      <!-- Modal alasan penolakan -->
      <div id="<?= $modalId ?>" class="modal">
        <div class="modal__backdrop"
             onclick="document.getElementById('<?= $modalId ?>').classList.remove('modal--open')"></div>
        <div class="modal__content">
          <h3 class="modal__title">Tolak Transaksi</h3>
          <p class="modal__text">
            Berikan alasan penolakan agar staff tahu apa yang perlu diperbaiki.
          </p>
          <form method="POST" action="<?= BASE_URL ?>/proses/admin/proses-transaksi.php">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="id" value="<?= (int)$trx['id'] ?>">
            <input type="hidden" name="kembali_ke"
                   value="<?= BASE_URL ?>/admin/transaksi/approval.php">
            <textarea name="catatan_approval" class="form-input form-input--textarea" rows="3" required
                      placeholder="Contoh: nominal tidak sesuai bukti, kategori salah, dll."></textarea>
            <div class="modal__actions">
              <button type="button" class="btn btn--text"
                      onclick="document.getElementById('<?= $modalId ?>').classList.remove('modal--open')">
                Batal
              </button>
              <button type="submit" class="btn btn--danger">Tolak Transaksi</button>
            </div>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>