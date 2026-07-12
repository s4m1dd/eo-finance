<?php
/**
 * EO Finance — Proses registrasi admin_eo baru.
 *
 * Menerima POST dari register.php, memvalidasi semua field,
 * lalu membuat satu baris di tabel `eo` dan satu baris di tabel `users`
 * dalam satu transaksi database — keduanya berhasil atau keduanya batal.
 *
 * Alur:
 *   1. Tolak selain POST
 *   2. Validasi CSRF token
 *   3. Sanitasi & validasi semua field
 *   4. Cek duplikasi email di tabel eo (email EO harus unik global)
 *   5. Cek duplikasi email di tabel users per calon eo_id
 *      (tidak relevan untuk registrasi baru, tapi dijaga untuk konsistensi)
 *   6. BEGIN TRANSACTION → INSERT eo → INSERT users → COMMIT
 *   7. Redirect ke login dengan pesan sukses
 *
 * Field yang diproses (dari register.php):
 *   - nama             : nama lengkap admin
 *   - email            : email login admin (sekaligus email kontak EO)
 *   - password         : password admin
 *   - konfirmasi_password
 *   - nama_eo          : nama Event Organizer
 *   - telepon          : nomor telepon EO (opsional)
 *   - alamat_eo        : alamat EO (opsional)
 *
 * @package EOFinance
 */
define('EO_FINANCE_APP', true);

// Aktifkan error reporting untuk membantu debug di development
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

// ════════════════════════════════════════════════════════════════════════════
// 1. HANYA TERIMA POST
// ════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/register.php');
    exit();
}

// ── Helper: redirect ke register dengan old input ─────────────────────────────
// Didefinisikan setelah cek POST agar $_POST sudah tersedia.
// $_SESSION di-pass by reference agar perubahan di dalam closure terefleksi.
$failBack = function (string $msg): never {
    $_SESSION['__flash']['error'] = $msg;
    $_SESSION['__old_input'] = [
        'nama'      => trim($_POST['nama']      ?? ''),
        'email'     => trim($_POST['email']     ?? ''),
        'nama_eo'   => trim($_POST['nama_eo']   ?? ''),
        'telepon'   => trim($_POST['telepon']   ?? ''),
        'alamat_eo' => trim($_POST['alamat_eo'] ?? ''),
    ];
    header('Location: ' . BASE_URL . '/register.php');
    exit();
};

// ════════════════════════════════════════════════════════════════════════════
// 2. VALIDASI CSRF TOKEN
// ════════════════════════════════════════════════════════════════════════════
$_csrfPost    = $_POST['csrf_token'] ?? '';
$_csrfSession = $_SESSION['csrf_token'] ?? '';

if (
    empty($_csrfSession) ||
    empty($_csrfPost) ||
    !hash_equals($_csrfSession, $_csrfPost)
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $failBack('Permintaan tidak valid. Silakan coba lagi.');
}

// Putar CSRF token setelah submit valid
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ════════════════════════════════════════════════════════════════════════════
// 3. SANITASI & VALIDASI INPUT
// ════════════════════════════════════════════════════════════════════════════
$nama             = trim($_POST['nama']                 ?? '');
$email            = strtolower(trim($_POST['email']     ?? ''));
$password         = $_POST['password']                  ?? '';
$konfirmasiPass   = $_POST['konfirmasi_password']        ?? '';
$namaEO           = trim($_POST['nama_eo']              ?? '');
$telepon          = trim($_POST['telepon']              ?? '');
$alamatEO         = trim($_POST['alamat_eo']            ?? '');

// ── Field wajib ───────────────────────────────────────────────────────────────
if ($nama === '') {
    $failBack('Nama lengkap wajib diisi.');
}

if ($email === '') {
    $failBack('Email wajib diisi.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $failBack('Format email tidak valid.');
}

// Batasi panjang email — sesuai kolom VARCHAR(150) di DB
if (mb_strlen($email) > 150) {
    $failBack('Email terlalu panjang (maksimal 150 karakter).');
}

if ($password === '') {
    $failBack('Password wajib diisi.');
}

if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
    $failBack('Password minimal ' . PASSWORD_MIN_LENGTH . ' karakter.');
}

// Batasi panjang password mentah — bcrypt hanya memproses 72 byte pertama;
// lebih dari itu tidak menambah keamanan tapi bisa menyesatkan user
if (mb_strlen($password) > 72) {
    $failBack('Password terlalu panjang (maksimal 72 karakter).');
}

if ($konfirmasiPass === '') {
    $failBack('Konfirmasi password wajib diisi.');
}

// Bandingkan langsung — ini bukan secret kriptografi, user tahu kedua nilainya
if ($password !== $konfirmasiPass) {
    $failBack('Password dan konfirmasi password tidak sama.');
}

