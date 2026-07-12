/* EO Finance — Script khusus interaksi halaman staff: validasi form transaksi */
/**
 * EO Finance — staff.js
 *
 * Script khusus halaman-halaman staff. Dimuat setelah main.js oleh footer.php.
 * Ketergantungan:
 *   - Chart.js 4.4.0      (dimuat lebih awal di footer.php via CDN)
 *   - window.formatRupiah()  (didefinisikan di main.js)
 *
 * ISI FILE
 * ─────────────────────────────────────────────────────────────────────────
 *  A. GRAFIK
 *     A1. initChartAktivitas() — Bar: jumlah transaksi 7 hari terakhir
 *     A2. initChartStatusTrx() — Donut: status transaksi (menunggu/disetujui/ditolak)
 *
 *  B. HELPER FORM TRANSAKSI
 *     B1. initKategoriLainLain()  — toggle wajib deskripsi jika kategori
 *                                   diisi "Lain-lain" (tambah & edit)
 *     B2. initPreviewJumlah()     — tampilkan format Rupiah real-time
 *                                   di bawah input jumlah
 * ─────────────────────────────────────────────────────────────────────────
 */

document.addEventListener('DOMContentLoaded', function () {

    /* ====================================================================
       A. GRAFIK
    ==================================================================== */

    /* ------------------------------------------------------------------
       A1. initChartAktivitas — Bar Chart (staff/dashboard.php)
       Menampilkan jumlah transaksi yang diinput staff per hari
       dalam 7 hari terakhir. Satu dataset, tampilan bersih tanpa legend.

       Data dari: window.dataAktivitas = { labels, data }
         labels → ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min']
         data   → [3, 0, 5, 2, 7, 1, 4]  (jumlah transaksi per hari)
    ------------------------------------------------------------------ */
    function initChartAktivitas() {
        var canvas = document.getElementById('chartAktivitas');
        if (!canvas) return;  // tidak ada di halaman ini

        var src = window.dataAktivitas;
        if (!src || !src.labels) {
            console.warn('[staff.js] window.dataAktivitas tidak ditemukan.');
            return;
        }

        // Baca warna --primary dari CSS agar sinkron dengan tema
        var colorPrimary = getComputedStyle(document.documentElement)
                               .getPropertyValue('--primary')
                               .trim() || '#6366f1';

        // Warna bar sedikit transparan agar terlihat lebih ringan,
        // border solid untuk ketegasan
        var bgColor     = colorPrimary + 'cc';  // ~80% opacity via hex alpha
        var borderColor = colorPrimary;

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: src.labels,
                datasets: [{
                    label:           'Transaksi',  // dipakai tooltip, bukan legend
                    data:            src.data,
                    backgroundColor: bgColor,
                    borderColor:     borderColor,
                    borderWidth:     1,
                    borderRadius:    5,
                    borderSkipped:   false,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                animation: {
                    duration: 300,
                },
                plugins: {
                    // Sembunyikan legend — hanya satu dataset, tidak perlu label
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        // Tampilkan jumlah transaksi, bukan format Rupiah
                        callbacks: {
                            label: function (context) {
                                var n = context.parsed.y;
                                return n + (n === 1 ? ' transaksi' : ' transaksi');
                            },
                        },
                        titleFont: { family: 'system-ui, -apple-system, sans-serif', size: 12 },
                        bodyFont:  { family: 'system-ui, -apple-system, sans-serif', size: 12 },
                        padding:      10,
                        cornerRadius: 6,
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },  // grid vertikal dihapus — lebih bersih
                        ticks: {
                            font:  { family: 'system-ui, -apple-system, sans-serif', size: 12 },
                            color: getComputedStyle(document.documentElement)
                                       .getPropertyValue('--text-secondary').trim()
                                   || '#6b7280',
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: getComputedStyle(document.documentElement)
                                       .getPropertyValue('--border').trim()
                                   || '#e5e7eb',
                            drawBorder: false,
                        },
                        ticks: {
                            font:  { family: 'system-ui, -apple-system, sans-serif', size: 11 },
                            color: getComputedStyle(document.documentElement)
                                       .getPropertyValue('--text-secondary').trim()
                                   || '#6b7280',
                            // Hanya tampilkan angka bulat — tidak ada 0.5 transaksi
                            precision: 0,
                            stepSize:  1,
                            callback: function (val) {
                                return Number.isInteger(val) ? val : null;
                            },
                        },
                    },
                },
            },
        });
    }

    /* ------------------------------------------------------------------
       A2. initChartStatusTrx — Donut Chart (staff/dashboard.php)
       Ringkasan status seluruh transaksi pada event yang ditugaskan ke
       staff ini (bukan hanya yang dia catat sendiri):
       Menunggu / Disetujui / Ditolak. Donut dipilih (bukan bar) karena
       ini murni proporsi 3 kategori tetap — lebih cepat "dibaca sekilas"
       untuk lihat berapa besar porsi yang masih menunggu approval.

       Data dari: window.dataStatusTrx = { labels, data }
         labels → ['Menunggu', 'Disetujui', 'Ditolak']
         data   → [2, 14, 1]
    ------------------------------------------------------------------ */
    function initChartStatusTrx() {
        var canvas = document.getElementById('chartStatusTrx');
        if (!canvas) return;  // tidak ada di halaman ini

        var src = window.dataStatusTrx;
        if (!src || !src.labels) {
            console.warn('[staff.js] window.dataStatusTrx tidak ditemukan.');
            return;
        }

        // Warna tetap & bermakna: kuning=menunggu, hijau=disetujui, merah=ditolak
        // supaya konsisten dengan badge status di tabel transaksi lain.
        var colorWarning = getComputedStyle(document.documentElement)
                               .getPropertyValue('--warning').trim() || '#f59e0b';
        var colorSuccess = getComputedStyle(document.documentElement)
                               .getPropertyValue('--success').trim() || '#10b981';
        var colorDanger  = getComputedStyle(document.documentElement)
                               .getPropertyValue('--danger').trim()  || '#ef4444';

        var totalTrx = src.data.reduce(function (a, b) { return a + b; }, 0);

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: src.labels,
                datasets: [{
                    data:            src.data,
                    backgroundColor: [colorWarning, colorSuccess, colorDanger],
                    borderWidth:     2,
                    borderColor:     '#ffffff',
                    hoverOffset:     6,
                }],
            },
            options: {
                responsive:          true,
                maintainAspectRatio: false,
                cutout: '65%',
                animation: { duration: 300 },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font:     { family: 'system-ui, -apple-system, sans-serif', size: 12 },
                            color:    getComputedStyle(document.documentElement)
                                          .getPropertyValue('--text-secondary').trim()
                                      || '#6b7280',
                            boxWidth: 12,
                            padding:  14,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var n   = context.parsed;
                                var pct = totalTrx > 0 ? Math.round((n / totalTrx) * 100) : 0;
                                return context.label + ': ' + n + ' (' + pct + '%)';
                            },
                        },
                        titleFont: { family: 'system-ui, -apple-system, sans-serif', size: 12 },
                        bodyFont:  { family: 'system-ui, -apple-system, sans-serif', size: 12 },
                        padding:      10,
                        cornerRadius: 6,
                    },
                },
            },
        });
    }

    /* ====================================================================
       B. HELPER FORM TRANSAKSI
       Berlaku di staff/transaksi/tambah.php DAN edit.php karena
       kedua form menggunakan ID field yang identik (id="kategori",
       id="jumlah", id="deskripsi").
    ==================================================================== */

    /* ------------------------------------------------------------------
       B1. initKategoriLainLain — Toggle deskripsi wajib diisi
       Karena field #kategori adalah input text bebas (bukan select),
       fungsi ini mendengarkan event input dan membandingkan nilainya
       (case-insensitive) dengan string "lain-lain".

       Perilaku:
         - Ketik "Lain-lain" (atau variannya):
             → wrapper .form-group dari #deskripsi diberi class .is-required
             → label deskripsi mendapat tanda * (via class atau atribut)
             → textarea #deskripsi diberi required + placeholder diperjelas
         - Ketik nilai lain:
             → semua perubahan di atas dikembalikan ke kondisi awal

       MENGAPA TIDAK PAKAI required LANGSUNG DI PHP?
       Karena deskripsi bersifat opsional secara umum — hanya wajib
       diisi kontekstual saat kategori adalah "Lain-lain". Server-side
       validation di proses-transaksi.php tetap menjadi validasi utama;
       ini hanya UX enhancement client-side.
    ------------------------------------------------------------------ */
    function initKategoriLainLain() {
        var inputKategori = document.getElementById('kategori');
        var inputDeskripsi = document.getElementById('deskripsi');
        if (!inputKategori || !inputDeskripsi) return;  // bukan halaman form transaksi

        // Ambil wrapper .form-group dari textarea deskripsi
        var groupDeskripsi = inputDeskripsi.closest('.form-group');
        if (!groupDeskripsi) return;

        // Simpan label asli deskripsi untuk restore nanti
        var labelEl       = groupDeskripsi.querySelector('.form-label, label');
        var labelAwal     = labelEl ? labelEl.textContent.trim() : '';
        var placeholderAwal = inputDeskripsi.placeholder;

        /**
         * Aktifkan mode "deskripsi wajib diisi":
         * - Tandai wrapper dengan class CSS untuk styling
         * - Perbarui label dengan tanda bintang required
         * - Tambahkan atribut required ke textarea
         * - Perjelas placeholder sebagai petunjuk tambahan
         */
        function aktifkanWajib() {
            groupDeskripsi.classList.add('is-required');
            if (labelEl) {
                // Tambahkan <span> tanda required jika belum ada
                if (!labelEl.querySelector('.form-label__required')) {
                    var span = document.createElement('span');
                    span.className = 'form-label__required';
                    span.setAttribute('aria-hidden', 'true');
                    span.textContent = ' *';
                    labelEl.appendChild(span);
                }
            }
            inputDeskripsi.required    = true;
            inputDeskripsi.placeholder = 'Jelaskan kategori pengeluaran ini (wajib diisi)';
        }

        /**
         * Kembalikan deskripsi ke kondisi opsional:
         * - Hapus class dan tanda required
         * - Kembalikan placeholder semula
         */
        function nonaktifkanWajib() {
            groupDeskripsi.classList.remove('is-required');
            if (labelEl) {
                var span = labelEl.querySelector('.form-label__required');
                if (span) span.remove();
            }
            inputDeskripsi.required    = false;
            inputDeskripsi.placeholder = placeholderAwal;
        }

        // Jalankan pengecekan setiap kali nilai kategori berubah
        inputKategori.addEventListener('input', function () {
            var val = this.value.trim().toLowerCase();
            if (val === 'lain-lain' || val === 'lainnya' || val === 'lain lain') {
                aktifkanWajib();
            } else {
                nonaktifkanWajib();
            }
        });

        // Cek kondisi awal juga — penting di halaman edit.php
        // di mana kategori mungkin sudah terisi "Lain-lain" dari DB
        var valAwal = inputKategori.value.trim().toLowerCase();
        if (valAwal === 'lain-lain' || valAwal === 'lainnya' || valAwal === 'lain lain') {
            aktifkanWajib();
        }
    }

    /* ------------------------------------------------------------------
       B2. initPreviewJumlah — Preview format Rupiah real-time
       Mendengarkan event input pada #jumlah dan menampilkan hasil
       window.formatRupiah() di elemen preview yang dibuat dinamis
       tepat di bawah input.

       Elemen preview dibuat sekali (tidak duplikat) dengan ID
       "jumlah-preview" dan disisipkan setelah input#jumlah.

       Perilaku:
         - Input kosong atau 0  → sembunyikan preview
         - Input valid > 0      → tampilkan "Rp X.XXX.XXX" dengan warna
                                   --text-secondary, font sedikit lebih kecil
         - Input negatif (min=1 di PHP, tapi tetap ditangani) → Rp 0

       Berlaku di tambah.php DAN edit.php (ID field sama).
       Di edit.php, preview juga ditampilkan saat halaman pertama load
       jika field sudah terisi nilai dari database.
    ------------------------------------------------------------------ */
    function initPreviewJumlah() {
        var inputJumlah = document.getElementById('jumlah');
        if (!inputJumlah) return;  // bukan halaman form transaksi

        // Buat elemen preview sekali, sisipkan tepat setelah input
        var preview = document.createElement('span');
        preview.id = 'jumlah-preview';
        preview.setAttribute('aria-live', 'polite');  // pembaca layar baca perubahannya
        preview.setAttribute('aria-atomic', 'true');
        preview.style.cssText = [
            'display:block',
            'margin-top:4px',
            'font-size:13px',
            'font-weight:600',
            'color:var(--primary, #6366f1)',
            'min-height:18px',           // mencegah layout shift saat teks muncul/hilang
            'transition:opacity 0.15s',  // fade halus saat nilai berubah
        ].join(';');

        // Sisipkan preview setelah input, dalam .form-group yang sama
        inputJumlah.insertAdjacentElement('afterend', preview);

        /**
         * Perbarui teks preview berdasarkan nilai input saat ini.
         * Dipanggil saat: (a) user mengetik, (b) halaman pertama load (edit.php).
         */
        function updatePreview() {
            var raw = parseFloat(inputJumlah.value);

            if (!inputJumlah.value || isNaN(raw) || raw <= 0) {
                // Kosongkan dan sembunyikan preview
                preview.textContent = '';
                preview.style.opacity = '0';
                return;
            }

            var formatted = (typeof window.formatRupiah === 'function')
                                ? window.formatRupiah(raw)
                                : 'Rp ' + raw.toLocaleString('id-ID');

            preview.textContent = formatted;
            preview.style.opacity = '1';
        }

        // Perbarui setiap kali user mengubah nilai
        inputJumlah.addEventListener('input', updatePreview);

        // Jalankan sekali saat load — penting untuk edit.php yang sudah terisi
        updatePreview();
    }

    /* ====================================================================
       INISIALISASI — panggil semua fungsi
       Setiap fungsi sudah mengandung pengecekan elemen DOM sehingga
       aman dipanggil di halaman manapun yang dimuat admin.css + staff.js.
    ==================================================================== */

    // A — Grafik
    initChartAktivitas();
    initChartStatusTrx();

    // B — Helper form transaksi (aktif di tambah.php dan edit.php)
    initKategoriLainLain();
    initPreviewJumlah();

}); // end DOMContentLoaded