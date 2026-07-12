<?php
/**
 * EO Finance — Kumpulan fungsi helper global.
 *
 * Semua fungsi di sini bersifat pure (tidak bergantung state global)
 * kecuali yang secara eksplisit menerima $conn atau mengakses $_SESSION.
 * Ini memudahkan testing dan debugging secara terisolasi.
 *
 * PRASYARAT: config.php sudah di-include (untuk konstanta ITEMS_PER_PAGE,
 * APP_TIMEZONE, BASE_URL).
 *
 * @package EOFinance
 */

// ── Guard: blokir akses langsung via browser ──────────────────────────────────
if (!defined('EO_FINANCE_APP')) {
    http_response_code(403);
    exit('Akses langsung tidak diizinkan.');
}

// ════════════════════════════════════════════════════════════════════════════
// FORMAT & TAMPILAN
// ════════════════════════════════════════════════════════════════════════════

/**
 * Format angka desimal menjadi format mata uang Rupiah.
 *
 * Menangani nilai dari kolom DECIMAL(18,2) di MySQL — termasuk angka negatif
 * (pengeluaran melebihi anggaran) dan nilai nol.
 *
 * @param  int|float|string|null $angka  Nilai numerik yang akan diformat
 * @return string                        Contoh: "Rp 1.500.000" | "Rp 0" | "Rp -250.000"
 */
function format_rupiah(int|float|string|null $angka): string
{
    // Cast ke float terlebih dahulu — menangani nilai string dari PDO/MySQLi
    // dan nilai null dengan aman (null → 0.0)
    $nilai = (float) ($angka ?? 0);

    // number_format: 0 desimal (rupiah tidak pakai sen), ribuan dengan titik,
    // desimal dengan koma — konvensi format Indonesia
    return 'Rp ' . number_format($nilai, 0, ',', '.');
}

/**
 * Ubah string datetime menjadi representasi waktu relatif.
 *
 * Berguna untuk kolom created_at / updated_at di tabel transaksi, event, dll.
 * Menggunakan timezone APP_TIMEZONE dari config.php.
 *
 * @param  string $datetime  Datetime string format MySQL: 'YYYY-MM-DD HH:MM:SS'
 * @return string            Contoh: "baru saja", "5 menit lalu", "2 hari lalu"
 */
function time_ago(string $datetime): string
{
    $tz       = new DateTimeZone(APP_TIMEZONE);
    $sekarang = new DateTime('now', $tz);

    try {
        $waktu = new DateTime($datetime, $tz);
    } catch (Exception) {
        // Datetime string tidak valid — kembalikan string aslinya daripada error
        return htmlspecialchars($datetime, ENT_QUOTES, 'UTF-8');
    }

    // Selisih dalam detik (absolut — menangani kasus server time sedikit berbeda)
    $selisih = abs($sekarang->getTimestamp() - $waktu->getTimestamp());

    return match (true) {
        $selisih < 60                  => 'baru saja',
        $selisih < 3_600               => (int)($selisih / 60)      . ' menit lalu',
        $selisih < 86_400              => (int)($selisih / 3_600)   . ' jam lalu',
        $selisih < 604_800             => (int)($selisih / 86_400)  . ' hari lalu',
        $selisih < 2_592_000           => (int)($selisih / 604_800) . ' minggu lalu',
        $selisih < 31_536_000          => (int)($selisih / 2_592_000) . ' bulan lalu',
        default                        => (int)($selisih / 31_536_000) . ' tahun lalu',
    };
}

/**
 * Return sapaan berdasarkan jam saat ini di timezone Asia/Jakarta.
 *
 * @return string  "Selamat pagi" | "Selamat siang" | "Selamat sore" | "Selamat malam"
 */
