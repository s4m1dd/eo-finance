<?php
/**
 * EO Finance — Form edit event.
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

require_login();
require_role('admin_eo');

$eoId    = get_current_eo_id();
$eventId = (int) ($_GET['id'] ?? 0);

if ($eventId <= 0) {
    header('Location: ' . BASE_URL . '/admin/events/index.php');
    exit();
}

// ── Ambil data event (pastikan milik EO ini) ──────────────────────────────────
$stmtEv = $mysqli->prepare(
    'SELECT * FROM events WHERE id = ? AND eo_id = ? LIMIT 1'
);
$stmtEv->bind_param('ii', $eventId, $eoId);
$stmtEv->execute();
$event = $stmtEv->get_result()->fetch_assoc();
$stmtEv->close();

if ($event === null) {
    $_SESSION['__flash']['error'] = 'Event tidak ditemukan.';
    header('Location: ' . BASE_URL . '/admin/events/index.php');
    exit();
}

// ── Flash & old input ─────────────────────────────────────────────────────────
$flashMessage = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);
$old = $_SESSION['__old_input'] ?? [];
unset($_SESSION['__old_input']);

// Jika ada old input (validasi gagal), pakai itu; jika tidak, pakai data DB
$v = fn(string $key) => htmlspecialchars(
    $old[$key] ?? $event[$key] ?? '',
    ENT_QUOTES, 'UTF-8'
);

// ── Dropdown klien ────────────────────────────────────────────────────────────
$stmtKlien = $mysqli->prepare(
    'SELECT id, nama FROM klien WHERE eo_id = ? ORDER BY nama ASC'
);
$stmtKlien->bind_param('i', $eoId);
$stmtKlien->execute();
$daftarKlien = $stmtKlien->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtKlien->close();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$selectedKlien = $old['klien_id'] ?? $event['klien_id'] ?? '';
$selectedStatus = $old['status'] ?? $event['status'] ?? 'draft';

$pageTitle = 'Edit Event';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <nav class="breadcrumb" aria-label="Breadcrumb">
          <a href="<?= BASE_URL ?>/admin/events/index.php"
             class="breadcrumb__item">Event</a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= $eventId ?>"
             class="breadcrumb__item">
            <?= htmlspecialchars($event['nama_event'], ENT_QUOTES, 'UTF-8') ?>
          </a>
          <span class="breadcrumb__sep" aria-hidden="true">›</span>
          <span class="breadcrumb__item breadcrumb__item--active">Edit</span>
        </nav>
        <h1 class="page-title">Edit Event</h1>
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
        <h2 class="form-card__title">Detail Event</h2>

        <form method="POST"
              action="<?= BASE_URL ?>/proses/admin/proses-event.php"
              novalidate>
          <input type="hidden" name="csrf_token"
                 value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" value="<?= $eventId ?>">

          <div class="form-group">
            <label class="form-label" for="nama_event">
              Nama Event <span class="form-label__required">*</span>
            </label>
            <input type="text" id="nama_event" name="nama_event"
                   class="form-input"
                   value="<?= $v('nama_event') ?>" required>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="tanggal_mulai">
                Tanggal Mulai <span class="form-label__required">*</span>
              </label>
              <input type="date" id="tanggal_mulai" name="tanggal_mulai"
                     class="form-input"
                     value="<?= $v('tanggal_mulai') ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label" for="tanggal_selesai">
                Tanggal Selesai <span class="form-label__required">*</span>
              </label>
              <input type="date" id="tanggal_selesai" name="tanggal_selesai"
                     class="form-input"
                     value="<?= $v('tanggal_selesai') ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="klien_id">Klien</label>
              <select id="klien_id" name="klien_id"
                      class="form-input filter-select">
                <option value="">— Tanpa klien —</option>
                <?php foreach ($daftarKlien as $kl) : ?>
                <option value="<?= (int)$kl['id'] ?>"
                        <?= (string)$selectedKlien === (string)$kl['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($kl['nama'], ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label" for="status">
                Status <span class="form-label__required">*</span>
              </label>
              <select id="status" name="status"
                      class="form-input filter-select" required>
                <?php
                $statuses = ['draft' => 'Draft', 'aktif' => 'Aktif',
                             'selesai' => 'Selesai', 'dibatalkan' => 'Dibatalkan'];
                foreach ($statuses as $val => $lbl) :
                ?>
                <option value="<?= $val ?>"
                        <?= $selectedStatus === $val ? 'selected' : '' ?>>
                  <?= $lbl ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label" for="lokasi">Lokasi</label>
              <input type="text" id="lokasi" name="lokasi"
                     class="form-input"
                     value="<?= $v('lokasi') ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="anggaran">
                Anggaran (Rp) <span class="form-label__required">*</span>
              </label>
              <input type="number" id="anggaran" name="anggaran"
                     class="form-input"
                     min="0" step="1000"
                     value="<?= $v('anggaran') ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label" for="deskripsi">Deskripsi</label>
            <textarea id="deskripsi" name="deskripsi"
                      class="form-input form-input--textarea"
                      rows="4"><?= $v('deskripsi') ?></textarea>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn--primary">Simpan Perubahan</button>
            <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= $eventId ?>"
               class="btn btn--secondary">Batal</a>
          </div>
          <p class="form-note"><span aria-hidden="true">*</span> Wajib diisi</p>
        </form>
      </div>

      <!-- Danger zone -->
      <div>
        <div class="form-sidebar-card">
          <p class="form-sidebar-card__title">Info</p>
          <div class="form-sidebar-tip">
            Mengubah status ke <strong>Dibatalkan</strong> tidak menghapus
            transaksi yang sudah dicatat.
          </div>
        </div>
        <div class="danger-zone" style="margin-top:16px">
          <div>
            <p class="danger-zone__text">Hapus event ini</p>
            <p class="danger-zone__desc">
              Semua transaksi dan penugasan terkait ikut terhapus.
            </p>
          </div>
          <form method="POST"
                action="<?= BASE_URL ?>/proses/admin/proses-event.php"
                onsubmit="return confirm('Yakin hapus event ini beserta seluruh datanya?')">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="hapus">
            <input type="hidden" name="id" value="<?= $eventId ?>">
            <button type="submit" class="btn btn--danger btn--sm">
              Hapus Event
            </button>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>

<?php
$page_type = 'admin';
require_once __DIR__ . '/../../includes/footer.php';
?>