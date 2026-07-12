<?php
/**
 * EO Finance — Halaman profil staff_eo.
 *
 * Staff bisa mengubah nama, email, dan password akun.
 * Data EO ditampilkan sebagai informasi read-only.
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role('staff_eo');

if ((int) ($_SESSION['first_login'] ?? 0) === 1) {
    $_SESSION['__flash']['warning'] = 'Silakan ganti password terlebih dahulu.';
    header('Location: ' . BASE_URL . '/staff/ganti-password.php');
    exit();
}

$userId = get_current_user_id();
$eoId   = get_current_eo_id();

// ── Ambil data user dari DB ───────────────────────────────────────────────────
$stmtUser = $mysqli->prepare(
    "SELECT id, nama, email, created_at
     FROM users
     WHERE id = ? AND eo_id = ? AND role = 'staff_eo'
     LIMIT 1"
);
$stmtUser->bind_param('ii', $userId, $eoId);
$stmtUser->execute();
$user = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

if ($user === null) {
    header('Location: ' . BASE_URL . '/logout.php');
    exit();
}

// ── Ambil nama EO untuk informasi ─────────────────────────────────────────────
$stmtEO = $mysqli->prepare('SELECT nama_eo FROM eo WHERE id = ? LIMIT 1');
$stmtEO->bind_param('i', $eoId);
$stmtEO->execute();
$namaEO = $stmtEO->get_result()->fetch_assoc()['nama_eo'] ?? '—';
$stmtEO->close();

// ── Statistik aktivitas staff ─────────────────────────────────────────────────
$stmtStat = $mysqli->prepare(
    "SELECT
        (SELECT COUNT(*) FROM penugasan
         WHERE user_id = ? AND eo_id = ?) AS total_penugasan,
        (SELECT COUNT(*) FROM transaksi
         WHERE created_by = ? AND eo_id = ?) AS total_transaksi,
        (SELECT COUNT(*) FROM transaksi
         WHERE created_by = ? AND eo_id = ?
           AND MONTH(created_at) = MONTH(NOW())
           AND YEAR(created_at)  = YEAR(NOW())) AS transaksi_bulan_ini"
);
$stmtStat->bind_param('iiiiii', $userId, $eoId, $userId, $eoId, $userId, $eoId);
$stmtStat->execute();
$stat = $stmtStat->get_result()->fetch_assoc();
$stmtStat->close();

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Profil';
$page_type = 'staff';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/navbar-staff.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Profil</h1>
        <p class="page-subtitle">Kelola informasi akun kamu</p>
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

    <div class="profil-layout">

      <!-- ── Kolom kiri: kartu identitas ─────────────────────── -->
      <div class="profil-card">

        <!-- Avatar inisial -->
        <div class="profil-card__avatar" aria-hidden="true">
          <?= mb_strtoupper(mb_substr($user['nama'], 0, 1, 'UTF-8'), 'UTF-8') ?>
        </div>

        <h2 class="profil-card__name">
          <?= htmlspecialchars($user['nama'], ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <span class="profil-card__role">Staff EO</span>

        <!-- Meta info -->
        <div class="profil-card__meta">
          <div class="profil-card__meta-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                    stroke="currentColor" stroke-width="1.8"/>
              <polyline points="22 6 12 13 2 6" stroke="currentColor" stroke-width="1.8"/>
            </svg>
            <?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="profil-card__meta-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                    stroke="currentColor" stroke-width="1.8"/>
              <rect x="9" y="14" width="6" height="8"
                    stroke="currentColor" stroke-width="1.8"/>
            </svg>
            <?= htmlspecialchars($namaEO, ENT_QUOTES, 'UTF-8') ?>
          </div>
          <div class="profil-card__meta-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true">
              <rect x="3" y="4" width="18" height="18" rx="2"
                    stroke="currentColor" stroke-width="1.8"/>
              <path d="M3 10h18M8 2v4M16 2v4"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            Bergabung <?= date('M Y', strtotime($user['created_at'])) ?>
          </div>
        </div>

        <!-- Statistik aktivitas -->
        <div style="width:100%;border-top:1px solid var(--border-light);
                    padding-top:var(--space-4);display:grid;
                    grid-template-columns:1fr 1fr 1fr;gap:var(--space-2)">
          <?php
          $statItems = [
              ['label' => 'Event',      'value' => $stat['total_penugasan']],
              ['label' => 'Transaksi',  'value' => $stat['total_transaksi']],
              ['label' => 'Bulan Ini',  'value' => $stat['transaksi_bulan_ini']],
          ];
          foreach ($statItems as $s) : ?>
          <div style="text-align:center">
            <p style="font-size:20px;font-weight:700;color:var(--text-primary)">
              <?= (int)$s['value'] ?>
            </p>
            <p style="font-size:10px;color:var(--text-secondary);
                      text-transform:uppercase;letter-spacing:.05em">
              <?= $s['label'] ?>
            </p>
          </div>
          <?php endforeach; ?>
        </div>

        <!-- Link ganti password -->
        <a href="<?= BASE_URL ?>/staff/ganti-password.php"
           class="btn btn--secondary btn--full btn--sm"
           style="margin-top:var(--space-3)">
          Ganti Password
        </a>

      </div><!-- /profil-card -->

      <!-- ── Kolom kanan: form edit akun ───────────────────── -->
      <div class="profil-form-card">

        <!-- Form: Data Akun -->
        <h2 class="profil-form-card__title">Data Akun</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/staff/proses-profil-staff.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="update_akun">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="nama">
                Nama Lengkap <span class="form-label__required">*</span>
              </label>
              <input type="text" id="nama" name="nama"
                     class="form-input"
                     value="<?= htmlspecialchars($user['nama'], ENT_QUOTES, 'UTF-8') ?>"
                     required>
            </div>
            <div class="form-group">
              <label class="form-label" for="email">
                Email <span class="form-label__required">*</span>
              </label>
              <input type="email" id="email" name="email"
                     class="form-input"
                     value="<?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?>"
                     required>
              <span class="form-hint">Digunakan untuk login.</span>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">
              Simpan Perubahan
            </button>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>

        <hr style="border:none;border-top:1px solid var(--border-light);
                   margin:var(--space-6) 0">

        <!-- Info EO — read-only -->
        <h2 class="profil-form-card__title">Informasi EO</h2>
        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:var(--space-4)">
          Informasi berikut dikelola oleh Admin EO dan tidak bisa diubah dari sini.
        </p>

        <div class="form-group">
          <label class="form-label">Nama EO</label>
          <input type="text" class="form-input form-input--readonly"
                 value="<?= htmlspecialchars($namaEO, ENT_QUOTES, 'UTF-8') ?>"
                 readonly>
        </div>
        <div class="form-group">
          <label class="form-label">Email EO</label>
          <input type="text" class="form-input form-input--readonly"
                 value="<?= htmlspecialchars($_SESSION['email'] ?? '—', ENT_QUOTES, 'UTF-8') ?>"
                 readonly>
          <span class="form-readonly-note">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.8"/>
              <path d="M12 8v4M12 16h.01" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            Hubungi Admin EO untuk mengubah informasi ini.
          </span>
        </div>

      </div><!-- /profil-form-card -->
    </div><!-- /profil-layout -->

  </div>
</div>

<?php
$page_type = 'staff';
require_once __DIR__ . '/../includes/footer.php';
?>