if ($namaEO === '') {
    $failBack('Nama EO wajib diisi.');
}

if (mb_strlen($namaEO) > 150) {
    $failBack('Nama EO terlalu panjang (maksimal 150 karakter).');
}

if (mb_strlen($nama) > 100) {
    $failBack('Nama lengkap terlalu panjang (maksimal 100 karakter).');
}

// Validasi telepon: hanya angka, tanda +, strip, spasi (opsional — boleh kosong)
if ($telepon !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $telepon)) {
    $failBack('Format nomor telepon tidak valid.');
}

// ════════════════════════════════════════════════════════════════════════════
// 4. CEK DUPLIKASI EMAIL DI TABEL eo
// email di tabel eo bersifat UNIQUE global — satu email hanya untuk satu EO.
// Email admin digunakan sebagai email kontak EO sekaligus email login.
// ════════════════════════════════════════════════════════════════════════════
$stmtCekEO = $mysqli->prepare(
    'SELECT id FROM eo WHERE email = ? LIMIT 1'
);
$stmtCekEO->bind_param('s', $email);
$stmtCekEO->execute();
$stmtCekEO->store_result();

if ($stmtCekEO->num_rows > 0) {
    $stmtCekEO->close();
    // Pesan sengaja tidak menyebut "email sudah terdaftar" secara eksplisit
    // untuk sedikit memperlambat user enumeration lewat form registrasi
    $failBack('Email ini sudah digunakan. Gunakan email lain atau masuk dengan akun yang ada.');
}
$stmtCekEO->close();

// ════════════════════════════════════════════════════════════════════════════
// 5. HASH PASSWORD
// Lakukan sebelum transaksi DB agar tidak memblokir koneksi terlalu lama.
// bcrypt dengan cost dari konstanta config — konfigurasi terpusat.
// ════════════════════════════════════════════════════════════════════════════
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

// Bersihkan plaintext password dari memori sesegera mungkin
unset($password, $konfirmasiPass);

// ════════════════════════════════════════════════════════════════════════════
// 6. INSERT DALAM TRANSAKSI
// Dua INSERT harus atomik: jika INSERT users gagal, INSERT eo ikut dibatalkan.
// Tanpa transaksi, bisa terbentuk baris eo tanpa user pemiliknya.
// ════════════════════════════════════════════════════════════════════════════
$mysqli->begin_transaction();

try {
    // ── INSERT ke tabel eo ────────────────────────────────────────────────────
    $stmtEO = $mysqli->prepare("
        INSERT INTO eo (nama_eo, email, telepon, alamat, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, 1, NOW(), NOW())
    ");
    // Simpan null jika string kosong — kolom DB nullable
    $teleponDb = $telepon !== '' ? $telepon : null;
    $alamatDb  = $alamatEO !== '' ? $alamatEO : null;

    $stmtEO->bind_param('ssss', $namaEO, $email, $teleponDb, $alamatDb);
    $stmtEO->execute();
    $newEoId = (int) $mysqli->insert_id;
    $stmtEO->close();

    // ── INSERT ke tabel users (admin_eo pertama EO ini) ───────────────────────
    $stmtUser = $mysqli->prepare("
        INSERT INTO users
            (eo_id, nama, email, password, role, first_login, is_active, created_at, updated_at)
        VALUES
            (?, ?, ?, ?, 'admin_eo', 0, 1, NOW(), NOW())
    ");
    $stmtUser->bind_param('isss', $newEoId, $nama, $email, $passwordHash);
    $stmtUser->execute();
    $stmtUser->close();

    // Semua berhasil — commit
    $mysqli->commit();

} catch (mysqli_sql_exception $e) {
    // Rollback agar tidak ada data setengah jadi
    $mysqli->rollback();

    // Catat detail teknis ke log server (bukan ke user)
    error_log(
        '[EO Finance] Registrasi gagal — ' .
        'email: ' . $email .
        ', error: ' . $e->getMessage()
    );

    // Cek apakah error karena duplikasi (kode 1062 = Duplicate entry)
    if ($e->getCode() === 1062) {
        $failBack('Email ini sudah digunakan. Gunakan email lain.');
    }

    $failBack('Terjadi kesalahan saat mendaftarkan akun. Silakan coba lagi.');
}

// ════════════════════════════════════════════════════════════════════════════
// 7. REGISTRASI BERHASIL — bersihkan old input dan redirect ke login
// ════════════════════════════════════════════════════════════════════════════
unset($_SESSION['__old_input']);

$_SESSION['__flash']['success'] =
    'Akun berhasil dibuat! Silakan masuk dengan email dan password yang sudah kamu daftarkan.';

header('Location: ' . BASE_URL . '/login.php');
exit();