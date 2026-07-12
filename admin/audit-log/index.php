<?php
/**
 * EO Finance — Audit Log.
 *
 * Menampilkan riwayat semua aktivitas penting dalam sistem:
 * siapa melakukan apa, kapan, dan terhadap data mana.
 * Hanya bisa diakses admin_eo — staff tidak punya akses ke halaman ini.
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

$eoId  = get_current_eo_id();

// ── Filter ────────────────────────────────────────────────────────────────────
$filterUser    = (int)    ($_GET['user_id']  ?? 0);
$filterEntitas = trim(    $_GET['entitas']   ?? '');
$filterAksi    = trim(    $_GET['aksi']      ?? '');

// Validasi whitelist supaya tidak bisa inject nilai sembarangan
$entitasValid = ['transaksi','events','users','klien','vendor','penugasan'];
$aksiValid    = ['created','updated','deleted','approved','rejected','lunas'];
if (!in_array($filterEntitas, $entitasValid, true)) $filterEntitas = '';
if (!in_array($filterAksi,    $aksiValid,    true)) $filterAksi    = '';

// ── Paginasi ─────────────────────────────────────────────────────────────────
$perPage = 50;
$page    = max(1, (int) ($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

// ── Bangun klausa WHERE dinamis ───────────────────────────────────────────────
$where  = 'WHERE al.eo_id = ?';
$params = [$eoId];
$types  = 'i';

if ($filterUser > 0) {
    $where   .= ' AND al.user_id = ?';
    $params[] = $filterUser;
    $types   .= 'i';
}
if ($filterEntitas !== '') {
    $where   .= ' AND al.entitas = ?';
    $params[] = $filterEntitas;
    $types   .= 's';
}
if ($filterAksi !== '') {
    $where   .= ' AND al.aksi = ?';
    $params[] = $filterAksi;
    $types   .= 's';
}

// ── Total baris untuk paginasi ────────────────────────────────────────────────
$stmtCount = $mysqli->prepare("SELECT COUNT(*) AS total FROM audit_log al $where");
$stmtCount->bind_param($types, ...$params);
$stmtCount->execute();
$totalBaris = (int) $stmtCount->get_result()->fetch_assoc()['total'];
$stmtCount->close();
$totalPage = max(1, (int) ceil($totalBaris / $perPage));

// ── Data log ─────────────────────────────────────────────────────────────────
$stmtLog = $mysqli->prepare(
    "SELECT al.id, al.user_id, al.nama_user, al.aksi, al.entitas,
            al.entitas_id, al.deskripsi, al.ip_address, al.created_at
     FROM audit_log al
     $where
     ORDER BY al.created_at DESC
     LIMIT ? OFFSET ?"
);
$paramsLog   = array_merge($params, [$perPage, $offset]);
$typesLog    = $types . 'ii';
$stmtLog->bind_param($typesLog, ...$paramsLog);
$stmtLog->execute();
$daftarLog = $stmtLog->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtLog->close();

// ── Daftar staff untuk filter dropdown ───────────────────────────────────────
$stmtStaff = $mysqli->prepare(
    "SELECT DISTINCT al.user_id, al.nama_user
     FROM audit_log al
     WHERE al.eo_id = ? AND al.user_id IS NOT NULL
     ORDER BY al.nama_user ASC"
);
$stmtStaff->bind_param('i', $eoId);
$stmtStaff->execute();
$daftarStaff = $stmtStaff->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtStaff->close();

// ── Label aksi & ikon entitas ─────────────────────────────────────────────────
$labelAksi = [
    'created'  => ['label' => 'Dibuat',    'class' => 'audit-aksi--created'],
    'updated'  => ['label' => 'Diubah',    'class' => 'audit-aksi--updated'],
    'deleted'  => ['label' => 'Dihapus',   'class' => 'audit-aksi--deleted'],
    'approved' => ['label' => 'Disetujui', 'class' => 'audit-aksi--approved'],
    'rejected' => ['label' => 'Ditolak',   'class' => 'audit-aksi--rejected'],
    'lunas'    => ['label' => 'Lunas',     'class' => 'audit-aksi--lunas'],
];
$labelEntitas = [
    'transaksi'  => 'Transaksi',
    'events'     => 'Event',
    'users'      => 'Staff',
    'klien'      => 'Klien',
    'vendor'     => 'Vendor',
    'penugasan'  => 'Penugasan',
];

$pageTitle = 'Audit Log';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Audit Log</h1>
        <p class="page-subtitle">
          Riwayat aktivitas sistem —
          <?= number_format($totalBaris) ?> entri tercatat
        </p>
      </div>
    </div>

    <!-- Filter -->
    <div class="laporan-filter laporan-filter--3col" style="margin-bottom:var(--space-5)">
      <form method="GET" action="" style="display:contents">

        <div class="laporan-filter__group">
          <label class="laporan-filter__label" for="f_user">Pengguna</label>
          <select id="f_user" name="user_id" class="form-input filter-select">
            <option value="">— Semua pengguna —</option>
            <?php foreach ($daftarStaff as $st) : ?>
            <option value="<?= (int)$st['user_id'] ?>"
                    <?= $filterUser === (int)$st['user_id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($st['nama_user'], ENT_QUOTES, 'UTF-8') ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="laporan-filter__group">
          <label class="laporan-filter__label" for="f_entitas">Jenis Data</label>
          <select id="f_entitas" name="entitas" class="form-input filter-select">
            <option value="">— Semua —</option>
            <?php foreach ($labelEntitas as $val => $lbl) : ?>
            <option value="<?= $val ?>" <?= $filterEntitas === $val ? 'selected' : '' ?>>
              <?= $lbl ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="laporan-filter__group">
          <label class="laporan-filter__label" for="f_aksi">Aksi</label>
          <select id="f_aksi" name="aksi" class="form-input filter-select">
            <option value="">— Semua aksi —</option>
            <?php foreach ($labelAksi as $val => $meta) : ?>
            <option value="<?= $val ?>" <?= $filterAksi === $val ? 'selected' : '' ?>>
              <?= $meta['label'] ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="laporan-filter__group laporan-filter__group--action">
          <div class="laporan-filter__buttons">
            <button type="submit" class="btn btn--primary">Filter</button>
            <?php if ($filterUser || $filterEntitas || $filterAksi) : ?>
            <a href="<?= BASE_URL ?>/admin/audit-log/index.php" class="btn btn--outline">Reset</a>
            <?php endif; ?>
          </div>
        </div>

      </form>
    </div>

    <!-- Tabel log -->
    <?php if (empty($daftarLog)) : ?>
    <div class="empty-state">
      <p class="empty-state__text">Tidak ada aktivitas yang cocok dengan filter.</p>
    </div>
    <?php else : ?>
    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Waktu</th>
            <th scope="col">Pengguna</th>
            <th scope="col">Aksi</th>
            <th scope="col">Data</th>
            <th scope="col">Deskripsi</th>
            <th scope="col">IP</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($daftarLog as $log) :
            $aksiMeta = $labelAksi[$log['aksi']] ?? ['label' => $log['aksi'], 'class' => ''];
            $entitasLabel = $labelEntitas[$log['entitas']] ?? $log['entitas'];
          ?>
          <tr>
            <td class="data-table__cell" style="white-space:nowrap;font-size:13px">
              <?= date('d M Y', strtotime($log['created_at'])) ?><br>
              <span class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
            </td>
            <td class="data-table__cell data-table__cell--primary">
              <?= htmlspecialchars($log['nama_user'], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td class="data-table__cell">
              <span class="audit-aksi <?= $aksiMeta['class'] ?>">
                <?= $aksiMeta['label'] ?>
              </span>
            </td>
            <td class="data-table__cell" style="font-size:13px">
              <?= htmlspecialchars($entitasLabel, ENT_QUOTES, 'UTF-8') ?>
              <?php if ($log['entitas_id']) : ?>
              <span class="text-muted">#<?= (int)$log['entitas_id'] ?></span>
              <?php endif; ?>
            </td>
            <td class="data-table__cell" style="font-size:13px;max-width:300px">
              <?= htmlspecialchars($log['deskripsi'], ENT_QUOTES, 'UTF-8') ?>
            </td>
            <td class="data-table__cell text-muted" style="font-size:12px">
              <?= htmlspecialchars($log['ip_address'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginasi -->
    <?php if ($totalPage > 1) : ?>
    <div class="pagination" style="margin-top:var(--space-5)">
      <?php
      $qBase = http_build_query(array_filter([
          'user_id'  => $filterUser  ?: null,
          'entitas'  => $filterEntitas ?: null,
          'aksi'     => $filterAksi  ?: null,
      ]));
      ?>
      <?php if ($page > 1) : ?>
      <a href="?<?= $qBase ?>&page=<?= $page - 1 ?>" class="btn btn--outline btn--sm">← Sebelumnya</a>
      <?php endif; ?>
      <span class="pagination__info">Hal. <?= $page ?> / <?= $totalPage ?></span>
      <?php if ($page < $totalPage) : ?>
      <a href="?<?= $qBase ?>&page=<?= $page + 1 ?>" class="btn btn--outline btn--sm">Berikutnya →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>