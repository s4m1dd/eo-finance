<?php
/**
 * EO Finance — Daftar staff admin.
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
$filterCari   = trim($_GET['cari']   ?? '');
$filterStatus = $_GET['status'] ?? '';
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = ITEMS_PER_PAGE;
$offset       = ($page - 1) * $perPage;

if (!in_array($filterStatus, ['aktif', 'nonaktif'], true)) {
    $filterStatus = '';
}

$where  = "WHERE u.eo_id = ? AND u.role = 'staff_eo'";
$params = [$eoId];
$types  = 'i';

if ($filterStatus === 'aktif') {
    $where .= ' AND u.is_active = 1';
} elseif ($filterStatus === 'nonaktif') {
    $where .= ' AND u.is_active = 0';
}

if ($filterCari !== '') {
    $where   .= ' AND (u.nama LIKE ? OR u.email LIKE ?)';
    $like     = '%' . $filterCari . '%';
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}

// ── Total ─────────────────────────────────────────────────────────────────────
$stmtCount = $mysqli->prepare(
    "SELECT COUNT(*) AS total FROM users u $where"
);
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalData = (int) $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();

// ── Data staff + jumlah event yang ditugaskan ─────────────────────────────────
$stmtStaff = $mysqli->prepare(
    "SELECT u.id, u.nama, u.email, u.is_active, u.first_login,
            COUNT(p.id) AS jumlah_penugasan
     FROM users u
     LEFT JOIN penugasan p ON p.user_id = u.id AND p.eo_id = u.eo_id
     $where
     GROUP BY u.id
     ORDER BY u.nama ASC
     LIMIT ? OFFSET ?"
);
$paramsFull = array_merge($params, [$perPage, $offset]);
$stmtStaff->bind_param($types . 'ii', ...$paramsFull);
$stmtStaff->execute();
$daftarStaff = $stmtStaff->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtStaff->close();

$baseUrl = BASE_URL . '/admin/staff/index.php?'
         . ($filterStatus ? 'status=' . urlencode($filterStatus) . '&' : '')
         . ($filterCari   ? 'cari='   . urlencode($filterCari)   . '&' : '');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Manajemen Staff';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Manajemen Staff</h1>
        <p class="page-subtitle"><?= $totalData ?> staff terdaftar</p>
      </div>
      <div class="page-header__right">
        <a href="<?= BASE_URL ?>/admin/staff/tambah.php" class="btn btn--primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round"/>
          </svg>
          Tambah Staff
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

    <!-- Filter -->
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
               placeholder="Cari nama atau email staff..."
               value="<?= htmlspecialchars($filterCari, ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <select name="status" class="filter-select" aria-label="Filter status">
        <option value="">Semua Status</option>
        <option value="aktif"    <?= $filterStatus === 'aktif'    ? 'selected' : '' ?>>Aktif</option>
        <option value="nonaktif" <?= $filterStatus === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
      </select>
      <button type="submit" class="btn btn--secondary btn--sm">Filter</button>
      <?php if ($filterCari || $filterStatus) : ?>
      <a href="<?= BASE_URL ?>/admin/staff/index.php"
         class="btn btn--text btn--sm">Reset</a>
      <?php endif; ?>
      <span class="table-result-count"><?= $totalData ?> data</span>
    </form>

    <?php if (empty($daftarStaff)) : ?>
    <div class="empty-state">
      <div class="empty-state__icon" aria-hidden="true">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
          <circle cx="9" cy="7" r="3" stroke="currentColor" stroke-width="1.5"/>
          <circle cx="17" cy="9" r="2.5" stroke="currentColor" stroke-width="1.5"/>
          <path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
          <path d="M17 14c2 0 4 1 4 3"
                stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
      </div>
      <p class="empty-state__text">
        <?= $filterCari || $filterStatus
            ? 'Tidak ada staff yang cocok dengan filter.'
            : 'Belum ada staff.' ?>
      </p>
      <?php if (!$filterCari && !$filterStatus) : ?>
      <a href="<?= BASE_URL ?>/admin/staff/tambah.php" class="btn btn--primary">
        Tambah Staff Pertama
      </a>
      <?php endif; ?>
    </div>

    <?php else : ?>
    <div class="table-wrap">
      <table class="data-table" aria-label="Daftar staff">
        <thead>
          <tr>
            <th scope="col">Nama</th>
            <th scope="col">Email</th>
            <th scope="col">Status</th>
            <th scope="col">Penugasan</th>
            <th scope="col">Login Pertama</th>
            <th scope="col"><span class="sr-only">Aksi</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarStaff as $sf) : ?>
          <tr>
            <!-- Avatar + Nama -->
            <td class="data-table__cell data-table__cell--primary">
              <div style="display:flex;align-items:center;gap:10px">
                <span class="sidebar__avatar" style="width:32px;height:32px;font-size:13px"
                      aria-hidden="true">
                  <?= mb_strtoupper(mb_substr($sf['nama'], 0, 1, 'UTF-8'), 'UTF-8') ?>
                </span>
                <?= htmlspecialchars($sf['nama'], ENT_QUOTES, 'UTF-8') ?>
              </div>
            </td>

            <!-- Email -->
            <td class="data-table__cell text-muted">
              <?= htmlspecialchars($sf['email'], ENT_QUOTES, 'UTF-8') ?>
            </td>

            <!-- Status aktif -->
            <td class="data-table__cell">
              <?php if ((int)$sf['is_active'] === 1) : ?>
              <span class="badge badge--aktif-user">Aktif</span>
              <?php else : ?>
              <span class="badge badge--nonaktif">Nonaktif</span>
              <?php endif; ?>
            </td>

            <!-- Jumlah event ditugaskan -->
            <td class="data-table__cell">
              <?php if ((int)$sf['jumlah_penugasan'] > 0) : ?>
              <span class="tag"><?= (int)$sf['jumlah_penugasan'] ?> event</span>
              <?php else : ?>
              <span class="text-muted">—</span>
              <?php endif; ?>
            </td>

            <!-- Flag first_login -->
            <td class="data-table__cell">
              <?php if ((int)$sf['first_login'] === 1) : ?>
              <span class="badge badge--first-login">Belum login</span>
              <?php else : ?>
              <span class="text-muted">Sudah</span>
              <?php endif; ?>
            </td>

            <!-- Aksi -->
            <td class="data-table__cell data-table__cell--actions">
              <a href="<?= BASE_URL ?>/admin/staff/edit.php?id=<?= (int)$sf['id'] ?>"
                 class="btn-icon" title="Edit staff">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                  <path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                </svg>
              </a>

              <!-- Toggle aktif/nonaktif -->
              <form method="POST"
                    action="<?= BASE_URL ?>/proses/admin/proses-staff.php"
                    onsubmit="return confirm('<?= (int)$sf['is_active'] === 1
                        ? 'Nonaktifkan akun staff ini?'
                        : 'Aktifkan kembali akun staff ini?' ?>')">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="toggle_aktif">
                <input type="hidden" name="id" value="<?= (int)$sf['id'] ?>">
                <button type="submit" class="btn-icon"
                        title="<?= (int)$sf['is_active'] === 1 ? 'Nonaktifkan' : 'Aktifkan' ?>"
                        style="color:<?= (int)$sf['is_active'] === 1
                            ? 'var(--warning)' : 'var(--success)' ?>">
                  <?php if ((int)$sf['is_active'] === 1) : ?>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M8 12h8" stroke="currentColor" stroke-width="1.8"
                          stroke-linecap="round"/>
                  </svg>
                  <?php else : ?>
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
                    <path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="1.8"
                          stroke-linecap="round"/>
                  </svg>
                  <?php endif; ?>
                </button>
              </form>

              <!-- Reset password (set first_login = 1) -->
              <form method="POST"
                    action="<?= BASE_URL ?>/proses/admin/proses-staff.php"
                    onsubmit="return confirm('Reset password staff ini? Staff harus membuat password baru saat login berikutnya.')">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="id" value="<?= (int)$sf['id'] ?>">
                <button type="submit" class="btn-icon" title="Reset password"
                        style="color:var(--text-secondary)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M21 2v6h-6" stroke="currentColor" stroke-width="1.8"
                          stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M21 13a9 9 0 11-1.5-4.5L21 8"
                          stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
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

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>