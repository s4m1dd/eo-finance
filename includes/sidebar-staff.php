<?php
/**
 * includes/sidebar-staff.php
 *
 * Sidebar navigasi untuk role: staff_eo.
 * Staff hanya melihat event yang ditugaskan kepadanya (tabel penugasan).
 *
 * CSS  : assets/css/staff.css  (#sidebar-staff, .sidebar-event-item, dll)
 * JS   : assets/js/main.js     (openStaffSidebar / closeStaffSidebar)
 *
 * Gunakan dengan cara include di setiap halaman staff:
 *   <?php include __DIR__ . '/../includes/sidebar-staff.php'; ?>
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Guard: redirect jika tidak login atau bukan staff.
// Sistem auth (proses-login.php) menyimpan sesi secara FLAT:
//   $_SESSION['user_id'], $_SESSION['role'], $_SESSION['nama'],
//   $_SESSION['email'], $_SESSION['eo_id'], $_SESSION['nama_eo']
// Sidebar ini kompatibel dengan format flat tersebut.
$_sesiRole = $_SESSION['role'] ?? '';

if (empty($_SESSION['user_id']) || $_sesiRole !== 'staff_eo') {
    header('Location: ' . (defined('BASE_URL') ? BASE_URL : '') . '/login.php');
    exit;
}

// Bangun array $currentUser dan $currentEo dari sesi flat
$currentUser = [
    'id'    => $_SESSION['user_id'] ?? null,
    'nama'  => $_SESSION['nama']    ?? '',
    'email' => $_SESSION['email']   ?? '',
    'role'  => $_SESSION['role']    ?? '',
    'eo_id' => $_SESSION['eo_id']   ?? null,
];

$currentEo = [
    'id'      => $_SESSION['eo_id']   ?? null,
    'nama_eo' => $_SESSION['nama_eo'] ?? 'EO Finance',
    'logo'    => $_SESSION['logo_eo'] ?? null,
];

$currentScript = basename($_SERVER['PHP_SELF']);
$currentDir    = basename(dirname($_SERVER['PHP_SELF']));

/**
 * Cek apakah menu item sedang aktif.
 */
function isStaffMenuActive(string ...$files): bool
{
    global $currentScript, $currentDir;
    foreach ($files as $file) {
        if ($currentScript === $file || $currentDir === $file) {
            return true;
        }
    }
    return false;
}

/**
 * Render satu menu item sidebar staff.
 */
function staffMenuItem(string $href, string $icon, string $label, bool $active, string $sublabel = ''): string
{
    $activeStyle  = $active
        ? 'background:rgba(255,255,255,.15);color:#fff;font-weight:600;'
        : 'color:rgba(255,255,255,.75);';
    $activeClass  = $active ? ' is-active' : '';

    $sublabelHtml = $sublabel
        ? "<span style='
            color:rgba(255,255,255,.45);font-size:11px;
            display:block;line-height:1.2;margin-top:2px;
          '>{$sublabel}</span>"
        : '';

    return <<<HTML
    <a href="{$href}"
       style="
           display:flex;align-items:center;gap:10px;
           padding:10px 14px;border-radius:8px;
           text-decoration:none;font-size:14px;
           transition:background .15s, color .15s;
           {$activeStyle}
       "
       onmouseover="if(!this.classList.contains('is-active')){this.style.background='rgba(255,255,255,.1)';this.style.color='#fff';}"
       onmouseout="if(!this.classList.contains('is-active')){this.style.background='transparent';this.style.color='rgba(255,255,255,.75)';}"
       class="sidebar-link{$activeClass}"
    >
        <span style="flex-shrink:0;width:18px;display:flex;justify-content:center;">
            {$icon}
        </span>
        <span style="flex:1;min-width:0;">
            <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                {$label}
            </span>
            {$sublabelHtml}
        </span>
    </a>
    HTML;
}