function greeting(): string
{
    $jam = (int) (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('H');

    return match (true) {
        $jam >= 5  && $jam <= 10 => 'Selamat pagi',
        $jam >= 11 && $jam <= 14 => 'Selamat siang',
        $jam >= 15 && $jam <= 17 => 'Selamat sore',
        default                  => 'Selamat malam',
    };
}

// ════════════════════════════════════════════════════════════════════════════
// KEAMANAN & INPUT
// ════════════════════════════════════════════════════════════════════════════

/**
 * Bersihkan input string dari karakter berbahaya.
 *
 * Fungsi ini untuk keamanan OUTPUT (mencegah XSS saat nilai ditampilkan
 * ke HTML). Untuk keamanan query DB, selalu gunakan prepared statement —
 * sanitize() tidak menggantikan parameterisasi query.
 *
 * @param  mixed  $input  Nilai input mentah dari $_POST, $_GET, atau sumber lain
 * @return string         String yang sudah di-trim dan di-escape untuk output HTML
 */
function sanitize(mixed $input): string
{
    if ($input === null) {
        return '';
    }
    // trim() dulu: buang spasi di awal/akhir sebelum escape
    // htmlspecialchars() dengan ENT_QUOTES mengubah &, <, >, ", ' ke entitas HTML
    return htmlspecialchars(trim((string) $input), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate kode OTP berupa string angka acak.
 *
 * Menggunakan random_int() yang bersifat cryptographically secure (CSPRNG),
 * lebih aman daripada rand() atau mt_rand() untuk keperluan keamanan.
 * Return berupa string (bukan int) agar leading zero tidak hilang,
 * misalnya "012345" tetap 6 karakter.
 *
 * @param  int    $length  Panjang kode OTP (default: OTP_LENGTH dari config, fallback 6)
 * @return string          String angka: contoh "083741" untuk length 6
 *
 * @throws \ValueError  Jika $length kurang dari 1
 */
function generate_otp(int $length = 0): string
{
    // Gunakan konstanta OTP_LENGTH dari config.php jika length tidak dioper
    if ($length <= 0) {
        $length = defined('OTP_LENGTH') ? OTP_LENGTH : 6;
    }

    if ($length < 1) {
        throw new \ValueError('Panjang OTP minimal 1 digit.');
    }

    // Hitung batas atas berdasarkan length: length=6 → min=0, max=999999
    $min = 0;
    $max = (int) str_repeat('9', $length); // "999999" → 999999

    // str_pad() memastikan panjang selalu $length digit (padding leading zero)
    return str_pad((string) random_int($min, $max), $length, '0', STR_PAD_LEFT);
}

// ════════════════════════════════════════════════════════════════════════════
// NAVIGASI & REDIRECT
// ════════════════════════════════════════════════════════════════════════════

/**
 * Simpan pesan flash (opsional) ke sesi lalu redirect ke URL tujuan.
 *
 * Wrapper ringkas untuk pola yang sering dipakai di file proses/:
 *   set pesan → redirect → exit
 *
 * Pesan dibaca di halaman tujuan menggunakan get_flash_message() dari auth.php.
 * Pastikan auth.php sudah di-include sebelum memanggil redirect() agar
 * set_flash_message() tersedia; jika tidak, pesan disimpan langsung ke sesi.
 *
 * @param  string      $url      URL tujuan redirect (gunakan BASE_URL . '/path')
 * @param  string|null $message  Teks pesan yang akan ditampilkan, null = tanpa pesan
 * @param  string      $type     Tipe pesan: 'error' | 'success' | 'warning' | 'info'
 * @return never                 Fungsi ini selalu diakhiri exit()
 */
function redirect(string $url, ?string $message = null, string $type = 'error'): never
{
    if ($message !== null && $message !== '') {
        // Gunakan set_flash_message() dari auth.php jika sudah ter-include,
        // atau tulis langsung ke $_SESSION sebagai fallback
        if (function_exists('set_flash_message')) {
            set_flash_message($type, $message);
        } else {
            $_SESSION['__flash'][$type] = $message;
        }
    }

    header('Location: ' . $url);
    exit();
}

/**
 * Generate HTML paginasi: tombol Prev, nomor halaman, dan tombol Next.
 *
 * URL setiap halaman dibangun dari $base_url dengan query param `?page=N`.
 * Jika $base_url sudah mengandung query string lain, gunakan '&page=N'
 * — fungsi ini mendeteksinya secara otomatis.
 *
 * Contoh output (disederhanakan):
 *   « Sebelumnya  1  2  [3]  4  5  Berikutnya »
 *
 * @param  int    $total_data    Total baris data keseluruhan (dari COUNT(*))
 * @param  string $base_url      URL dasar halaman, contoh: BASE_URL . '/admin/events/index.php'
 * @param  int    $current_page  Halaman aktif saat ini (dari $_GET['page'] ?? 1)
 * @param  int    $per_page      Jumlah item per halaman (default: ITEMS_PER_PAGE dari config)
 * @return string                HTML string siap di-echo, kosong jika data muat 1 halaman
 */
function pagination(
    int    $total_data,
    string $base_url,
    int    $current_page = 1,
    int    $per_page     = 0
): string {
    // Gunakan konstanta ITEMS_PER_PAGE jika per_page tidak dioper
    if ($per_page <= 0) {
        $per_page = defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 10;
    }

    // Tidak perlu paginasi jika data muat dalam satu halaman
    if ($total_data <= $per_page) {
        return '';
    }

    $total_halaman  = (int) ceil($total_data / $per_page);
    // Pastikan current_page tidak keluar dari batas valid
    $current_page   = max(1, min($current_page, $total_halaman));

    // Deteksi pemisah query string: '?' jika belum ada, '&' jika sudah ada
    $separator = str_contains($base_url, '?') ? '&' : '?';

    // ── Bangun URL helper ─────────────────────────────────────────────────────
    $page_url = fn(int $page): string =>
        htmlspecialchars($base_url . $separator . 'page=' . $page, ENT_QUOTES, 'UTF-8');

    // ── Hitung rentang nomor halaman yang ditampilkan ─────────────────────────
    // Tampilkan maksimal 5 nomor di sekitar halaman aktif (windowed pagination)
    // agar tidak membanjiri UI saat total halaman sangat banyak.
    $window      = 2; // jumlah halaman di kiri dan kanan halaman aktif
    $range_start = max(1, $current_page - $window);
    $range_end   = min($total_halaman, $current_page + $window);

    // Geser window jika terlalu dekat ke ujung agar selalu tampil 5 nomor
    if ($range_end - $range_start < ($window * 2)) {
        if ($range_start === 1) {
            $range_end = min($total_halaman, $range_start + ($window * 2));
        } else {
            $range_start = max(1, $range_end - ($window * 2));
        }
    }

    // ── Mulai build HTML ──────────────────────────────────────────────────────
    $html  = '<nav class="pagination" aria-label="Navigasi halaman">';
    $html .= '<ul class="pagination__list">';

    // Tombol Sebelumnya
    if ($current_page > 1) {
        $html .= '<li class="pagination__item">'
               . '<a href="' . $page_url($current_page - 1) . '" '
               . 'class="pagination__link pagination__link--prev" '
               . 'aria-label="Halaman sebelumnya">'
               . '&laquo; Sebelumnya'
               . '</a></li>';
    } else {
        $html .= '<li class="pagination__item pagination__item--disabled">'
               . '<span class="pagination__link pagination__link--prev" '
               . 'aria-disabled="true">&laquo; Sebelumnya</span></li>';
    }

    // Titik-titik di awal jika window tidak mulai dari halaman 1
    if ($range_start > 1) {
        $html .= '<li class="pagination__item">'
               . '<a href="' . $page_url(1) . '" class="pagination__link">1</a></li>';
        if ($range_start > 2) {
            $html .= '<li class="pagination__item pagination__item--ellipsis">'
                   . '<span class="pagination__ellipsis">&hellip;</span></li>';
        }
    }

    // Nomor halaman dalam window
    for ($i = $range_start; $i <= $range_end; $i++) {
        if ($i === $current_page) {
            $html .= '<li class="pagination__item pagination__item--active">'
                   . '<span class="pagination__link" aria-current="page">' . $i . '</span></li>';
        } else {
            $html .= '<li class="pagination__item">'
                   . '<a href="' . $page_url($i) . '" class="pagination__link">' . $i . '</a></li>';
        }
    }

    // Titik-titik di akhir jika window tidak mencapai halaman terakhir
    if ($range_end < $total_halaman) {
        if ($range_end < $total_halaman - 1) {
            $html .= '<li class="pagination__item pagination__item--ellipsis">'
                   . '<span class="pagination__ellipsis">&hellip;</span></li>';
        }
        $html .= '<li class="pagination__item">'
               . '<a href="' . $page_url($total_halaman) . '" class="pagination__link">'
               . $total_halaman . '</a></li>';
    }

    // Tombol Berikutnya
    if ($current_page < $total_halaman) {
        $html .= '<li class="pagination__item">'
               . '<a href="' . $page_url($current_page + 1) . '" '
               . 'class="pagination__link pagination__link--next" '
               . 'aria-label="Halaman berikutnya">'
               . 'Berikutnya &raquo;'
               . '</a></li>';
    } else {
        $html .= '<li class="pagination__item pagination__item--disabled">'
               . '<span class="pagination__link pagination__link--next" '
               . 'aria-disabled="true">Berikutnya &raquo;</span></li>';
    }

    $html .= '</ul>';

    // Ringkasan: "Menampilkan 11–20 dari 47 data"
    $dari  = (($current_page - 1) * $per_page) + 1;
    $sampai = min($current_page * $per_page, $total_data);
    $html .= '<p class="pagination__summary">'
           . 'Menampilkan ' . $dari . '–' . $sampai . ' dari ' . $total_data . ' data'
           . '</p>';

    $html .= '</nav>';

    return $html;
}

// ════════════════════════════════════════════════════════════════════════════
// APPROVAL TRANSAKSI
// ════════════════════════════════════════════════════════════════════════════

/**
 * Render badge HTML kecil untuk status approval transaksi.
 *
 * Dipakai di semua tabel/daftar transaksi (admin & staff) supaya tampilan
 * status konsisten di seluruh aplikasi.
 *
 * @param  string $status  'pending' | 'approved' | 'rejected'
 * @return string          HTML <span> badge, sudah aman untuk di-echo langsung
 */
function badge_status_approval(string $status): string
{
    [$label, $kelas] = match ($status) {
        'approved' => ['Disetujui', 'badge--approved'],
        'rejected' => ['Ditolak',   'badge--rejected'],
        default    => ['Menunggu Persetujuan', 'badge--pending'],
    };

    return '<span class="badge ' . $kelas . '">' . $label . '</span>';
}

/**
 * Render badge HTML kecil untuk status pembayaran transaksi (piutang/hutang).
 *
 * Hanya relevan ditampilkan saat 'belum_lunas' — transaksi 'lunas' adalah
 * kondisi normal/default sehingga biasanya tidak perlu badge ekstra di UI
 * (panggil hanya saat $status === 'belum_lunas' di halaman pemanggil).
 *
 * @param  string $status  'lunas' | 'belum_lunas'
 * @param  string $tipe    'pemasukan' | 'pengeluaran' — menentukan label (Piutang/Hutang)
 * @return string          HTML <span> badge, aman untuk di-echo langsung
 */
function badge_status_pembayaran(string $status, string $tipe = ''): string
{
    if ($status !== 'belum_lunas') {
        return '<span class="badge badge--lunas">Lunas</span>';
    }

    $label = $tipe === 'pemasukan' ? 'Piutang' : ($tipe === 'pengeluaran' ? 'Hutang' : 'Belum Lunas');

    return '<span class="badge badge--belum-lunas">' . $label . '</span>';
}

// ════════════════════════════════════════════════════════════════════════════
// NOTIFIKASI IN-APP
// ════════════════════════════════════════════════════════════════════════════

/**
 * Kirim satu notifikasi in-app ke seorang user.
 *
 * Seperti catat_audit(), fungsi ini TIDAK boleh menggagalkan aksi utama
 * kalau pencatatan notifikasi gagal (mis. tabel belum ada saat migrasi
 * belum dijalankan). Error cukup dicatat ke error_log.
 *
 * @param mysqli   $conn
 * @param int      $eoId
 * @param int      $userId   ID penerima notifikasi
 * @param string   $tipe     transaksi_baru | transaksi_approved | transaksi_rejected | event_dekat | staff_baru
 * @param string   $judul    Judul singkat
 * @param string   $pesan    Detail pesan
 * @param string|null $link  URL tujuan (relatif dari BASE_URL)
 */
function kirim_notifikasi(
    mysqli $conn,
    int $eoId,
    int $userId,
    string $tipe,
    string $judul,
    string $pesan,
    ?string $link = null
): void {
    try {
        $stmt = $conn->prepare(
            "INSERT INTO notifikasi (eo_id, user_id, tipe, judul, pesan, link)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('iissss', $eoId, $userId, $tipe, $judul, $pesan, $link);
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $e) {
        error_log('[notifikasi] gagal kirim: ' . $e->getMessage());
    }
}

/**
 * Kirim notifikasi ke semua admin_eo dalam satu EO.
 *
 * Berguna untuk notifikasi seperti "ada transaksi baru dari staff"
 * di mana semua admin perlu tahu.
 */
function kirim_notifikasi_ke_semua_admin(
    mysqli $conn,
    int $eoId,
    string $tipe,
    string $judul,
    string $pesan,
    ?string $link = null,
    int $kecuali_user_id = 0   // skip admin ini (mis. yang baru saja bertindak)
): void {
    try {
        $stmt = $conn->prepare(
            "SELECT id FROM users WHERE eo_id = ? AND role = 'admin_eo' AND is_active = 1"
        );
        $stmt->bind_param('i', $eoId);
        $stmt->execute();
        $admins = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($admins as $admin) {
            if ($kecuali_user_id > 0 && (int)$admin['id'] === $kecuali_user_id) continue;
            kirim_notifikasi($conn, $eoId, (int)$admin['id'], $tipe, $judul, $pesan, $link);
        }
    } catch (\Throwable $e) {
        error_log('[notifikasi] gagal kirim ke admin: ' . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════════════
// AUDIT LOG
// ════════════════════════════════════════════════════════════════════════════

/**
 * Catat satu entri audit log.
 *
 * Dipakai di seluruh file proses-*.php untuk mencatat siapa melakukan apa,
 * kapan, terhadap data apa — penting untuk aplikasi finansial multi-staff.
 *
 * CATATAN DESAIN: tipe parameter bind_param dibangun otomatis dari nilai
 * variabelnya (bukan string literal tangan seperti 'iisssisss'), supaya
 * tidak berisiko bug "tipe tertukar" seperti yang pernah ditemukan di
 * proses/staff/proses-transaksi.php pada iterasi sebelumnya.
 *
 * @param mysqli      $conn      Koneksi database aktif
 * @param int         $eoId      ID EO (tenant)
 * @param int         $userId    ID user yang melakukan aksi
 * @param string      $namaUser  Nama user (snapshot, supaya log tetap terbaca
 *                                walau akun user kelak diubah/dihapus)
 * @param string      $aksi      'created' | 'updated' | 'deleted' | 'approved'
 *                                | 'rejected' | 'lunas' | dst (bebas, singkat)
 * @param string      $entitas   Nama tabel/entitas: 'transaksi', 'events',
 *                                'users', 'klien', 'vendor', 'penugasan'
 * @param int|null    $entitasId ID baris yang terdampak (boleh null)
 * @param string      $deskripsi Ringkasan singkat & mudah dibaca manusia
 * @param array|null  $dataLama  Snapshot data sebelum perubahan (opsional)
 * @param array|null  $dataBaru  Snapshot data sesudah perubahan (opsional)
 * @return void
 */
function catat_audit(
    mysqli $conn,
    int $eoId,
    int $userId,
    string $namaUser,
    string $aksi,
    string $entitas,
    ?int $entitasId,
    string $deskripsi,
    ?array $dataLama = null,
    ?array $dataBaru = null
): void {
    try {
        $jsonLama = $dataLama !== null ? json_encode($dataLama, JSON_UNESCAPED_UNICODE) : null;
        $jsonBaru = $dataBaru !== null ? json_encode($dataBaru, JSON_UNESCAPED_UNICODE) : null;
        $ip       = $_SERVER['REMOTE_ADDR'] ?? null;

        $params = [$eoId, $userId, $namaUser, $aksi, $entitas, $entitasId, $deskripsi, $jsonLama, $jsonBaru, $ip];

        // Bangun type string otomatis dari tipe nilai aktual — lihat catatan desain di atas.
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : 's'; // NULL & string sama-sama aman dikirim sebagai 's'
        }

        $stmt = $conn->prepare(
            "INSERT INTO audit_log
                (eo_id, user_id, nama_user, aksi, entitas, entitas_id, deskripsi,
                 data_lama, data_baru, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $e) {
        // Audit log TIDAK BOLEH menggagalkan aksi utama (mis. transaksi tetap
        // harus tersimpan walau pencatatan log-nya gagal karena sebab apa pun).
        // Cukup catat ke error_log server, jangan hentikan alur aplikasi.
        error_log('[audit_log] gagal mencatat: ' . $e->getMessage());
    }
}

// ════════════════════════════════════════════════════════════════════════════
// KEUANGAN & BISNIS
// ════════════════════════════════════════════════════════════════════════════

/**
 * Hitung sisa anggaran sebuah event beserta status visualnya.
 *
 * Melakukan dua query:
 *  1. Ambil nilai anggaran event dari tabel events.
 *  2. SUM pengeluaran dari tabel transaksi untuk event tersebut.
 *
 * Filter eo_id disertakan di kedua query sebagai lapisan keamanan
 * multi-tenant — memastikan admin tidak bisa mengintip data EO lain
 * meski mereka tahu event_id-nya (insecure direct object reference).
 *
 * @param  int    $event_id  ID event yang ingin dihitung anggarannya
 * @param  mysqli $conn      Koneksi MySQLi aktif (biasanya $mysqli dari database.php)
 * @param  int    $eo_id     ID EO pemilik event — untuk validasi kepemilikan.
 *                           Jika 0, diambil dari $_SESSION['eo_id'] (fallback).
 * @return array{
 *   anggaran:           float,
 *   total_pengeluaran:  float,
 *   sisa:               float,
 *   persentase_terpakai: float,
 *   status_warna:       string,
 *   formatted: array{
 *     anggaran:          string,
 *     total_pengeluaran: string,
 *     sisa:              string,
 *   }
 * }
 */
function hitung_sisa_anggaran(int $event_id, mysqli $conn, int $eo_id = 0): array
{
    // Fallback ke sesi jika eo_id tidak dioper — kemudahan pemanggilan
    if ($eo_id <= 0) {
        $eo_id = (int) ($_SESSION['eo_id'] ?? 0);
    }

    // ── Query 1: ambil anggaran event ─────────────────────────────────────────
    // Filter eo_id memastikan event benar-benar milik EO yang sedang login
    $stmtEvent = $conn->prepare(
        'SELECT anggaran FROM events WHERE id = ? AND eo_id = ? LIMIT 1'
    );
    $stmtEvent->bind_param('ii', $event_id, $eo_id);
    $stmtEvent->execute();
    $resultEvent = $stmtEvent->get_result();
    $event       = $resultEvent->fetch_assoc();
    $stmtEvent->close();

    // Event tidak ditemukan atau bukan milik EO ini
    $anggaran = (float) ($event['anggaran'] ?? 0);

    // ── Query 2: SUM semua pengeluaran event ──────────────────────────────────
    // COALESCE mengembalikan 0 jika belum ada transaksi (SUM NULL → 0)
    // Hanya transaksi berstatus 'approved' yang dihitung — transaksi staff
    // yang masih 'pending' belum final dan tidak boleh memotong anggaran dulu,
    // dan yang 'rejected' memang batal.
    $stmtTrx = $conn->prepare(
        "SELECT COALESCE(SUM(jumlah), 0) AS total_pengeluaran
         FROM transaksi
         WHERE event_id = ? AND eo_id = ? AND tipe = ? AND status_approval = 'approved'"
    );
    $tipePengeluaran = 'pengeluaran';
    $stmtTrx->bind_param('iis', $event_id, $eo_id, $tipePengeluaran);
    $stmtTrx->execute();
    $resultTrx       = $stmtTrx->get_result();
    $totalPengeluaran = (float) ($resultTrx->fetch_assoc()['total_pengeluaran'] ?? 0);
    $stmtTrx->close();

    // ── Hitung sisa dan persentase ────────────────────────────────────────────
    $sisa = $anggaran - $totalPengeluaran;

    // Hindari division by zero jika anggaran belum diset (0)
    $persentaseTerpakai = $anggaran > 0
        ? round(($totalPengeluaran / $anggaran) * 100, 2)
        : 0.0;

    $persentaseSisa = 100 - $persentaseTerpakai;

    // ── Tentukan status warna untuk indikator UI ──────────────────────────────
    // Hijau  : sisa > 30% anggaran — kondisi aman
    // Kuning : sisa 10–30%          — perlu perhatian
    // Merah  : sisa < 10%           — hampir habis atau sudah minus
    $statusWarna = match (true) {
        $persentaseSisa > 30 => 'hijau',
        $persentaseSisa >= 10 => 'kuning',
        default               => 'merah',
    };

    return [
        'anggaran'            => $anggaran,
        'total_pengeluaran'   => $totalPengeluaran,
        'sisa'                => $sisa,
        'persentase_terpakai' => $persentaseTerpakai,
        'status_warna'        => $statusWarna,
        // Versi terformat siap tampil — tidak perlu panggil format_rupiah() lagi
        'formatted'           => [
            'anggaran'          => format_rupiah($anggaran),
            'total_pengeluaran' => format_rupiah($totalPengeluaran),
            'sisa'              => format_rupiah($sisa),
        ],
    ];
}