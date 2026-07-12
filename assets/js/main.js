/**
 * EO Finance — main.js
 *
 * Script global yang dimuat di semua halaman via footer.php (defer).
 * Berisi helper DOM murni tanpa dependensi library eksternal.
 *
 * Fungsi yang tersedia:
 *   1. initFlashMessages()   — auto-dismiss pesan flash [role="alert"]
 *   2. initTableRowHighlight() — highlight baris tabel saat tombol hapus diklik
 *   3. formatRupiah(angka)   — format angka ke string "Rp 1.000.000"
 *   4. initSidebarToggle()   — toggle sidebar mobile (admin & staff)
 *
 * Semua fungsi diinisialisasi di dalam DOMContentLoaded agar DOM
 * sudah tersedia saat script berjalan (footer.php sudah pakai defer,
 * tapi DOMContentLoaded tetap disertakan sebagai lapisan keamanan ekstra).
 */

document.addEventListener('DOMContentLoaded', function () {

    /* ====================================================================
       1. FLASH MESSAGE — AUTO-DISMISS
       ====================================================================
       Mencari semua elemen [role="alert"] yang dihasilkan oleh alert.php,
       lalu menyembunyikannya otomatis setelah FLASH_DELAY ms.

       Mekanisme fade-out murni CSS transition:
         - opacity → 0  (konten menghilang secara visual)
         - height  → 0  (elemen menyusut dan tidak menyisakan ruang kosong)
         - margin  → 0  (hapus jarak antar alert)
       Setelah transisi selesai, elemen dihapus dari DOM.

       CATATAN: alert.php sudah punya inline auto-dismiss sendiri (5 detik)
       untuk alert yang di-render via renderAlert($autoDismiss=true).
       Fungsi ini bertindak sebagai fallback global untuk alert yang
       tidak menyertakan autoDismissJs — atau jika ada kebutuhan waktu
       dismiss yang berbeda (misal alert inline di form).
    ==================================================================== */
    function initFlashMessages() {
        var FLASH_DELAY      = 3000; // ms sebelum mulai fade-out
        var TRANSITION_SPEED = 400;  // ms durasi transisi CSS

        // Pilih semua elemen alert yang belum punya timer dari PHP
        // (ditandai: tidak memiliki atribut data-auto-dismiss="php")
        var alerts = document.querySelectorAll('[role="alert"]:not([data-auto-dismiss="php"])');

        alerts.forEach(function (el) {
            // Siapkan transition agar fade-out bisa berjalan mulus
            el.style.transition =
                'opacity ' + TRANSITION_SPEED + 'ms ease, ' +
                'max-height ' + TRANSITION_SPEED + 'ms ease, ' +
                'margin ' + TRANSITION_SPEED + 'ms ease, ' +
                'padding ' + TRANSITION_SPEED + 'ms ease';

            // Rekam tinggi asli sebelum di-collapse
            // (max-height dipakai karena height tidak bisa di-transition ke 0
            //  jika nilai awalnya 'auto')
            el.style.maxHeight = el.scrollHeight + 'px';
            el.style.overflow  = 'hidden';

            setTimeout(function () {
                // Fase 1: konten memudar
                el.style.opacity   = '0';
                el.style.maxHeight = '0';
                el.style.margin    = '0';
                el.style.padding   = '0';

                // Fase 2: hapus dari DOM setelah transisi selesai
                setTimeout(function () {
                    if (el && el.parentNode) {
                        el.parentNode.removeChild(el);
                    }
                }, TRANSITION_SPEED);

            }, FLASH_DELAY);
        });
    }


    /* ====================================================================
       2. HIGHLIGHT BARIS TABEL — KONFIRMASI HAPUS
       ====================================================================
       Karena proyek ini tidak menggunakan JS untuk form hapus
       (konfirmasi hapus dilakukan via halaman terpisah), fungsi ini
       hanya bertanggung jawab memberikan feedback visual:
       baris tabel akan di-highlight (diberi class .row--deleting)
       saat user mengklik tombol hapus di baris tersebut.

       Tombol hapus diasumsikan memiliki atribut:
         - data-delete="true"  → penanda tombol hapus
         - href / action       → menuju halaman konfirmasi (ditangani PHP)

       Cara pakai di template PHP:
         <a href="hapus.php?id=<?= $id ?>"
            data-delete="true"
            class="btn btn--danger btn--sm">
           Hapus
         </a>
    ==================================================================== */
    function initTableRowHighlight() {
        // Delegasi event ke document agar bekerja pada tabel yang di-render
        // secara dinamis (misal setelah filter/pencarian via PHP redirect)
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-delete="true"]');
            if (!btn) return;

            // Cari baris <tr> terdekat dari tombol yang diklik
            var row = btn.closest('tr');
            if (!row) return;

            // Hapus highlight lama di tabel yang sama (jika ada)
            var table = row.closest('table');
            if (table) {
                table.querySelectorAll('tr.row--deleting').forEach(function (r) {
                    r.classList.remove('row--deleting');
                });
            }

            // Tambahkan highlight ke baris yang dipilih
            row.classList.add('row--deleting');

            // CATATAN: navigasi ke halaman konfirmasi tetap berjalan normal
            // (href tidak dicegah). Class row--deleting hanya muncul
            // sebentar sebelum halaman berganti — memberikan sinyal visual
            // bahwa klik berhasil diterima.
        });
    }


    /* ====================================================================
       3. FORMAT RUPIAH — HELPER CLIENT-SIDE
       ====================================================================
       Mengubah angka mentah menjadi string format mata uang Rupiah.
       Diekspos ke scope global (window.formatRupiah) agar bisa dipanggil
       dari admin.js, staff.js, maupun inline script di halaman tertentu.

       Contoh:
         formatRupiah(1500000)    → "Rp 1.500.000"
         formatRupiah(1500000.50) → "Rp 1.500.000,50"
         formatRupiah('invalid')  → "Rp 0"
         formatRupiah(-500000)    → "-Rp 500.000"

       @param  {number|string} angka  Nilai yang akan diformat
       @return {string}               String terformat "Rp X.XXX.XXX"
    ==================================================================== */
    function formatRupiah(angka) {
        var nilai = parseFloat(angka);

        // Kembalikan "Rp 0" untuk nilai yang tidak valid
        if (isNaN(nilai)) {
            return 'Rp 0';
        }

        // Tangani nilai negatif: pisahkan tanda minus dulu
        var negatif = nilai < 0;
        var absolut = Math.abs(nilai);

        // Pisahkan bagian bulat dan desimal
        var bagianBulat    = Math.floor(absolut);
        var bagianDesimal  = absolut - bagianBulat;

        // Format bagian bulat dengan titik sebagai pemisah ribuan
        var bulatStr = bagianBulat
            .toString()
            .replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        // Tambahkan desimal jika ada (gunakan koma sebagai separator — konvensi ID)
        var desimalStr = '';
        if (bagianDesimal > 0) {
            // Bulatkan ke 2 angka di belakang koma, ambil bagian setelah titik
            desimalStr = ',' + bagianDesimal.toFixed(2).slice(2);
        }

        return (negatif ? '-' : '') + 'Rp ' + bulatStr + desimalStr;
    }

    // Ekspos ke global agar bisa dipakai dari file JS lain atau inline script
    window.formatRupiah = formatRupiah;


    /* ====================================================================
       4. TOGGLE SIDEBAR — MOBILE
       ====================================================================
       Mendeteksi sidebar mana yang aktif di halaman saat ini
       (admin: #sidebar-admin | staff: #sidebar-staff) lalu
       mengekspos fungsi openSidebar / closeSidebar ke scope global.

       Mengapa tidak cukup pakai inline onclick di HTML?
       Fungsi openSidebar() & closeSidebar() sudah didefinisikan di dalam
       masing-masing sidebar-admin.php / sidebar-staff.php. Fungsi di main.js
       ini adalah PELENGKAP — menyediakan satu entry point terpusat
       (window.toggleSidebar) yang bisa dipanggil dari tombol atau
       shortcut keyboard tanpa perlu tahu konteks halaman (admin/staff).

       Selain itu, fungsi ini mendaftarkan listener keyboard (Escape)
       di level document — lebih aman dibanding listener di dalam
       masing-masing sidebar karena tidak terduplikasi.

       ID yang digunakan (sesuai sidebar-admin.php & sidebar-staff.php):
         Admin : #sidebar-admin  | tombol: #sidebar-toggle
         Staff : #sidebar-staff  | tombol: #sidebar-toggle-staff

       CSS class yang di-toggle: .is-open (sudah terdefinisi di CSS masing-masing)
    ==================================================================== */
    function initSidebarToggle() {
        // Deteksi sidebar mana yang ada di halaman ini
        var sidebarAdmin = document.getElementById('sidebar-admin');
        var sidebarStaff = document.getElementById('sidebar-staff');
        var sidebar      = sidebarAdmin || sidebarStaff; // ambil yang ada

        if (!sidebar) return; // halaman publik (login, register) — tidak ada sidebar

        var isAdmin  = Boolean(sidebarAdmin);
        var toggleId = isAdmin ? 'sidebar-toggle' : 'sidebar-toggle-staff';
        var btnToggle = document.getElementById(toggleId);

        /**
         * Buka sidebar: tambah .is-open + kunci scroll body
         */
        function openSidebar() {
            sidebar.classList.add('is-open');
            document.body.style.overflow = 'hidden'; // cegah scroll background
            sidebar.setAttribute('aria-hidden', 'false');
            if (btnToggle) btnToggle.setAttribute('aria-expanded', 'true');
        }

        /**
         * Tutup sidebar: hapus .is-open + kembalikan scroll body
         */
        function closeSidebar() {
            sidebar.classList.remove('is-open');
            document.body.style.overflow = '';
            sidebar.setAttribute('aria-hidden', 'true');
            if (btnToggle) btnToggle.setAttribute('aria-expanded', 'false');
        }

        /**
         * Toggle: buka jika tertutup, tutup jika terbuka
         */
        function toggleSidebar() {
            if (sidebar.classList.contains('is-open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        }

        // Daftarkan ke global window agar bisa dipanggil dari
        // inline onclick di sidebar-admin.php / sidebar-staff.php
        // ("openSidebar()" / "closeSidebar()" yang sudah ada di HTML)
        if (isAdmin) {
            window.openSidebar  = openSidebar;
            window.closeSidebar = closeSidebar;
        } else {
            window.openStaffSidebar  = openSidebar;
            window.closeStaffSidebar = closeSidebar;
        }

        // Satu entry point netral untuk keperluan internal / testing
        window.toggleSidebar = toggleSidebar;

        // Tutup sidebar dengan tombol Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && sidebar.classList.contains('is-open')) {
                closeSidebar();
            }
        });

        // Tutup sidebar jika viewport melebar ke desktop
        // (mencegah sidebar "terjebak terbuka" setelah resize)
        if (window.matchMedia) {
            var mq = window.matchMedia('(min-width: 768px)');
            mq.addEventListener('change', function (e) {
                if (e.matches) closeSidebar();
            });
        }
    }


    /* ====================================================================
       INISIALISASI — panggil semua fungsi
    ==================================================================== */
    initFlashMessages();
    initTableRowHighlight();
    initSidebarToggle();

    // formatRupiah tidak perlu diinisialisasi karena sudah tersedia
    // via window.formatRupiah sejak baris di atas dieksekusi.

}); // end DOMContentLoaded