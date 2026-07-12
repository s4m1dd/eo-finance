<?php
/**
 * EO Finance — Manajemen penugasan staff ke event.
 *
 * Tampilan terpusat untuk melihat dan mengelola semua penugasan
 * dalam satu halaman — alternatif dari kelola penugasan yang tersebar
 * di admin/staff/edit.php dan admin/events/detail.php.
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

// ── Filter ────────────────────────────────────────────────────────────────────
$filterEventId = (int) ($_GET['event_id'] ?? 0);
$filterUserId  = (int) ($_GET['user_id']  ?? 0);
$page          = max(1, (int) ($_GET['page'] ?? 1));
$perPage       = ITEMS_PER_PAGE;
$offset        = ($page - 1) * $perPage;

// ── WHERE clause ──────────────────────────────────────────────────────────────
$where  = 'WHERE p.eo_id = ?';
$params = [$eoId];
$types  = 'i';

if ($filterEventId > 0) {
    $where   .= ' AND p.event_id = ?';
    $params[] = $filterEventId;
    $types   .= 'i';
}
if ($filterUserId > 0) {
    $where   .= ' AND p.user_id = ?';
    $params[] = $filterUserId;
    $types   .= 'i';
}

// ── Total ─────────────────────────────────────────────────────────────────────
$stmtCount = $mysqli->prepare(
    "SELECT COUNT(*) AS total FROM penugasan p $where"
);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalData = (int) $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

// ── Data penugasan ────────────────────────────────────────────────────────────
$stmtPenugasan = $mysqli->prepare(
    "SELECT p.id, p.role_dalam_event, p.created_at,
            e.id AS event_id, e.nama_event, e.status AS event_status,
            e.tanggal_mulai, e.tanggal_selesai,
            u.id AS user_id, u.nama AS nama_staff, u.email AS email_staff
     FROM penugasan p
     INNER JOIN events e ON p.event_id = e.id
     INNER JOIN users  u ON p.user_id  = u.id
     $where
     ORDER BY e.tanggal_mulai DESC, u.nama ASC
     LIMIT ? OFFSET ?"
);
$paramsFull = array_merge($params, [$perPage, $offset]);
$stmtPenugasan->bind_param($types . 'ii', ...$paramsFull);
$stmtPenugasan->execute();
$daftarPenugasan = $stmtPenugasan->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPenugasan->close();

// ── Dropdown event untuk filter ───────────────────────────────────────────────
$stmtEvDrop = $mysqli->prepare(
    "SELECT id, nama_event FROM events
     WHERE eo_id = ? ORDER BY tanggal_mulai DESC"
);
$stmtEvDrop->bind_param('i', $eoId);
$stmtEvDrop->execute();
$daftarEvents = $stmtEvDrop->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEvDrop->close();

// ── Dropdown staff untuk filter ───────────────────────────────────────────────
$stmtStaffDrop = $mysqli->prepare(
    "SELECT id, nama FROM users
     WHERE eo_id = ? AND role = 'staff_eo' AND is_active = 1
     ORDER BY nama ASC"
);
$stmtStaffDrop->bind_param('i', $eoId);
$stmtStaffDrop->execute();
$daftarStaff = $stmtStaffDrop->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtStaffDrop->close();

// ── Dropdown event aktif untuk form tambah penugasan ─────────────────────────
$stmtEvAktif = $mysqli->prepare(
    "SELECT id, nama_event FROM events
     WHERE eo_id = ? AND status IN ('draft','aktif')
     ORDER BY tanggal_mulai ASC"
);
$stmtEvAktif->bind_param('i', $eoId);
$stmtEvAktif->execute();
$eventsAktif = $stmtEvAktif->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEvAktif->close();

$baseUrl = BASE_URL . '/admin/penugasan/index.php?'
         . ($filterEventId ? 'event_id=' . $filterEventId . '&' : '')
         . ($filterUserId  ? 'user_id='  . $filterUserId  . '&' : '');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$labelStatus = [
    'draft'=>'Draft','aktif'=>'Aktif',
    'selesai'=>'Selesai','dibatalkan'=>'Dibatalkan',
];

$pageTitle = 'Penugasan Staff';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Penugasan Staff</h1>
        <p class="page-subtitle"><?= $totalData ?> penugasan aktif</p>
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

    <!-- Layout dua kolom: tabel kiri, form tambah kanan -->
    <div class="form-layout">

      <!-- Kolom utama: filter + tabel -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Filter -->
        <form method="GET" action="" class="table-filter-bar">
          <select name="event_id" class="filter-select" aria-label="Filter event">
            <option value="0">Semua Event</option>
            <?php foreach ($daftarEvents as $ev) : ?>
            <option value="<?= (int)$ev['id'] ?>"
                    <?= $filterEventId === (int)$ev['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
            </option>
            <?php endforeach; ?>
          </select>

          <select name="user_id" class="filter-select" aria-label="Filter staff">
            <option value="0">Semua Staff</option>
            <?php foreach ($daftarStaff as $sf) : ?>
            <option value="<?= (int)$sf['id'] ?>"
                    <?= $filterUserId === (int)$sf['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($sf['nama'], ENT_QUOTES, 'UTF-8') ?>
            </option>
            <?php endforeach; ?>
          </select>

          <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
          <?php if ($filterEventId || $filterUserId) : ?>
          <a href="<?= BASE_URL ?>/admin/penugasan/index.php"
             class="btn btn--text btn--sm">Reset</a>
          <?php endif; ?>
          <span class="table-result-count"><?= $totalData ?> data</span>
        </form>

        <!-- Tabel penugasan -->
        <?php if (empty($daftarPenugasan)) : ?>
        <div class="empty-state">
          <div class="empty-state__icon" aria-hidden="true">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
              <circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.5"/>
              <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              <rect x="14" y="4" width="7" height="7" rx="2"
                    stroke="currentColor" stroke-width="1.5"/>
              <path d="M17.5 6v4M15.5 8h4"
                    stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
          </div>
          <p class="empty-state__text">
            <?= $filterEventId || $filterUserId
                ? 'Tidak ada penugasan yang cocok dengan filter.'
                : 'Belum ada penugasan staff ke event.' ?>
          </p>
        </div>

        <?php else : ?>
        <div class="table-wrap">
          <table class="data-table" aria-label="Daftar penugasan staff">
            <thead>
              <tr>
                <th scope="col">Staff</th>
                <th scope="col">Event</th>
                <th scope="col">Status Event</th>
                <th scope="col">Peran</th>
                <th scope="col">Tanggal Ditugaskan</th>
                <th scope="col"><span class="sr-only">Aksi</span></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($daftarPenugasan as $pn) : ?>
              <tr>
                <!-- Staff -->
                <td class="data-table__cell data-table__cell--primary">
                  <div style="display:flex;align-items:center;gap:10px">
                    <span class="sidebar__avatar"
                          style="width:30px;height:30px;font-size:12px;flex-shrink:0"
                          aria-hidden="true">
                      <?= mb_strtoupper(
                            mb_substr($pn['nama_staff'], 0, 1, 'UTF-8'),
                            'UTF-8'
                          ) ?>
                    </span>
                    <div style="min-width:0">
                      <p style="font-weight:500;color:var(--text-primary)">
                        <?= htmlspecialchars($pn['nama_staff'], ENT_QUOTES, 'UTF-8') ?>
                      </p>
                      <p style="font-size:12px;color:var(--text-secondary)">
                        <?= htmlspecialchars($pn['email_staff'], ENT_QUOTES, 'UTF-8') ?>
                      </p>
                    </div>
                  </div>
                </td>

                <!-- Event -->
                <td class="data-table__cell">
                  <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$pn['event_id'] ?>"
                     class="table-link">
                    <?= htmlspecialchars($pn['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                  </a>
                  <p style="font-size:12px;color:var(--text-secondary);margin-top:2px">
                    <?= date('d M Y', strtotime($pn['tanggal_mulai'])) ?>
                    &ndash;
                    <?= date('d M Y', strtotime($pn['tanggal_selesai'])) ?>
                  </p>
                </td>

                <!-- Status event -->
                <td class="data-table__cell">
                  <span class="badge badge--<?= $pn['event_status'] ?>">
                    <?= $labelStatus[$pn['event_status']] ?? $pn['event_status'] ?>
                  </span>
                </td>

                <!-- Peran dalam event -->
                <td class="data-table__cell">
                  <?php if ($pn['role_dalam_event']) : ?>
                  <span class="tag">
                    <?= htmlspecialchars($pn['role_dalam_event'], ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <?php else : ?>
                  <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>

                <!-- Tanggal ditugaskan -->
                <td class="data-table__cell text-muted">
                  <?= time_ago($pn['created_at']) ?>
                </td>

                <!-- Aksi: lepas penugasan -->
                <td class="data-table__cell data-table__cell--actions">
                  <form method="POST"
                        action="<?= BASE_URL ?>/proses/admin/proses-penugasan.php"
                        onsubmit="return confirm('Lepas penugasan <?= htmlspecialchars(
                            addslashes($pn['nama_staff']), ENT_QUOTES, 'UTF-8') ?> dari event ini?')">
                    <input type="hidden" name="csrf_token"
                           value="<?= htmlspecialchars(
                                       $_SESSION['csrf_token'],
                                       ENT_QUOTES, 'UTF-8'
                                   ) ?>">
                    <input type="hidden" name="action"   value="lepas">
                    <input type="hidden" name="event_id" value="<?= (int)$pn['event_id'] ?>">
                    <input type="hidden" name="user_id"  value="<?= (int)$pn['user_id'] ?>">
                    <button type="submit" class="btn-icon"
                            title="Lepas penugasan"
                            style="color:var(--danger)">
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                        <path d="M18 6L6 18M6 6l12 12"
                              stroke="currentColor" stroke-width="2"
                              stroke-linecap="round"/>
                      </svg>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?= pagination($totalData, $baseUrl, $page, $perPage) ?>
        <?php endif; ?>

      </div><!-- /kolom utama -->

      <!-- Kolom kanan: form tambah penugasan baru -->
      <div class="form-sidebar-card" style="position:sticky;top:24px">
        <p class="form-sidebar-card__title">Tugaskan Staff ke Event</p>

        <?php if (empty($eventsAktif)) : ?>
        <div class="form-sidebar-tip">
          Tidak ada event aktif/draft saat ini. Buat event baru terlebih dahulu.
        </div>
        <?php elseif (empty($daftarStaff)) : ?>
        <div class="form-sidebar-tip">
          Belum ada staff aktif. Tambahkan staff terlebih dahulu di menu
          <a href="<?= BASE_URL ?>/admin/staff/tambah.php">Staff</a>.
        </div>
        <?php else : ?>
        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-penugasan.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="tambah">

          <!-- Event -->
          <div class="form-group">
            <label class="form-label" for="event_id_form">
              Event <span class="form-label__required">*</span>
            </label>
            <select id="event_id_form" name="event_id"
                    class="form-input filter-select" required>
              <option value="">— Pilih event —</option>
              <?php foreach ($eventsAktif as $ev) : ?>
              <option value="<?= (int)$ev['id'] ?>"
                      <?= $filterEventId === (int)$ev['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Staff -->
          <div class="form-group">
            <label class="form-label" for="user_id_form">
              Staff <span class="form-label__required">*</span>
            </label>
            <select id="user_id_form" name="user_id"
                    class="form-input filter-select" required>
              <option value="">— Pilih staff —</option>
              <?php foreach ($daftarStaff as $sf) : ?>
              <option value="<?= (int)$sf['id'] ?>"
                      <?= $filterUserId === (int)$sf['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($sf['nama'], ENT_QUOTES, 'UTF-8') ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Peran -->
          <div class="form-group">
            <label class="form-label" for="role_dalam_event_form">
              Peran dalam Event
            </label>
            <input type="text" id="role_dalam_event_form"
                   name="role_dalam_event"
                   class="form-input"
                   placeholder="Koordinator, Kasir, Logistik, ...">
            <span class="form-hint">Opsional — untuk memperjelas tanggung jawab.</span>
          </div>

          <button type="submit" class="btn btn--primary btn--full">
            Tugaskan Staff
          </button>
        </form>
        <?php endif; ?>

        <div class="form-sidebar-tip" style="margin-top:12px">
          Staff yang sudah ditugaskan ke suatu event tidak akan bisa
          ditugaskan ulang ke event yang sama sampai penugasan sebelumnya dilepas.
        </div>
      </div><!-- /kolom kanan -->

    </div><!-- /form-layout -->

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>