<?php
/**
 * EO Finance — Guard dan helper fungsi autentikasi & otorisasi.
 *
 * File ini TIDAK men-include config apapun secara mandiri.
 * Pastikan header.php (atau ketiga config) sudah di-include sebelumnya
 * karena fungsi-fungsi di sini bergantung pada sesi yang sudah aktif.
 *
 * CARA PAKAI — di setiap halaman yang butuh proteksi:
 *
 *   // Halaman admin:
 *   require_once __DIR__ . '/../../includes/auth.php';
 *   require_login();
 *   require_role('admin_eo');
 *
 *   // Halaman staff:
 *   require_once __DIR__ . '/../../includes/auth.php';
 *   require_login();
 *   require_role('staff_eo');
 *
 *   // Halaman yang hanya butuh login (tanpa cek role):
 *   require_once __DIR__ . '/../../includes/auth.php';
 *   require_login();
 *
 * @package EOFinance
 */

// ── Guard: blokir akses langsung via browser ──────────────────────────────────
if (!defined('EO_FINANCE_APP')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

// ── Pastikan sesi sudah aktif sebelum fungsi-fungsi ini dipakai ───────────────
// session_status() === PHP_SESSION_NONE berarti session_start() belum dipanggil.
// Ini jaring pengaman — normalnya session.php sudah di-include via header.php.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ════════════════════════════════════════════════════════════════════════════
// HELPER INTERNAL
// ════════════════════════════════════════════════════════════════════════════

/**
 * Simpan pesan flash ke sesi untuk ditampilkan sekali di halaman berikutnya.
 *
 * Digunakan secara internal oleh require_login() dan require_role() untuk
 * membawa pesan error melewati redirect tanpa menyimpannya di URL
 * (menyimpan pesan di URL berisiko: bisa di-bookmark atau di-log server).
 *
 * Pesan dibaca dan langsung dihapus oleh get_flash_message() setelah ditampilkan
 * sehingga tidak muncul dua kali jika halaman di-refresh.
 *
 * @param  string $type     Tipe pesan: 'error' | 'success' | 'warning' | 'info'
 * @param  string $message  Teks pesan yang akan ditampilkan
 * @return void
 */
function set_flash_message(string $type, string $message): void
{
    $_SESSION['__flash'][$type] = $message;
}

/**
 * Ambil dan hapus pesan flash dari sesi.
 *
 * Setelah fungsi ini dipanggil, pesan tidak ada lagi di sesi —
 * memastikan pesan hanya tampil satu kali (one-time display).
 *
 * @param  string      $type  Tipe pesan yang ingin diambil
 * @return string|null        Teks pesan, atau null jika tidak ada
 */
function get_flash_message(string $type): ?string
{
    if (isset($_SESSION['__flash'][$type])) {
        $message = $_SESSION['__flash'][$type];
        unset($_SESSION['__flash'][$type]);
        return $message;
    }
    return null;
}

/**
 * Redirect ke URL lain dan hentikan eksekusi skrip.
 *
 * Wrapper sederhana di atas header('Location: ...') dengan exit() eksplisit.
 * exit() wajib dipanggil setelah header redirect — tanpa itu, PHP melanjutkan
 * eksekusi sisa skrip meski browser sudah diarahkan ke halaman lain,
 * yang bisa menyebabkan kebocoran data atau logika yang tidak diinginkan.
 *
 * @param  string $url  URL tujuan redirect (absolut atau relatif)
 * @return never        Fungsi ini tidak pernah kembali — selalu diakhiri exit()
 */
function _auth_redirect(string $url): never
{
    header('Location: ' . $url);
    exit();
}

// ════════════════════════════════════════════════════════════════════════════
// FUNGSI GUARD — PROTEKSI HALAMAN
// ════════════════════════════════════════════════════════════════════════════

/**
 * Pastikan pengguna sudah login sebelum mengakses halaman.
 *
 * Cek keberadaan $_SESSION['user_id']. Jika tidak ada (belum login atau
 * sesi kadaluarsa), simpan pesan error ke flash session dan redirect ke login.
 *
 * Letakkan panggilan ini di baris PERTAMA setiap halaman yang butuh login,
 * sebelum query DB atau output HTML apapun dijalankan.
 *
 * @return void
 */
function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        // Simpan URL yang sedang dikunjungi agar setelah login bisa redirect balik
        // ke halaman yang dimaksud (intended URL pattern).
        // Hanya simpan jika request method GET — POST tidak bisa di-redirect ulang.
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $_SESSION['__intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        }

        set_flash_message('error', 'Silakan login terlebih dahulu.');
        _auth_redirect(BASE_URL . '/login.php');
    }
}

