<?php
/**
 * EO Finance — Recurring Expense Template.
 *
 * Admin bisa menyimpan template transaksi yang sering berulang
 * (mis. "Honor MC", "Sewa Sound", "DP Venue", dll) dan
 * menggunakannya sebagai "isi otomatis" saat mencatat transaksi baru.
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
$flash = $_SESSION['__flash'] ?? [];
unset($_SESSION['__flash']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Ambil semua template ──────────────────────────────────────────────────────
$stmtT = $mysqli->prepare(
    "SELECT t.*, u.nama AS nama_pembuat
     FROM expense_template t
     INNER JOIN users u ON t.created_by = u.id
     WHERE t.eo_id = ?
     ORDER BY t.tipe ASC, t.kategori ASC, t.nama_template ASC"
);
$stmtT->bind_param('i', $eoId);
$stmtT->execute();
$templates = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtT->close();

$pemasukan   = array_filter($templates, fn($t) => $t['tipe'] === 'pemasukan');
$pengeluaran = array_filter($templates, fn($t) => $t['tipe'] === 'pengeluaran');

$pageTitle = 'Template Transaksi';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Template Transaksi</h1>
        <p class="page-subtitle">
          Simpan transaksi yang sering berulang — dipakai sebagai isi otomatis
          saat mencatat transaksi baru.
        </p>
      </div>
    </div>

    <?php foreach ($flash as $type => $msg) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
      <span class="alert__text"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endforeach; ?>

    <div class="event-detail-grid">

      <!-- Kolom kiri: daftar template -->
      <div class="event-detail-main">

        <!-- Pengeluaran -->
        <div class="card">
          <div class="card__header">
            <h2 class="card__title">Template Pengeluaran (<?= count($pengeluaran) ?>)</h2>
          </div>
          <?php if (empty($pengeluaran)) : ?>
          <p class="text-muted" style="padding:var(--space-3) 0;font-size:14px">Belum ada template pengeluaran.</p>
          <?php else : ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th scope="col">Nama Template</th>
                  <th scope="col">Kategori</th>
                  <th scope="col">Estimasi</th>
                  <th scope="col">Status</th>
                  <th scope="col">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pengeluaran as $t) : ?>
                <tr>
                  <td class="data-table__cell data-table__cell--primary">
                    <?= htmlspecialchars($t['nama_template'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($t['deskripsi']) : ?>
                    <div class="text-muted" style="font-size:12px">
                      <?= htmlspecialchars($t['deskripsi'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php endif; ?>
                  </td>
                  <td class="data-table__cell">
                    <?= htmlspecialchars($t['kategori'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="data-table__cell data-table__cell--amount text-red">
                    <?= $t['jumlah_estimasi'] ? format_rupiah($t['jumlah_estimasi']) : '—' ?>
                  </td>
                  <td class="data-table__cell">
                    <?php if ($t['is_active']) : ?>
                    <span class="badge badge--aktif">Aktif</span>
                    <?php else : ?>
                    <span class="badge badge--dibatalkan">Nonaktif</span>
                    <?php endif; ?>
                  </td>
                  <td class="data-table__cell">
                    <div style="display:flex;gap:6px">
                      <form method="POST"
                            action="<?= BASE_URL ?>/proses/admin/proses-expense-template.php">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="btn btn--outline btn--sm">
                          <?= $t['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                      </form>
                      <form method="POST"
                            action="<?= BASE_URL ?>/proses/admin/proses-expense-template.php"
                            onsubmit="return confirm('Hapus template ini?')">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="btn btn--outline-danger btn--sm">Hapus</button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

        <!-- Pemasukan -->
        <div class="card" style="margin-top:var(--space-5)">
          <div class="card__header">
            <h2 class="card__title">Template Pemasukan (<?= count($pemasukan) ?>)</h2>
          </div>
          <?php if (empty($pemasukan)) : ?>
          <p class="text-muted" style="padding:var(--space-3) 0;font-size:14px">Belum ada template pemasukan.</p>
          <?php else : ?>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th scope="col">Nama Template</th>
                  <th scope="col">Kategori</th>
                  <th scope="col">Estimasi</th>
                  <th scope="col">Status</th>
                  <th scope="col">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pemasukan as $t) : ?>
                <tr>
                  <td class="data-table__cell data-table__cell--primary">
                    <?= htmlspecialchars($t['nama_template'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($t['deskripsi']) : ?>
                    <div class="text-muted" style="font-size:12px">
                      <?= htmlspecialchars($t['deskripsi'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                    <?php endif; ?>
                  </td>
                  <td class="data-table__cell">
                    <?= htmlspecialchars($t['kategori'], ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td class="data-table__cell data-table__cell--amount text-green">
                    <?= $t['jumlah_estimasi'] ? format_rupiah($t['jumlah_estimasi']) : '—' ?>
                  </td>
                  <td class="data-table__cell">
                    <?php if ($t['is_active']) : ?>
                    <span class="badge badge--aktif">Aktif</span>
                    <?php else : ?>
                    <span class="badge badge--dibatalkan">Nonaktif</span>
                    <?php endif; ?>
                  </td>
                  <td class="data-table__cell">
                    <div style="display:flex;gap:6px">
                      <form method="POST"
                            action="<?= BASE_URL ?>/proses/admin/proses-expense-template.php">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="btn btn--outline btn--sm">
                          <?= $t['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                        </button>
                      </form>
                      <form method="POST"
                            action="<?= BASE_URL ?>/proses/admin/proses-expense-template.php"
                            onsubmit="return confirm('Hapus template ini?')">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="hapus">
                        <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                        <button type="submit" class="btn btn--outline-danger btn--sm">Hapus</button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>

      </div>

      <!-- Kolom kanan: form tambah template baru -->
      <div class="event-detail-side">
        <div class="card">
          <div class="card__header"><h2 class="card__title">Tambah Template</h2></div>
          <form method="POST" action="<?= BASE_URL ?>/proses/admin/proses-expense-template.php">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="tambah">

            <div class="form-group">
              <label class="form-label" for="nama_template">
                Nama Template <span class="form-label__required">*</span>
              </label>
              <input type="text" id="nama_template" name="nama_template"
                     class="form-input" required
                     placeholder="mis. Honor MC, Sewa Sound, DP Venue">
            </div>

            <div class="form-group">
              <label class="form-label" for="tipe">
                Tipe <span class="form-label__required">*</span>
              </label>
              <select id="tipe" name="tipe" class="form-input filter-select" required>
                <option value="pengeluaran">Pengeluaran</option>
                <option value="pemasukan">Pemasukan</option>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label" for="kategori">
                Kategori <span class="form-label__required">*</span>
              </label>
              <input type="text" id="kategori" name="kategori"
                     class="form-input" required
                     placeholder="mis. honor, sewa, katering">
            </div>

            <div class="form-group">
              <label class="form-label" for="jumlah_estimasi">Estimasi Jumlah</label>
              <input type="text" id="jumlah_estimasi" name="jumlah_estimasi"
                     class="form-input" inputmode="numeric"
                     placeholder="Kosongkan jika tidak tentu">
              <p class="form-hint">Bisa diubah saat memakai template ini.</p>
            </div>

            <div class="form-group">
              <label class="form-label" for="deskripsi_tmpl">Deskripsi</label>
              <textarea id="deskripsi_tmpl" name="deskripsi"
                        class="form-input form-input--textarea" rows="2"
                        placeholder="Keterangan singkat (opsional)"></textarea>
            </div>

            <button type="submit" class="btn btn--primary"
                    style="width:100%;justify-content:center">
              Simpan Template
            </button>
          </form>
        </div>
      </div>

    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>