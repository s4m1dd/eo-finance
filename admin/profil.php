<?php
/**
 * EO Finance — Halaman profil admin_eo.
 *
 * Admin bisa mengubah nama, email, dan password akun,
 * serta mengubah data EO (nama, telepon, alamat).
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
require_role('admin_eo');

$userId = get_current_user_id();
$eoId   = get_current_eo_id();

// ── Ambil data user terkini dari DB ──────────────────────────────────────────
$stmtUser = $mysqli->prepare(
    "SELECT id, nama, email, password, two_fa_enabled, created_at
     FROM users
     WHERE id = ? AND eo_id = ? AND role = 'admin_eo'
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

// ── Ambil data EO terkini ─────────────────────────────────────────────────────
$stmtEO = $mysqli->prepare(
    'SELECT id, nama_eo, email, telepon, alamat, created_at FROM eo WHERE id = ? LIMIT 1'
);
$stmtEO->bind_param('i', $eoId);
$stmtEO->execute();
$eo = $stmtEO->get_result()->fetch_assoc();
$stmtEO->close();

// ── Statistik ringkas ─────────────────────────────────────────────────────────
$stmtStat = $mysqli->prepare(
    "SELECT
        (SELECT COUNT(*) FROM events     WHERE eo_id = ?)  AS total_event,
        (SELECT COUNT(*) FROM users      WHERE eo_id = ? AND role = 'staff_eo') AS total_staff,
        (SELECT COUNT(*) FROM transaksi  WHERE eo_id = ?)  AS total_transaksi,
        (SELECT COUNT(*) FROM klien      WHERE eo_id = ?)  AS total_klien,
        (SELECT COUNT(*) FROM vendor     WHERE eo_id = ?)  AS total_vendor"
);
$stmtStat->bind_param('iiiii', $eoId, $eoId, $eoId, $eoId, $eoId);
$stmtStat->execute();
$stat = $stmtStat->get_result()->fetch_assoc();
$stmtStat->close();

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Profil';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Profil</h1>
        <p class="page-subtitle">Kelola akun dan data EO kamu</p>
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

      <!-- ── Kolom kiri: kartu identitas ──────────────────────── -->
      <div class="profil-card">

        <!-- Avatar inisial -->
        <div class="profil-card__avatar" aria-hidden="true">
          <?= mb_strtoupper(mb_substr($user['nama'], 0, 1, 'UTF-8'), 'UTF-8') ?>
        </div>

        <h2 class="profil-card__name">
          <?= htmlspecialchars($user['nama'], ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <span class="profil-card__role">Admin EO</span>

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
              <rect x="3" y="4" width="18" height="18" rx="2"
                    stroke="currentColor" stroke-width="1.8"/>
              <path d="M3 10h18M8 2v4M16 2v4"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            </svg>
            Bergabung <?= date('M Y', strtotime($user['created_at'])) ?>
          </div>
          <div class="profil-card__meta-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 aria-hidden="true">
              <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"
                    stroke="currentColor" stroke-width="1.8"/>
              <rect x="9" y="14" width="6" height="8"
                    stroke="currentColor" stroke-width="1.8"/>
            </svg>
            <?= htmlspecialchars($eo['nama_eo'], ENT_QUOTES, 'UTF-8') ?>
          </div>
        </div>

        <!-- Statistik EO -->
        <div class="profil-card__stats">
          <?php
          $statItems = [
              ['label' => 'Event',     'value' => $stat['total_event']],
              ['label' => 'Staff',     'value' => $stat['total_staff']],
              ['label' => 'Transaksi', 'value' => $stat['total_transaksi']],
              ['label' => 'Klien',     'value' => $stat['total_klien']],
              ['label' => 'Vendor',    'value' => $stat['total_vendor']],
          ];
          foreach ($statItems as $s) : ?>
          <div class="profil-card__stat">
            <p class="profil-card__stat-value"><?= (int)$s['value'] ?></p>
            <p class="profil-card__stat-label"><?= htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8') ?></p>
          </div>
          <?php endforeach; ?>
        </div>

      </div><!-- /profil-card -->

      <!-- ── Kolom kanan: form-form ─────────────────────────── -->
      <div class="profil-form-card">

        <!-- ── Form 1: Data Akun ──────────────────────────────── -->
        <div class="profil-form-card__section">
        <h2 class="profil-form-card__title">Data Akun</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-profil.php"
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
              Simpan Data Akun
            </button>
          </div>
        </form>
        </div><!-- /profil-form-card__section -->

        <hr class="profil-form-card__divider">

        <!-- ── Form 2: Ganti Password ─────────────────────────── -->
        <div class="profil-form-card__section">
        <h2 class="profil-form-card__title">Ganti Password</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-profil.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="ganti_password">

          <div class="form-group">
            <label class="form-label" for="password_lama">
              Password Saat Ini <span class="form-label__required">*</span>
            </label>
            <input type="password" id="password_lama" name="password_lama"
                   class="form-input"
                   placeholder="Masukkan password saat ini"
                   autocomplete="current-password"
                   required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="password_baru">
                Password Baru <span class="form-label__required">*</span>
              </label>
              <input type="password" id="password_baru" name="password_baru"
                     class="form-input"
                     placeholder="Minimal <?= PASSWORD_MIN_LENGTH ?> karakter"
                     minlength="<?= PASSWORD_MIN_LENGTH ?>"
                     autocomplete="new-password"
                     required>
            </div>
            <div class="form-group">
              <label class="form-label" for="konfirmasi_password">
                Konfirmasi Password Baru <span class="form-label__required">*</span>
              </label>
              <input type="password" id="konfirmasi_password"
                     name="konfirmasi_password"
                     class="form-input"
                     placeholder="Ulangi password baru"
                     autocomplete="new-password"
                     required>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">
              Ganti Password
            </button>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
        </div><!-- /profil-form-card__section -->

        <hr class="profil-form-card__divider">

        <!-- ── Keamanan: Two-Factor Authentication ─────────────── -->
        <div class="profil-form-card__section">
        <h2 class="profil-form-card__title">Verifikasi Dua Langkah (2FA)</h2>
        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:var(--space-4)">
          Saat diaktifkan, setiap login memerlukan kode OTP 6 digit yang dikirim
          ke email kamu sebagai lapisan keamanan tambahan.
        </p>

        <?php $twoFaAktif = (int)($user['two_fa_enabled'] ?? 0) === 1; ?>
        <div style="display:flex;align-items:center;gap:var(--space-4);
                    padding:var(--space-4);border-radius:var(--radius-md);
                    background:<?= $twoFaAktif ? '#F0FDF4' : 'var(--bg-light)' ?>;
                    border:1px solid <?= $twoFaAktif ? '#BBF7D0' : 'var(--border-light)' ?>">
          <div style="flex:1">
            <p style="font-size:15px;font-weight:600;margin:0">
              <?= $twoFaAktif ? '✅ 2FA Aktif' : '🔓 2FA Tidak Aktif' ?>
            </p>
            <p style="font-size:13px;color:var(--text-secondary);margin:4px 0 0">
              <?= $twoFaAktif
                  ? 'Login kamu dilindungi verifikasi OTP via email.'
                  : 'Aktifkan untuk keamanan login yang lebih kuat.' ?>
            </p>
          </div>
          <form method="POST" action="<?= BASE_URL ?>/proses/admin/proses-profil.php"
                <?= !$twoFaAktif ? '' : 'onsubmit="return confirm(\'Matikan 2FA? Login tidak lagi memerlukan OTP.\')"' ?>>
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="toggle_2fa">
            <button type="submit"
                    class="btn <?= $twoFaAktif ? 'btn--outline-danger' : 'btn--primary' ?>">
              <?= $twoFaAktif ? 'Nonaktifkan 2FA' : 'Aktifkan 2FA' ?>
            </button>
          </form>
        </div>
        </div><!-- /profil-form-card__section -->

        <hr class="profil-form-card__divider">

        <!-- ── Form 3: Data EO ────────────────────────────────── -->
        <div class="profil-form-card__section">
        <h2 class="profil-form-card__title">Data Event Organizer</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-profil.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="update_eo">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="nama_eo">
                Nama EO <span class="form-label__required">*</span>
              </label>
              <input type="text" id="nama_eo" name="nama_eo"
                     class="form-input"
                     value="<?= htmlspecialchars($eo['nama_eo'], ENT_QUOTES, 'UTF-8') ?>"
                     required>
            </div>
            <div class="form-group">
              <label class="form-label" for="telepon_eo">Telepon EO</label>
              <input type="tel" id="telepon_eo" name="telepon"
                     class="form-input"
                     placeholder="08xxxxxxxxxx"
                     value="<?= htmlspecialchars($eo['telepon'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="alamat_eo">Alamat EO</label>
            <textarea id="alamat_eo" name="alamat"
                      class="form-input form-input--textarea"
                      rows="3"><?= htmlspecialchars($eo['alamat'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>

          <!-- Email EO — readonly, karena dipakai sebagai identifier EO -->
          <div class="form-group">
            <label class="form-label" for="email_eo">Email EO</label>
            <input type="email" id="email_eo"
                   class="form-input form-input--readonly"
                   value="<?= htmlspecialchars($eo['email'], ENT_QUOTES, 'UTF-8') ?>"
                   readonly>
            <span class="form-readonly-note">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                   aria-hidden="true">
                <circle cx="12" cy="12" r="10"
                        stroke="currentColor" stroke-width="1.8"/>
                <path d="M12 8v4M12 16h.01" stroke="currentColor"
                      stroke-width="1.8" stroke-linecap="round"/>
              </svg>
              Email EO tidak bisa diubah karena digunakan sebagai identitas unik EO.
            </span>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">
              Simpan Data EO
            </button>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
        </div><!-- /profil-form-card__section -->

      </div><!-- /profil-form-card -->
    </div><!-- /profil-layout -->

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../includes/footer.php';
?>