/**
 * Pastikan pengguna memiliki role yang sesuai untuk mengakses halaman.
 *
 * Harus dipanggil SETELAH require_login() — jika pengguna belum login,
 * require_login() sudah menangani redirect sehingga fungsi ini tidak akan
 * dicapai. Jika role tidak cocok, arahkan ke halaman 403.
 *
 * @param  string $expectedRole  Role yang diizinkan: 'admin_eo' atau 'staff_eo'
 * @return void
 */
function require_role(string $expectedRole): void
{
    // Validasi nilai role yang dioper — tangkap typo di awal development
    $validRoles = ['admin_eo', 'staff_eo'];
    if (!in_array($expectedRole, $validRoles, strict: true)) {
        // Ini adalah bug developer, bukan kesalahan user — log dan hentikan
        error_log(
            '[EO Finance] require_role() dipanggil dengan role tidak valid: '
            . htmlspecialchars($expectedRole, ENT_QUOTES, 'UTF-8')
        );
        http_response_code(500);
        exit('Konfigurasi role tidak valid. Hubungi administrator.');
    }

    $currentRole = $_SESSION['role'] ?? null;

    if ($currentRole !== $expectedRole) {
        // Catat percobaan akses tidak sah ke log server untuk monitoring
        error_log(
            '[EO Finance] Akses ditolak — user_id: ' . ($_SESSION['user_id'] ?? 'guest') .
            ', role: ' . ($currentRole ?? 'none') .
            ', butuh: ' . $expectedRole .
            ', URL: ' . ($_SERVER['REQUEST_URI'] ?? '')
        );

        _auth_redirect(BASE_URL . '/errors/403.php');
    }
}

// ════════════════════════════════════════════════════════════════════════════
// FUNGSI GETTER — AKSES DATA SESI
// ════════════════════════════════════════════════════════════════════════════

/**
 * Ambil ID pengguna yang sedang login.
 *
 * Lebih aman daripada akses langsung ke $_SESSION['user_id'] karena
 * mengembalikan null secara eksplisit alih-alih Undefined index notice.
 * Return type int|null memaksa pemanggil menangani kemungkinan null.
 *
 * @return int|null  ID pengguna, atau null jika belum login
 */
function get_current_user_id(): ?int
{
    $id = $_SESSION['user_id'] ?? null;
    // Cast ke int untuk memastikan nilainya selalu integer bersih,
    // bukan string "5" yang bisa menyebabkan perbandingan tipe yang tidak terduga.
    return $id !== null ? (int) $id : null;
}

/**
 * Ambil ID EO milik pengguna yang sedang login.
 *
 * eo_id dipakai sebagai filter tenant di setiap query DB —
 * ini adalah kunci isolasi data antar EO di sistem multi-tenant ini.
 * Selalu sertakan eo_id dalam klausa WHERE query yang menyentuh data EO.
 *
 * @return int|null  ID EO, atau null jika belum login / sesi tidak valid
 */
function get_current_eo_id(): ?int
{
    $eoId = $_SESSION['eo_id'] ?? null;
    return $eoId !== null ? (int) $eoId : null;
}

/**
 * Ambil role pengguna yang sedang login.
 *
 * @return string|null  'admin_eo' | 'staff_eo' | null jika belum login
 */
function get_current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * Cek apakah pengguna yang sedang login adalah admin_eo.
 *
 * Berguna untuk conditional rendering di template — menampilkan atau
 * menyembunyikan elemen UI tertentu tanpa harus cek string role secara manual.
 *
 * Contoh penggunaan di template:
 *   <?php if (is_admin()) : ?>
 *     <a href="...">Kelola Staff</a>
 *   <?php endif; ?>
 *
 * @return bool  true jika admin_eo, false untuk semua kondisi lainnya
 */
function is_admin(): bool
{
    return ($_SESSION['role'] ?? null) === 'admin_eo';
}

/**
 * Cek apakah pengguna yang sedang login adalah staff_eo.
 *
 * @return bool  true jika staff_eo, false untuk semua kondisi lainnya
 */
function is_staff(): bool
{
    return ($_SESSION['role'] ?? null) === 'staff_eo';
}

/**
 * Cek apakah ada pengguna yang sedang login (tanpa redirect).
 *
 * Berbeda dengan require_login() yang langsung redirect jika belum login,
 * fungsi ini hanya mengembalikan boolean — cocok untuk conditional rendering
 * di halaman publik yang bisa diakses baik oleh tamu maupun pengguna login.
 *
 * @return bool  true jika ada sesi login aktif
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}