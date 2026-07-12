<?php
/**
 * EO Finance — Daftar klien admin.
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

// ── Filter & pagination ───────────────────────────────────────────────────────
$filterCari = trim($_GET['cari'] ?? '');
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = ITEMS_PER_PAGE;
$offset     = ($page - 1) * $perPage;

$where  = 'WHERE k.eo_id = ?';
$params = [$eoId];
$types  = 'i';

if ($filterCari !== '') {
    $where   .= ' AND (k.nama LIKE ? OR k.email LIKE ? OR k.telepon LIKE ?)';
    $like     = '%' . $filterCari . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

// ── Total ─────────────────────────────────────────────────────────────────────
$stmtCount = $mysqli->prepare("SELECT COUNT(*) AS total FROM klien k $where");
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalData = (int) $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

// ── Data klien ────────────────────────────────────────────────────────────────
$stmtKlien = $mysqli->prepare(
    "SELECT k.id, k.nama, k.email, k.telepon, k.alamat,
            COUNT(e.id) AS jumlah_event
     FROM klien k
     LEFT JOIN events e ON e.klien_id = k.id AND e.eo_id = k.eo_id
     $where
     GROUP BY k.id
     ORDER BY k.nama ASC
     LIMIT ? OFFSET ?"
);
$paramsFull = array_merge($params, [$perPage, $offset]);
$typesFull  = $types . 'ii';
$stmtKlien->bind_param($typesFull, ...$paramsFull);
$stmtKlien->execute();
$daftarKlien = $stmtKlien->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtKlien->close();

$baseUrl = BASE_URL . '/admin/klien/index.php?'
         . ($filterCari ? 'cari=' . urlencode($filterCari) . '&' : '');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Klien';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Klien</h1>
        <p class="page-subtitle"><?= $totalData ?> klien terdaftar</p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/admin/klien/tambah.php" class="btn btn--primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round"/>
          </svg>
          Tambah Klien
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

    <!-- Filter bar -->
    <form method="GET" action="" class="table-filter-bar">
      <div class="table-search">
        <span class="table-search__icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
            <circle cx="11" cy="11" r="8" stroke="currentColor" stroke-width="1.8"/>
            <path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="1.8"
                  stroke-linecap="round"/>
          </svg>
        </span>
        <input type="text" name="cari" class="table-search__input"
               placeholder="Cari nama, email, atau telepon..."
               value="<?= htmlspecialchars($filterCari, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <button type="submit" class="btn btn--secondary btn--sm">Cari</button>
      <?php if ($filterCari) : ?>
      <a href="<?= BASE_URL ?>/admin/klien/index.php"
         class="btn btn--text btn--sm">Reset</a>
      <?php endif; ?>
      <span class="table-result-count"><?= $totalData ?> data</span>
    </form>

    <?php if (empty($daftarKlien)) : ?>
    <div class="empty-state">
      <div class="empty-state__icon" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
          <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="1.5"/>
          <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
      <p class="empty-state__text">
        <?= $filterCari ? 'Tidak ada klien yang cocok.' : 'Belum ada klien.' ?>
      </p>
      <?php if (!$filterCari) : ?>
      <a href="<?= BASE_URL ?>/admin/klien/tambah.php" class="btn btn--primary">
        Tambah Klien Pertama
      </a>
      <?php endif; ?>
    </div>

    <?php else : ?>
    <div class="contacts-grid">
      <?php foreach ($daftarKlien as $kl) : ?>
      <div class="contact-card">
        <!-- Avatar inisial -->
        <div class="contact-card__avatar" aria-hidden="true">
          <?= mb_strtoupper(mb_substr($kl['nama'], 0, 1, 'UTF-8'), 'UTF-8') ?>
        </div>

        <div class="contact-card__body">
          <p class="contact-card__name">
            <?= htmlspecialchars($kl['nama'], ENT_QUOTES, 'UTF-8') ?>
          </p>

          <!-- Jumlah event yang pernah dikerjakan -->
          <?php if ((int)$kl['jumlah_event'] > 0) : ?>
          <span class="contact-card__tag">
            <?= (int)$kl['jumlah_event'] ?> event
          </span>
          <?php endif; ?>

          <div class="contact-card__detail">
            <?php if ($kl['email']) : ?>
            <div class="contact-card__detail-row">
              <span class="contact-card__detail-icon" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                        stroke="currentColor" stroke-width="1.8"/>
                  <polyline points="22 6 12 13 2 6"
                            stroke="currentColor" stroke-width="1.8"/>
                </svg>
              </span>
              <?= htmlspecialchars($kl['email'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <?php if ($kl['telepon']) : ?>
            <div class="contact-card__detail-row">
              <span class="contact-card__detail-icon" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M22 16.92v3a2 2 0 01-2.18 2A19.79 19.79 0 012.08 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"
                        stroke="currentColor" stroke-width="1.8"/>
                </svg>
              </span>
              <?= htmlspecialchars($kl['telepon'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

            <?php if ($kl['alamat']) : ?>
            <div class="contact-card__detail-row">
              <span class="contact-card__detail-icon" aria-hidden="true">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none">
                  <path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0118 0z"
                        stroke="currentColor" stroke-width="1.8"/>
                  <circle cx="12" cy="10" r="3"
                          stroke="currentColor" stroke-width="1.8"/>
                </svg>
              </span>
              <span style="font-size:12px">
                <?= htmlspecialchars($kl['alamat'], ENT_QUOTES, 'UTF-8') ?>
              </span>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Tombol aksi -->
        <div class="contact-card__actions">
          <a href="<?= BASE_URL ?>/admin/klien/edit.php?id=<?= (int)$kl['id'] ?>"
             class="btn-icon" title="Edit klien">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
              <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
          </a>
          <form method="POST"
                action="<?= BASE_URL ?>/proses/admin/proses-klien.php"
                onsubmit="return confirm('Hapus klien <?= htmlspecialchars(
                    addslashes($kl['nama']), ENT_QUOTES, 'UTF-8') ?>?\nEvent yang menggunakan klien ini tidak akan terhapus.')">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="id" value="<?= (int)$kl['id'] ?>">
            <button type="submit" class="btn-icon" title="Hapus klien"
                    style="color:var(--danger)">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                <polyline points="3 6 5 6 21 6"
                          stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"
                      stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M10 11v6M14 11v6M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"
                      stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?= pagination($totalData, $baseUrl, $page, $perPage) ?>
    <?php endif; ?>

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>