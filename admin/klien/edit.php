<?php
/**
 * EO Finance — Form edit klien.
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
$klienId = (int) ($_GET['id'] ?? 0);

if ($klienId <= 0) {
    header('Location: ' . BASE_URL . '/admin/klien/index.php');
    exit();
}

// ── Ambil data klien — validasi eo_id ─────────────────────────────────────────
$stmt = $mysqli->prepare(
    'SELECT * FROM klien WHERE id = ? AND eo_id = ? LIMIT 1'
);
$stmt->bind_param('ii', $klienId, $eoId);
$stmt->execute();
$klien = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($klien === null) {
    $_SESSION['__flash']['error'] = 'Klien tidak ditemukan.';
    header('Location: ' . BASE_URL . '/admin/klien/index.php');
    exit();
}

$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);
$old = $_SESSION['__old_input'] ?? [];
unset($_SESSION['__old_input']);

$v = fn(string $key) => htmlspecialchars(
    $old[$key] ?? $klien[$key] ?? '',
    ENT_QUOTES, 'UTF-8'
);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Edit Klien';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/admin/klien/index.php"
             class="breadcrumb__item">Klien</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">
            <?= htmlspecialchars($klien['nama'], ENT_QUOTES, 'UTF-8') ?>
          </span>
        </nav>
        <h1 class="page-title">Edit Klien</h1>
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

    <div class="form-layout">
      <div class="form-card">
        <h2 class="form-card__title">Data Klien</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-klien.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" value="<?= $klienId ?>">

          <div class="form-group">
            <label class="form-label" for="nama">
              Nama Klien <span class="form-label__required">*</span>
            </label>
            <input type="text" id="nama" name="nama"
                   class="form-input"
                   value="<?= $v('nama') ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email"
                     class="form-input"
                     value="<?= $v('email') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="telepon">Telepon</label>
              <input type="tel" id="telepon" name="telepon"
                     class="form-input"
                     value="<?= $v('telepon') ?>">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat"
                      class="form-input form-input--textarea"
                      rows="3"><?= $v('alamat') ?></textarea>
          </div>

          <div class="form-group">
            <label class="form-label" for="catatan">Catatan</label>
            <textarea id="catatan" name="catatan"
                      class="form-input form-input--textarea"
                      rows="3"><?= $v('catatan') ?></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Simpan Perubahan</button>
            <a href="<?= BASE_URL ?>/admin/klien/index.php"
               class="btn btn--secondary">Batal</a>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
      </div>

      <!-- Danger zone -->
      <div class="danger-zone">
        <div>
          <p class="danger-zone__text">Hapus klien ini</p>
          <p class="danger-zone__desc">
            Event yang menggunakan klien ini tidak ikut terhapus —
            kolom klien di event tersebut akan menjadi kosong.
          </p>
        </div>
        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-klien.php"
              onsubmit="return confirm('Hapus klien ini?')">
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="hapus">
          <input type="hidden" name="id" value="<?= $klienId ?>">
          <button type="submit" class="btn btn--danger btn--sm">Hapus Klien</button>
        </form>
      </div>
    </div>

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>