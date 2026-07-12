<?php
/**
 * EO Finance — Piutang & Hutang.
 *
 * Piutang  = transaksi tipe 'pemasukan' yang masih 'belum_lunas' → klien
 *            belum membayar penuh untuk event terkait.
 * Hutang   = transaksi tipe 'pengeluaran' yang masih 'belum_lunas' → EO
 *            belum membayar penuh ke vendor/pihak terkait.
 *
 * Hanya transaksi berstatus approval 'approved' yang dihitung, supaya
 * konsisten dengan dashboard & laporan (transaksi pending belum final).
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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Daftar Piutang (pemasukan, belum lunas, approved) ─────────────────────────
$stmtPiutang = $mysqli->prepare(
    "SELECT t.id, t.kategori, t.jumlah, t.tanggal_transaksi, t.deskripsi,
            e.id AS event_id, e.nama_event,
            k.id AS klien_id, k.nama AS nama_klien, k.telepon AS telepon_klien
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     LEFT  JOIN klien  k ON e.klien_id = k.id
     WHERE t.eo_id = ? AND t.tipe = 'pemasukan'
       AND t.status_approval = 'approved' AND t.status_pembayaran = 'belum_lunas'
     ORDER BY t.tanggal_transaksi ASC"
);
$stmtPiutang->bind_param('i', $eoId);
$stmtPiutang->execute();
$daftarPiutang = $stmtPiutang->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtPiutang->close();

$totalPiutang = array_sum(array_column($daftarPiutang, 'jumlah'));

// Ringkasan per klien (untuk kartu/grouping ringan)
$piutangPerKlien = [];
foreach ($daftarPiutang as $row) {
    $namaKlien = $row['nama_klien'] ?? 'Tanpa Klien';
    $piutangPerKlien[$namaKlien] = ($piutangPerKlien[$namaKlien] ?? 0) + (float) $row['jumlah'];
}
arsort($piutangPerKlien);

// ── Daftar Hutang (pengeluaran, belum lunas, approved) ────────────────────────
$stmtHutang = $mysqli->prepare(
    "SELECT t.id, t.kategori, t.jumlah, t.tanggal_transaksi, t.deskripsi,
            e.id AS event_id, e.nama_event,
            v.id AS vendor_id, v.nama AS nama_vendor, v.telepon AS telepon_vendor
     FROM transaksi t
     INNER JOIN events e ON t.event_id = e.id
     LEFT  JOIN vendor v ON t.vendor_id = v.id
     WHERE t.eo_id = ? AND t.tipe = 'pengeluaran'
       AND t.status_approval = 'approved' AND t.status_pembayaran = 'belum_lunas'
     ORDER BY t.tanggal_transaksi ASC"
);
$stmtHutang->bind_param('i', $eoId);
$stmtHutang->execute();
$daftarHutang = $stmtHutang->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtHutang->close();

$totalHutang = array_sum(array_column($daftarHutang, 'jumlah'));

$hutangPerVendor = [];
foreach ($daftarHutang as $row) {
    $namaVendor = $row['nama_vendor'] ?? 'Tanpa Vendor';
    $hutangPerVendor[$namaVendor] = ($hutangPerVendor[$namaVendor] ?? 0) + (float) $row['jumlah'];
}
arsort($hutangPerVendor);

$pageTitle = 'Piutang & Hutang';
$page_type = 'admin';
?>
<?php require_once __DIR__ . '/../../includes/header.php'; ?>
<?php require_once __DIR__ . '/../../includes/navbar-admin.php'; ?>

<div class="admin-layout">
  <div class="admin-content">

    <div class="page-header">
      <div class="page-header__left">
        <h1 class="page-title">Piutang &amp; Hutang</h1>
        <p class="page-subtitle">
          Daftar transaksi yang masih "Belum Lunas" — diatur lewat status pembayaran
          saat mencatat/mengedit transaksi.
        </p>
      </div>
    </div>

    <?php foreach ($flashMessage as $type => $pesan) : ?>
    <div class="alert alert--<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" role="alert">
      <span class="alert__text"><?= htmlspecialchars($pesan, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <?php endforeach; ?>

    <!-- Ringkasan -->
    <div class="trx-summary">
      <div class="trx-summary__item">
        <span class="trx-summary__label">Total Piutang <small>(klien belum bayar)</small></span>
        <span class="trx-summary__value trx-summary__value--green">
          <?= format_rupiah($totalPiutang) ?>
        </span>
      </div>
      <div class="trx-summary__item">
        <span class="trx-summary__label">Total Hutang <small>(belum dibayar ke vendor)</small></span>
        <span class="trx-summary__value trx-summary__value--red">
          <?= format_rupiah($totalHutang) ?>
        </span>
      </div>
      <div class="trx-summary__item">
        <span class="trx-summary__label">Posisi Bersih</span>
        <?php $posisiBersih = $totalPiutang - $totalHutang; ?>
        <span class="trx-summary__value
                     <?= $posisiBersih >= 0 ? 'trx-summary__value--green' : 'trx-summary__value--red' ?>">
          <?= format_rupiah($posisiBersih) ?>
        </span>
      </div>
    </div>

    <!-- ════════════════════ PIUTANG ════════════════════ -->
    <div class="card" style="margin-top:24px">
      <div class="card__header" style="display:flex;justify-content:space-between;align-items:center">
        <h2 class="card__title">Piutang Klien (<?= count($daftarPiutang) ?>)</h2>
      </div>

      <?php if (empty($daftarPiutang)) : ?>
      <div class="empty-state" style="padding:32px 16px">
        <p class="empty-state__text">Tidak ada piutang. Semua pemasukan sudah lunas. 🎉</p>
      </div>
      <?php else : ?>

      <!-- Ringkasan per klien -->
      <div class="piutang-hutang-chips">
        <?php foreach ($piutangPerKlien as $nama => $total) : ?>
        <span class="piutang-hutang-chip">
          <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>
          <strong><?= format_rupiah($total) ?></strong>
        </span>
        <?php endforeach; ?>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th scope="col">Klien</th>
              <th scope="col">Event</th>
              <th scope="col">Kategori</th>
              <th scope="col">Tanggal</th>
              <th scope="col">Jumlah</th>
              <th scope="col">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarPiutang as $row) : ?>
            <tr>
              <td class="data-table__cell data-table__cell--primary">
                <?= htmlspecialchars($row['nama_klien'] ?? 'Tanpa Klien', ENT_QUOTES, 'UTF-8') ?>
                <?php if ($row['telepon_klien']) : ?>
                <div class="text-muted" style="font-size:12px">
                  <?= htmlspecialchars($row['telepon_klien'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>
              </td>
              <td class="data-table__cell">
                <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$row['event_id'] ?>" class="table-link">
                  <?= htmlspecialchars($row['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </td>
              <td class="data-table__cell"><?= htmlspecialchars($row['kategori'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="data-table__cell"><?= date('d M Y', strtotime($row['tanggal_transaksi'])) ?></td>
              <td class="data-table__cell data-table__cell--amount text-green">
                <?= format_rupiah($row['jumlah']) ?>
              </td>
              <td class="data-table__cell">
                <form method="POST" action="<?= BASE_URL ?>/proses/admin/proses-transaksi.php"
                      onsubmit="return confirm('Tandai transaksi ini sudah lunas?')">
                  <input type="hidden" name="csrf_token"
                         value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="tandai_lunas">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="kembali_ke"
                         value="<?= BASE_URL ?>/admin/piutang-hutang/index.php">
                  <button type="submit" class="btn btn--outline btn--sm">Tandai Lunas</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- ════════════════════ HUTANG ════════════════════ -->
    <div class="card" style="margin-top:24px">
      <div class="card__header" style="display:flex;justify-content:space-between;align-items:center">
        <h2 class="card__title">Hutang Vendor (<?= count($daftarHutang) ?>)</h2>
      </div>

      <?php if (empty($daftarHutang)) : ?>
      <div class="empty-state" style="padding:32px 16px">
        <p class="empty-state__text">Tidak ada hutang. Semua pengeluaran sudah lunas. 🎉</p>
      </div>
      <?php else : ?>

      <!-- Ringkasan per vendor -->
      <div class="piutang-hutang-chips">
        <?php foreach ($hutangPerVendor as $nama => $total) : ?>
        <span class="piutang-hutang-chip piutang-hutang-chip--hutang">
          <?= htmlspecialchars($nama, ENT_QUOTES, 'UTF-8') ?>
          <strong><?= format_rupiah($total) ?></strong>
        </span>
        <?php endforeach; ?>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th scope="col">Vendor</th>
              <th scope="col">Event</th>
              <th scope="col">Kategori</th>
              <th scope="col">Tanggal</th>
              <th scope="col">Jumlah</th>
              <th scope="col">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($daftarHutang as $row) : ?>
            <tr>
              <td class="data-table__cell data-table__cell--primary">
                <?= htmlspecialchars($row['nama_vendor'] ?? 'Tanpa Vendor', ENT_QUOTES, 'UTF-8') ?>
                <?php if ($row['telepon_vendor']) : ?>
                <div class="text-muted" style="font-size:12px">
                  <?= htmlspecialchars($row['telepon_vendor'], ENT_QUOTES, 'UTF-8') ?>
                </div>
                <?php endif; ?>
              </td>
              <td class="data-table__cell">
                <a href="<?= BASE_URL ?>/admin/events/detail.php?id=<?= (int)$row['event_id'] ?>" class="table-link">
                  <?= htmlspecialchars($row['nama_event'], ENT_QUOTES, 'UTF-8') ?>
                </a>
              </td>
              <td class="data-table__cell"><?= htmlspecialchars($row['kategori'], ENT_QUOTES, 'UTF-8') ?></td>
              <td class="data-table__cell"><?= date('d M Y', strtotime($row['tanggal_transaksi'])) ?></td>
              <td class="data-table__cell data-table__cell--amount text-red">
                <?= format_rupiah($row['jumlah']) ?>
              </td>
              <td class="data-table__cell">
                <form method="POST" action="<?= BASE_URL ?>/proses/admin/proses-transaksi.php"
                      onsubmit="return confirm('Tandai transaksi ini sudah lunas?')">
                  <input type="hidden" name="csrf_token"
                         value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                  <input type="hidden" name="action" value="tandai_lunas">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="kembali_ke"
                         value="<?= BASE_URL ?>/admin/piutang-hutang/index.php">
                  <button type="submit" class="btn btn--outline btn--sm">Tandai Lunas</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>