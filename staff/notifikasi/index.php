<?php
/**
 * EO Finance — Notifikasi In-App (Staff).
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

$eoId   = get_current_eo_id();
$userId = get_current_user_id();

// Tandai semua sebagai dibaca
$stmtUpd = $mysqli->prepare(
    'UPDATE notifikasi SET is_read = 1 WHERE user_id = ? AND is_read = 0'
);
$stmtUpd->bind_param('i', $userId);
$stmtUpd->execute();
$stmtUpd->close();

// Ambil notifikasi
$stmtN = $mysqli->prepare(
    "SELECT id, tipe, judul, pesan, link, is_read, created_at
     FROM notifikasi
     WHERE user_id = ? AND eo_id = ?
     ORDER BY created_at DESC
     LIMIT 50"
);
$stmtN->bind_param('ii', $userId, $eoId);
$stmtN->execute();
$notifs = $stmtN->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtN->close();

$ikonTipe = [
    'transaksi_approved' => ['icon' => '✅', 'class' => 'notif--success'],
    'transaksi_rejected' => ['icon' => '❌', 'class' => 'notif--danger'],
    'event_dekat'        => ['icon' => '📅', 'class' => 'notif--info'],
    'staff_baru'         => ['icon' => '👤', 'class' => 'notif--info'],
];

$pageTitle = 'Notifikasi';
$page_type = 'staff';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-staff.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Notifikasi</h1>
        <p class="page-subtitle"><?= count($notifs) ?> notifikasi terbaru</p>
      </div>
    </div>

    <?php if (empty($notifs)) : ?>
    <div class="empty-state">
      <div class="empty-state__icon" aria-hidden="true">🔔</div>
      <p class="empty-state__text">Belum ada notifikasi.</p>
    </div>
    <?php else : ?>
    <div class="notif-list">
      <?php foreach ($notifs as $n) :
        $meta = $ikonTipe[$n['tipe']] ?? ['icon' => '🔔', 'class' => ''];
        $url  = $n['link'] ? BASE_URL . $n['link'] : '#';
      ?>
      <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
         class="notif-item <?= $meta['class'] ?>">
        <span class="notif-item__icon"><?= $meta['icon'] ?></span>
        <div class="notif-item__body">
          <p class="notif-item__judul">
            <?= htmlspecialchars($n['judul'], ENT_QUOTES, 'UTF-8') ?>
          </p>
          <p class="notif-item__pesan">
            <?= htmlspecialchars($n['pesan'], ENT_QUOTES, 'UTF-8') ?>
          </p>
        </div>
        <span class="notif-item__time">
          <?= time_ago($n['created_at']) ?>
        </span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>