<?php
/**
 * EO Finance — Edit data staff + kelola penugasan event.
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role('admin_eo');

$eoId   = get_current_eo_id();
$staffId = (int) ($_GET['id'] ?? 0);

if ($staffId <= 0) {
    header('Location: ' . BASE_URL . '/admin/staff/index.php');
    exit();
}

// ── Ambil data staff — pastikan milik EO ini dan role staff_eo ────────────────
$stmtStaff = $mysqli->prepare(
    "SELECT id, nama, email, is_active, first_login
     FROM users
     WHERE id = ? AND eo_id = ? AND role = 'staff_eo'
     LIMIT 1"
);
$stmtStaff->bind_param('ii', $staffId, $eoId);
$stmtStaff->execute();
$staff = $stmtStaff->get_result()->fetch_assoc();
$stmtStaff->close();

if ($staff === null) {
    $_SESSION['__flash']['error'] = 'Staff tidak ditemukan.';
    header('Location: ' . BASE_URL . '/admin/staff/index.php');
    exit();
}

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

// Password sementara hasil reset (jika baru saja direset oleh admin)
// Dipisahkan dari $flashMessage agar tidak ikut dirender sebagai alert biasa.
$resetPassword = $flashMessage['reset_pass'] ?? ($_SESSION['__temp_password'] ?? '');
unset($flashMessage['reset_pass'], $_SESSION['__temp_password']);

$old = $_SESSION['__old_input'] ?? [];
unset($_SESSION['__old_input']);

$v = fn(string $key) => htmlspecialchars(
    $old[$key] ?? $staff[$key] ?? '',
    ENT_QUOTES, 'UTF-8'
);

// ── Event yang sudah ditugaskan ke staff ini ──────────────────────────────────
$stmtPenugasan = $mysqli->prepare(
    "SELECT p.id AS penugasan_id, p.role_dalam_event,
            e.id AS event_id, e.nama_event, e.status
     FROM penugasan p
     INNER JOIN events e ON p.event_id = e.id
     WHERE p.user_id = ? AND p.eo_id = ?
     ORDER BY e.tanggal_mulai DESC"
);
$stmtPenugasan->bind_param('ii', $staffId, $eoId);
$stmtPenugasan->execute();
$penugasanAktif = $stmtPenugasan->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPenugasan->close();

// ID event yang sudah ditugaskan (untuk exclude dari dropdown tambah)
$eventSudahDitugaskan = array_column($penugasanAktif, 'event_id');

// ── Event yang belum ditugaskan ke staff ini (untuk dropdown tambah) ──────────
$stmtEvents = $mysqli->prepare(
    "SELECT id, nama_event FROM events
     WHERE eo_id = ? AND status IN ('draft','aktif')
     ORDER BY tanggal_mulai DESC"
);
$stmtEvents->bind_param('i', $eoId);
$stmtEvents->execute();
$semuaEvents = $stmtEvents->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtEvents->close();

$eventBelumDitugaskan = array_filter(
    $semuaEvents,
    fn($ev) => !in_array((int)$ev['id'], $eventSudahDitugaskan, true)
);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$labelStatus = [
    'draft' => 'Draft', 'aktif' => 'Aktif',
    'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan',
];

$pageTitle = 'Edit Staff';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/admin/staff/index.php"
             class="breadcrumb__item">Staff</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">
            <?= htmlspecialchars($staff['nama'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </nav>
        <h1 class="page-title">Edit Staff</h1>
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

    <?php if ($resetPassword !== '') : ?>
    <!-- Box tampilkan password sementara setelah reset berhasil -->
    <div class="alert alert--success" role="alert" style="flex-direction:column;gap:8px">
      <p style="font-weight:600;color:inherit">
        Password berhasil direset! Sampaikan password sementara ini ke staff:
      </p>
      <code style="font-size:22px;letter-spacing:4px;padding:8px 16px;
                   background:rgba(0,0,0,.06);border-radius:6px;display:inline-block">
        <?= htmlspecialchars($resetPassword, ENT_QUOTES, 'UTF-8') ?>
      </code>
      <p style="font-size:13px;opacity:.8">
        Password ini hanya ditampilkan sekali. Staff wajib menggantinya saat login berikutnya.
      </p>
    </div>
    <?php endif; ?>

    <div class="form-layout">

      <!-- Kolom kiri: form edit data + penugasan -->
      <div style="display:flex;flex-direction:column;gap:20px">

        <!-- Edit data dasar -->
        <div class="form-card">
          <h2 class="form-card__title">Data Akun</h2>
          <form method="POST"
                action="<?= BASE_URL ?>/proses/admin/proses-staff.php"
                novalidate>
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= $staffId ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="nama">
                  Nama Lengkap <span class="form-label__required">*</span>
                </label>
                <input type="text" id="nama" name="nama"
                       class="form-input"
                       value="<?= $v('nama') ?>" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="email">
                  Email <span class="form-label__required">*</span>
                </label>
                <input type="email" id="email" name="email"
                       class="form-input"
                       value="<?= $v('email') ?>" required>
              </div>
            </div>

            <div class="form-actions">
              <button type="submit" class="btn btn--primary">Simpan Perubahan</button>
              <a href="<?= BASE_URL ?>/admin/staff/index.php"
                 class="btn btn--secondary">Batal</a>
            </div>
            <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
          </form>
        </div>

        <!-- Kelola penugasan event -->
        <div class="form-card">
          <h2 class="form-card__title">
            Penugasan Event
            <span style="font-weight:400;font-size:14px;color:var(--text-secondary)">
              (<?= count($penugasanAktif) ?> event)
            </span>
          </h2>

          <!-- Daftar event yang sudah ditugaskan -->
          <?php if (!empty($penugasanAktif)) : ?>
          <div class="penugasan-list" style="margin-bottom:20px">
            <?php foreach ($penugasanAktif as $pn) : ?>
            <div class="penugasan-item">
              <div>
                <p class="penugasan-item__event">
                  <?= htmlspecialchars($pn['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </p>
                <p class="penugasan-item__role">
                  <span class="badge badge--<?= $pn['status'] ?>" style="font-size:11px">
                    <?= $labelStatus[$pn['status']] ?? $pn['status'] ?>
                  </span>
                  <?php if ($pn['role_dalam_event']) : ?>
                  · <?= htmlspecialchars($pn['role_dalam_event'], ENT_QUOTES, 'UTF-8') ?>
                  <?php endif; ?>
                </p>
              </div>
              <div class="penugasan-item__actions">
                <form method="POST"
                      action="<?= BASE_URL ?>/proses/admin/proses-staff.php"
                      onsubmit="return confirm('Lepas penugasan dari event ini?')">
                  <input type="hidden" name="csrf_token"
                         value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="lepas_penugasan">
                  <input type="hidden" name="user_id" value="<?= $staffId ?>">
                  <input type="hidden" name="event_id"
                         value="<?= (int)$pn['event_id'] ?>">
                  <button type="submit" class="btn-icon btn--sm"
                          title="Lepas penugasan" style="color:var(--danger)">
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

          <!-- Form tambah penugasan baru -->
          <?php if (!empty($eventBelumDitugaskan)) : ?>
          <form method="POST"
                action="<?= BASE_URL ?>/proses/admin/proses-staff.php"
                novalidate>
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="tambah_penugasan">
            <input type="hidden" name="user_id" value="<?= $staffId ?>">

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="event_id_tugaskan">
                  Tambah ke Event
                </label>
                <select id="event_id_tugaskan" name="event_id"
                        class="form-input filter-select" required>
                  <option value="">— Pilih event —</option>
                  <?php foreach ($eventBelumDitugaskan as $ev) : ?>
                  <option value="<?= (int)$ev['id'] ?>">
                    <?= htmlspecialchars($ev['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label" for="role_dalam_event">
                  Peran dalam Event
                </label>
                <input type="text" id="role_dalam_event" name="role_dalam_event"
                       class="form-input"
                       placeholder="Koordinator, Kasir, Logistik, ...">
              </div>
            </div>

            <button type="submit" class="btn btn--secondary btn--sm">
              + Tugaskan ke Event
            </button>
          </form>
          <?php else : ?>
          <p class="text-muted" style="font-size:14px">
            Staff sudah ditugaskan ke semua event aktif.
          </p>
          <?php endif; ?>
        </div>

      </div><!-- /kolom kiri -->

      <!-- Kolom kanan: danger zone -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Info singkat staff -->
        <div class="form-sidebar-card">
          <p class="form-sidebar-card__title">Info Staff</p>
          <div style="display:flex;flex-direction:column;gap:8px;font-size:14px">
            <div style="display:flex;justify-content:space-between">
              <span class="text-muted">Status</span>
              <?php if ((int)$staff['is_active'] === 1) : ?>
              <span class="badge badge--aktif-user">Aktif</span>
              <?php else : ?>
              <span class="badge badge--nonaktif">Nonaktif</span>
              <?php endif; ?>
            </div>
            <div style="display:flex;justify-content:space-between">
              <span class="text-muted">Login Pertama</span>
              <span>
                <?= (int)$staff['first_login'] === 1
                    ? '<span class="badge badge--first-login">Belum</span>'
                    : 'Sudah' ?>
              </span>
            </div>
            <div style="display:flex;justify-content:space-between">
              <span class="text-muted">Event</span>
              <span><?= count($penugasanAktif) ?> ditugaskan</span>
            </div>
          </div>
        </div>

        <!-- Toggle aktif / reset password -->
        <div class="form-sidebar-card">
          <p class="form-sidebar-card__title">Tindakan Akun</p>

          <!-- Toggle aktif -->
          <form method="POST"
                action="<?= BASE_URL ?>/proses/admin/proses-staff.php"
                style="margin-bottom:10px"
                onsubmit="return confirm('<?= (int)$staff['is_active'] === 1
                    ? 'Nonaktifkan akun staff ini? Staff tidak bisa login.'
                    : 'Aktifkan kembali akun staff ini?' ?>')">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="toggle_aktif">
            <input type="hidden" name="id" value="<?= $staffId ?>">
            <button type="submit"
                    class="btn btn--sm btn--full
                           <?= (int)$staff['is_active'] === 1 ? 'btn--secondary' : 'btn--primary' ?>">
              <?= (int)$staff['is_active'] === 1 ? 'Nonaktifkan Akun' : 'Aktifkan Akun' ?>
            </button>
          </form>

          <!-- Reset password -->
          <form method="POST"
                action="<?= BASE_URL ?>/proses/admin/proses-staff.php"
                onsubmit="return confirm('Reset password? Staff harus membuat password baru saat login berikutnya.')">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" value="<?= $staffId ?>">
            <button type="submit" class="btn btn--secondary btn--sm btn--full">
              Reset Password Staff
            </button>
          </form>
        </div>

        <!-- Hapus akun -->
        <div class="danger-zone">
          <div>
            <p class="danger-zone__text">Hapus akun ini</p>
            <p class="danger-zone__desc">
              Penugasan ikut terhapus. Transaksi yang sudah dicatat staff ini
              tidak ikut terhapus (ON DELETE RESTRICT).
            </p>
          </div>
          <form method="POST"
                action="<?= BASE_URL ?>/proses/admin/proses-staff.php"
                onsubmit="return confirm('Yakin hapus akun staff ini?')">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="id" value="<?= $staffId ?>">
            <button type="submit" class="btn btn--danger btn--sm">Hapus</button>
          </form>
        </div>

      </div><!-- /kolom kanan -->

    </div><!-- /form-layout -->

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>