// Ikon SVG
$icons = [
    'dashboard'  => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>',
    'events'     => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
    'transaksi'  => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 1 0 0 7h5a3.5 3.5 0 1 1 0 7H6"/></svg>',
    'profil'     => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    'password'   => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
    'logout'     => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
    'bukti'      => '<svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>',
];

// Data EO & user
$eoNama    = htmlspecialchars($currentEo['nama_eo'] ?? 'EO Finance', ENT_QUOTES, 'UTF-8');
$userNama  = htmlspecialchars($currentUser['nama']  ?? '', ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($currentUser['email'] ?? '', ENT_QUOTES, 'UTF-8');
$logoPath  = $currentEo['logo'] ?? null;
$eoInitials = mb_strtoupper(mb_substr(preg_replace('/[^a-zA-Z\s]/', '', $eoNama), 0, 2));

$logoHtml = $logoPath
    ? "<img src='" . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . "'
            alt='Logo {$eoNama}'
            style='width:36px;height:36px;object-fit:contain;border-radius:6px;'>"
    : "<span style='
            display:flex;align-items:center;justify-content:center;
            width:36px;height:36px;background:rgba(255,255,255,.2);
            border-radius:6px;font-weight:700;font-size:13px;letter-spacing:.5px;
        '>{$eoInitials}</span>";

// Ambil daftar event yang ditugaskan ke staff ini dari session.
// Struktur: $_SESSION['penugasan'] = [
//   ['event_id' => 1, 'nama_event' => '...', 'tanggal_mulai' => '2025-01-01',
//    'status' => 'aktif', 'role_dalam_event' => 'Kasir'],
//   ...
// ]
$penugasanList   = $_SESSION['penugasan'] ?? [];
$penugasanTampil = array_slice($penugasanList, 0, 5);
$jumlahSemua     = count($penugasanList);

// Deteksi event aktif dari URL query string ?event_id=X
$activeEventId = (int)($_GET['event_id'] ?? 0);

// Warna badge per status event
function getEventStatusBadge(string $status): array
{
    return match ($status) {
        'aktif'      => ['bg' => '#16a34a', 'label' => 'Aktif'],
        'draft'      => ['bg' => '#6b7280', 'label' => 'Draft'],
        'selesai'    => ['bg' => '#2563eb', 'label' => 'Selesai'],
        'dibatalkan' => ['bg' => '#dc2626', 'label' => 'Batal'],
        default      => ['bg' => '#6b7280', 'label' => ucfirst($status)],
    };
}
?>

<!-- ============================================================
     SIDEBAR STAFF — EO Finance
     CSS: assets/css/staff.css | JS: assets/js/main.js
     ============================================================ -->

<!-- Overlay mobile (class di-toggle oleh main.js) -->
<div id="sidebar-overlay-staff" onclick="closeStaffSidebar()" aria-hidden="true"></div>

<!-- Hamburger mobile -->
<button id="sidebar-toggle-staff" onclick="openStaffSidebar()" aria-label="Buka menu">
    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"/>
        <line x1="3" y1="12" x2="21" y2="12"/>
        <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
</button>

<!-- ============================================================
     SIDEBAR UTAMA STAFF
     ============================================================ -->
<nav id="sidebar-staff" role="navigation" aria-label="Menu Staff">

    <!-- Header EO + badge peran -->
    <div style="padding:20px 16px 16px;border-bottom:1px solid rgba(255,255,255,.08);">
        <div style="display:flex;align-items:center;gap:10px;">
            <?= $logoHtml ?>
            <div style="min-width:0;">
                <div style="color:#fff;font-weight:700;font-size:14px;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                     title="<?= $eoNama ?>">
                    <?= $eoNama ?>
                </div>
                <div style="
                    display:inline-flex;align-items:center;gap:4px;
                    margin-top:3px;
                    background:rgba(59,130,246,.3);
                    border:1px solid rgba(59,130,246,.5);
                    border-radius:10px;
                    padding:1px 8px;
                    font-size:10px;color:#93c5fd;font-weight:600;letter-spacing:.3px;
                ">
                    <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Staff EO
                </div>
            </div>
            <!-- Tutup (mobile) -->
            <button onclick="closeStaffSidebar()"
                    id="staff-sidebar-close"
                    style="margin-left:auto;background:none;border:none;cursor:pointer;
                           color:rgba(255,255,255,.6);padding:4px;display:none;"
                    aria-label="Tutup menu">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- Menu utama -->
    <div style="padding:12px 10px;flex:1;">

        <!-- Seksi: Umum -->
        <div style="padding:0 6px 4px;color:rgba(255,255,255,.35);
                    font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;
                    margin-bottom:2px;">
            Umum
        </div>

        <?= staffMenuItem(
            BASE_URL . '/staff/dashboard.php',
            $icons['dashboard'],
            'Dashboard',
            isStaffMenuActive('dashboard.php')
        ) ?>

        <!-- Seksi: Event Saya -->
        <div style="padding:12px 6px 6px;color:rgba(255,255,255,.35);
                    font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;">
            Event Saya
            <?php if ($jumlahSemua > 0): ?>
                <span style="
                    background:rgba(59,130,246,.4);color:#93c5fd;
                    font-size:9px;font-weight:700;
                    border-radius:8px;padding:0 5px;margin-left:4px;
                "><?= $jumlahSemua ?></span>
            <?php endif; ?>
        </div>

        <?php if (empty($penugasanList)): ?>
            <!-- State: belum ada penugasan -->
            <div style="
                padding:14px 14px;border-radius:8px;
                background:rgba(255,255,255,.05);
                border:1px dashed rgba(255,255,255,.15);
                margin:0 0 4px;text-align:center;
            ">
                <svg width="28" height="28" fill="none" stroke="rgba(255,255,255,.3)"
                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                     viewBox="0 0 24 24" style="margin:0 auto 6px;">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <p style="color:rgba(255,255,255,.4);font-size:12px;margin:0;line-height:1.4;">
                    Belum ada event<br>yang ditugaskan
                </p>
            </div>

        <?php else: ?>
            <!-- Daftar event yang ditugaskan -->
            <?php foreach ($penugasanTampil as $penugasan):
                $isEventActive = ($activeEventId === (int)$penugasan['event_id']);
                $badge         = getEventStatusBadge($penugasan['status'] ?? 'draft');
                $namaEvent     = htmlspecialchars($penugasan['nama_event'] ?? 'Event', ENT_QUOTES, 'UTF-8');
                $roleDalam     = htmlspecialchars($penugasan['role_dalam_event'] ?? '', ENT_QUOTES, 'UTF-8');
                $tanggalMulai  = !empty($penugasan['tanggal_mulai'])
                    ? date('d M Y', strtotime($penugasan['tanggal_mulai']))
                    : '';
            ?>
                <a href="<?= BASE_URL ?>/staff/events/detail.php?event_id=<?= (int)$penugasan['event_id'] ?>"
                   class="sidebar-event-item<?= $isEventActive ? ' is-active' : '' ?>"
                   title="<?= $namaEvent ?>"
                >
                    <span style="
                        width:8px;height:8px;border-radius:50%;flex-shrink:0;
                        background:<?= $badge['bg'] ?>;
                    " aria-hidden="true"></span>

                    <span style="flex:1;min-width:0;">
                        <span style="display:block;white-space:nowrap;overflow:hidden;
                                     text-overflow:ellipsis;font-size:13px;">
                            <?= $namaEvent ?>
                        </span>
                        <?php if ($tanggalMulai): ?>
                            <span style="display:block;color:rgba(255,255,255,.4);font-size:11px;margin-top:1px;">
                                <?= $tanggalMulai ?>
                            </span>
                        <?php endif; ?>
                    </span>

                    <?php if ($roleDalam): ?>
                        <span class="event-role-badge"><?= $roleDalam ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>

            <?php if ($jumlahSemua > 5): ?>
                <a href="<?= BASE_URL ?>/staff/events/"
                   style="
                       display:block;text-align:center;padding:7px 14px;
                       color:rgba(59,130,246,.9);font-size:12px;
                       text-decoration:none;border-radius:8px;
                       border:1px solid rgba(59,130,246,.3);
                       margin-top:4px;
                       transition:background .15s,color .15s;
                   "
                   onmouseover="this.style.background='rgba(59,130,246,.15)';this.style.color='#93c5fd';"
                   onmouseout="this.style.background='transparent';this.style.color='rgba(59,130,246,.9)';"
                >
                    Lihat <?= $jumlahSemua - 5 ?> event lainnya →
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Seksi: Tugas Saya -->
        <div style="padding:14px 6px 4px;color:rgba(255,255,255,.35);
                    font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;
                    margin-bottom:2px;">
            Tugas Saya
        </div>

        <?= staffMenuItem(
            BASE_URL . '/staff/transaksi/',
            $icons['transaksi'],
            'Input Transaksi',
            isStaffMenuActive('transaksi'),
            'Catat pemasukan & pengeluaran'
        ) ?>

        <?= staffMenuItem(
            BASE_URL . '/staff/bukti/',
            $icons['bukti'],
            'Upload Bukti',
            isStaffMenuActive('bukti'),
            'Unggah bukti pembayaran'
        ) ?>

        <!-- Seksi: Akun -->
        <div style="padding:14px 6px 4px;color:rgba(255,255,255,.35);
                    font-size:10px;font-weight:700;letter-spacing:.8px;text-transform:uppercase;
                    margin-bottom:2px;">
            Akun
        </div>

        <?= staffMenuItem(
            BASE_URL . '/staff/profil.php',
            $icons['profil'],
            'Profil Saya',
            isStaffMenuActive('profil.php')
        ) ?>

        <?= staffMenuItem(
            BASE_URL . '/staff/ganti-password.php',
            $icons['password'],
            'Ganti Password',
            isStaffMenuActive('ganti-password.php')
        ) ?>

    </div>

    <!-- Footer: info user & logout -->
    <div style="padding:12px 10px;border-top:1px solid rgba(255,255,255,.08);">

        <div style="
            display:flex;align-items:center;gap:10px;
            padding:10px 12px;border-radius:8px;
            background:rgba(255,255,255,.05);
            margin-bottom:6px;
        ">
            <div style="
                width:34px;height:34px;border-radius:50%;
                background:linear-gradient(135deg,#0ea5e9,#2563eb);
                display:flex;align-items:center;justify-content:center;
                color:#fff;font-weight:700;font-size:13px;flex-shrink:0;
            " aria-hidden="true">
                <?= mb_strtoupper(mb_substr($currentUser['nama'] ?? 'S', 0, 1)) ?>
            </div>
            <div style="min-width:0;">
                <div style="color:#fff;font-size:13px;font-weight:600;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                     title="<?= $userNama ?>">
                    <?= $userNama ?>
                </div>
                <div style="color:rgba(255,255,255,.45);font-size:11px;
                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                     title="<?= $userEmail ?>">
                    <?= $userEmail ?>
                </div>
            </div>
        </div>

        <a href="<?= BASE_URL ?>/logout.php"
           onclick="return confirm('Yakin ingin keluar dari akun ini?')"
           style="
               display:flex;align-items:center;gap:10px;
               padding:9px 14px;border-radius:8px;
               color:rgba(255,100,100,.85);text-decoration:none;font-size:14px;
               transition:background .15s, color .15s;
           "
           onmouseover="this.style.background='rgba(239,68,68,.15)';this.style.color='#fca5a5';"
           onmouseout="this.style.background='transparent';this.style.color='rgba(255,100,100,.85)';"
        >
            <span style="flex-shrink:0;width:18px;display:flex;justify-content:center;">
                <?= $icons['logout'] ?>
            </span>
            Keluar
        </a>
    </div>

</nav>