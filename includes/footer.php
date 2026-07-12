<?php
/**
 * EO Finance — Footer HTML global.
 *
 * Menutup konten utama, memuat script JavaScript sesuai $page_type,
 * menampilkan copyright, lalu menutup </body> dan </html>.
 *
 * CARA PAKAI — di akhir setiap halaman:
 *
 *   <?php
 *   $page_type = 'admin';   // atau 'staff' | 'public'
 *   require_once __DIR__ . '/../../includes/footer.php';
 *   ?>
 *
 * Script yang dimuat:
 *   1. Chart.js 4.4.0 (CDN, selalu dimuat)
 *   2. assets/js/main.js (selalu dimuat)
 *   3. assets/js/admin.js (hanya jika $page_type === 'admin')
 *      atau assets/js/staff.js (hanya jika $page_type === 'staff')
 *
 * @package EOFinance
 */

// ── Guard ────────────────────────────────────────────────────────────────────
if (!defined('EO_FINANCE_APP')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

// ── Baca dan validasi $page_type dari scope pemanggil ────────────────────────
$_footerPageType = strtolower(trim($page_type ?? 'public'));
if (!in_array($_footerPageType, ['admin', 'staff', 'public'], true)) {
    $_footerPageType = 'public';
}

// ── Script tambahan sesuai role ───────────────────────────────────────────────
$_extraJs = match ($_footerPageType) {
    'admin' => BASE_URL . '/assets/js/admin.js',
    'staff' => BASE_URL . '/assets/js/staff.js',
    default => null,
};

// ── Tahun copyright (otomatis) ────────────────────────────────────────────────
$_year = (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('Y');
?>

  <?php if ($_footerPageType === 'public') : ?>
  </main><!-- /#main — hanya halaman publik yang punya tag <main> eksplisit -->
  <?php endif; ?>

  <?php if ($_footerPageType === 'public') : ?>
  <!-- Footer copyright — hanya di halaman publik -->
  <!-- Halaman admin/staff sudah punya info di sidebar__footer -->
  <footer class="site-footer" role="contentinfo">
    <p class="site-footer__copy">
      &copy; <?= htmlspecialchars($_year, ENT_QUOTES, 'UTF-8') ?>
      <?= APP_NAME ?>. Seluruh hak dilindungi.
    </p>
  </footer>
  <?php endif; ?>

  <!-- ═══════════════════════════════════════════════════════
       JAVASCRIPT — urutan pemuatan penting:
       1. Chart.js CDN  → harus ada sebelum admin.js/staff.js
       2. main.js       → helper global, selalu dimuat
       3. admin/staff.js → fitur spesifik role
       Semua pakai defer: tidak memblokir parsing HTML,
       dieksekusi setelah DOM selesai dibangun.
  ════════════════════════════════════════════════════════ -->

  <!--
    Chart.js v4.4.0 via cdnjs.cloudflare.com
    integrity (SRI): browser menolak file jika hash tidak cocok —
    proteksi supply-chain attack dari CDN yang dikompromis.
  -->
  <script
    src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"
    
    crossorigin="anonymous"
    referrerpolicy="no-referrer"
    defer>
  </script>

  <!-- Script global: helper DOM, flash message reader, utilitas umum -->
  <script
    src="<?= htmlspecialchars(BASE_URL . '/assets/js/main.js', ENT_QUOTES, 'UTF-8') ?>"
    defer>
  </script>

  <?php if ($_footerPageType !== 'public') : ?>
  <!-- Validasi form client-side — admin & staff -->
  <script
    src="<?= htmlspecialchars(BASE_URL . '/assets/js/validation.js', ENT_QUOTES, 'UTF-8') ?>"
    defer>
  </script>
  <?php endif; ?>

  <?php if ($_extraJs !== null) : ?>
  <!-- Script spesifik <?= htmlspecialchars($_footerPageType, ENT_QUOTES, 'UTF-8') ?> -->
  <script
    src="<?= htmlspecialchars($_extraJs, ENT_QUOTES, 'UTF-8') ?>"
    defer>
  </script>
  <?php endif; ?>

</body>
</html>