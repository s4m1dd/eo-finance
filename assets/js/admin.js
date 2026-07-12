/* EO Finance — Script khusus interaksi halaman admin: chart, filter tabel, modal konfirmasi */
/**
 * EO Finance — admin.js
 *
 * Script khusus halaman-halaman admin. Dimuat setelah main.js oleh footer.php.
 * Ketergantungan:
 *   - Chart.js 4.4.0  (dimuat lebih awal di footer.php via CDN, TANPA defer)
 *   - window.formatRupiah()  (didefinisikan di main.js)
 *
 * Data grafik dibaca dari variabel window terpisah yang di-embed dashboard.php:
 *   window.dataTren, window.dataKategori, window.dataProfit, window.dataAnggaran
 */

document.addEventListener('DOMContentLoaded', function () {

    /* ====================================================================
       A. KONFIGURASI & UTILITAS BERSAMA
    ==================================================================== */

    function getColor(prop, fallback) {
        var val = getComputedStyle(document.documentElement)
                    .getPropertyValue(prop)
                    .trim();
        return val || fallback;
    }

    var CHART_COLORS = [
        '#6366f1', '#f59e0b', '#10b981', '#ef4444', '#3b82f6',
        '#8b5cf6', '#f97316', '#06b6d4', '#ec4899', '#84cc16',
    ];

    function defaultOptions() {
        return {
            responsive:          true,
            maintainAspectRatio: false,
            animation: { duration: 300 },
            plugins: {
                legend: {
                    labels: {
                        font:     { family: 'system-ui, -apple-system, sans-serif', size: 12 },
                        color:    getColor('--text-secondary', '#6b7280'),
                        boxWidth: 12,
                        padding:  16,
                    },
                },
            },
            scales: {},
        };
    }

    function rupiahTooltip() {
        return {
            callbacks: {
                label: function (context) {
                    var label = context.dataset.label || context.label || '';
                    var value = context.parsed.y !== undefined
                                    ? context.parsed.y
                                    : context.parsed;
                    var fmt = (typeof window.formatRupiah === 'function')
                                ? window.formatRupiah(value)
                                : ('Rp ' + Number(value).toLocaleString('id-ID'));
                    return label ? (label + ': ' + fmt) : fmt;
                },
            },
            titleFont:    { family: 'system-ui, -apple-system, sans-serif', size: 12 },
            bodyFont:     { family: 'system-ui, -apple-system, sans-serif', size: 12 },
            padding:      10,
            cornerRadius: 6,
        };
    }

    function paletteSlice(count) {
        return Array.from({ length: count }, function (_, i) {
            return CHART_COLORS[i % CHART_COLORS.length];
        });
    }

    /* ====================================================================
       B. INISIALISASI GRAFIK
    ==================================================================== */

    /* B1. chartTren — Line Chart (dashboard)
       window.dataTren = { labels, dataPemasukan, dataPengeluaran }
    ------------------------------------------------------------------ */
    function initChartTren() {
        var canvas = document.getElementById('chartTren');
        if (!canvas) return;

        var src = window.dataTren;
        if (!src || !src.labels || !src.labels.length) {
            console.warn('[admin.js] window.dataTren tidak ditemukan atau kosong.');
            return;
        }

        var colorPrimary = getColor('--primary', '#6366f1');
        var colorDanger  = getColor('--danger',  '#ef4444');

        new Chart(canvas, {
            type: 'line',
            data: {
                labels: src.labels,
                datasets: [
                    {
                        label:               'Pemasukan',
                        data:                src.dataPemasukan,
                        borderColor:         colorPrimary,
                        backgroundColor:     'transparent',
                        pointBackgroundColor: colorPrimary,
                        pointRadius:         4,
                        pointHoverRadius:    6,
                        tension:             0.4,
                        borderWidth:         2,
                        fill:                false,
                    },
                    {
                        label:               'Pengeluaran',
                        data:                src.dataPengeluaran,
                        borderColor:         colorDanger,
                        backgroundColor:     'transparent',
                        pointBackgroundColor: colorDanger,
                        pointRadius:         4,
                        pointHoverRadius:    6,
                        tension:             0.4,
                        borderWidth:         2,
                        fill:                false,
                    },
                ],
            },
            options: (function () {
                var opt = defaultOptions();
                opt.plugins.legend.position = 'top';
                opt.plugins.tooltip = rupiahTooltip();
                opt.scales = {
                    x: {
                        grid:  { display: false },
                        ticks: {
                            font:  { family: 'system-ui, -apple-system, sans-serif', size: 11 },
                            color: getColor('--text-secondary', '#6b7280'),
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color:       getColor('--border', '#e5e7eb'),
                            drawBorder:  false,
                        },
                        ticks: {
                            font:  { family: 'system-ui, -apple-system, sans-serif', size: 11 },
                            color: getColor('--text-secondary', '#6b7280'),
                            callback: function (val) {
                                if (val >= 1000000) return (val / 1000000).toFixed(1) + 'jt';
                                if (val >= 1000)    return (val / 1000).toFixed(0) + 'rb';
                                return val;
                            },
                        },
                    },
                };
                return opt;
            }()),
        });
    }

    /* B2. chartKategori — Donut Chart (dashboard)
       window.dataKategori = { labels, data }
    ------------------------------------------------------------------ */
    function initChartKategori() {
        var canvas = document.getElementById('chartKategori');
        if (!canvas) return;

        var src = window.dataKategori;
        if (!src || !src.labels || !src.labels.length) {
            console.warn('[admin.js] window.dataKategori tidak ditemukan atau kosong.');
            return;
        }

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: src.labels,
                datasets: [{
                    data:            src.data,
                    backgroundColor: paletteSlice(src.data.length),
                    borderWidth:     2,
                    borderColor:     getColor('--bg-card', '#ffffff'),
                    hoverOffset:     6,
                }],
            },
            options: (function () {
                var opt = defaultOptions();
                opt.plugins.legend.position = 'bottom';
                opt.plugins.tooltip = rupiahTooltip();
                opt.cutout = '60%';
                return opt;
            }()),
        });
    }

    /* B3. chartProfit — Bar Horizontal (dashboard)
       window.dataProfit = { labels, data }
    ------------------------------------------------------------------ */
    function initChartProfit() {
        var canvas = document.getElementById('chartProfit');
        if (!canvas) return;

        var src = window.dataProfit;
        if (!src || !src.labels || !src.labels.length) {
            console.warn('[admin.js] window.dataProfit tidak ditemukan atau kosong.');
            return;
        }

        var colorSuccess = getColor('--success', '#10b981');

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: src.labels,
                datasets: [{
                    label:           'Profit',
                    data:            src.data,
                    backgroundColor: src.data.map(function (v) {
                        return v < 0
                            ? getColor('--danger', '#ef4444')
                            : colorSuccess;
                    }),
                    borderRadius:  4,
                    borderSkipped: false,
                }],
            },
            options: (function () {
                var opt = defaultOptions();
                opt.indexAxis = 'y';
                opt.plugins.legend.display = false;
                opt.plugins.tooltip = rupiahTooltip();
                opt.scales = {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color:      getColor('--border', '#e5e7eb'),
                            drawBorder: false,
                        },
                        ticks: {
                            font:  { family: 'system-ui, -apple-system, sans-serif', size: 11 },
                            color: getColor('--text-secondary', '#6b7280'),
                            callback: function (val) {
                                if (Math.abs(val) >= 1000000) return (val / 1000000).toFixed(1) + 'jt';
                                if (Math.abs(val) >= 1000)    return (val / 1000).toFixed(0) + 'rb';
                                return val;
                            },
                        },
                    },
                    y: {
                        grid:  { display: false },
                        ticks: {
                            font:  { family: 'system-ui, -apple-system, sans-serif', size: 11 },
                            color: getColor('--text-secondary', '#6b7280'),
                        },
                    },
                };
                return opt;
            }()),
        });
    }

    /* B4. chartAnggaran — Bar Grouped (dashboard)
       window.dataAnggaran = { labels, dataAnggaran, dataRealisasi }
    ------------------------------------------------------------------ */
    function initChartAnggaran() {
        var canvas = document.getElementById('chartAnggaran');
        if (!canvas) return;

        var src = window.dataAnggaran;
        if (!src || !src.labels || !src.labels.length) {
            console.warn('[admin.js] window.dataAnggaran tidak ditemukan atau kosong.');
            return;
        }

        var colorPrimary = getColor('--primary', '#6366f1');

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: src.labels,
                datasets: [
                    {
                        label:           'Anggaran',
                        data:            src.dataAnggaran,
                        backgroundColor: '#d1d5db',
                        borderRadius:    4,
                        borderSkipped:   false,
                    },
                    {
                        label:           'Realisasi',
                        data:            src.dataRealisasi,
                        backgroundColor: colorPrimary,
                        borderRadius:    4,
                        borderSkipped:   false,
                    },
                ],
            },
            options: (function () {
                var opt = defaultOptions();
                opt.plugins.legend.position = 'top';
                opt.plugins.tooltip = rupiahTooltip();
                opt.scales = {
                    x: {
                        grid:  { display: false },
                        ticks: {
                            font:  { family: 'system-ui, -apple-system, sans-serif', size: 11 },
                            color: getColor('--text-secondary', '#6b7280'),
                        },
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color:      getColor('--border', '#e5e7eb'),
                            drawBorder: false,
                        },
                        ticks: {
                            font:  { family: 'system-ui, -apple-system, sans-serif', size: 11 },
                            color: getColor('--text-secondary', '#6b7280'),
                            callback: function (val) {
                                if (val >= 1000000) return (val / 1000000).toFixed(1) + 'jt';
                                if (val >= 1000)    return (val / 1000).toFixed(0) + 'rb';
                                return val;
                            },
                        },
                    },
                };
                return opt;
            }()),
        });
    }

    /* B5. chartLaporan — Bar Grouped (laporan/index.php)
       window.dataLaporan = { labels, dataPemasukan, dataPengeluaran }
    ------------------------------------------------------------------ */
    function initChartLaporan() {
        var canvas = document.getElementById('chartLaporan');
        if (!canvas) return;

        var src = window.dataLaporan;
        if (!src || !src.labels) {
            console.warn('[admin.js] window.dataLaporan tidak ditemukan.');
            return;
        }

        var colorPrimary = getColor('--primary', '#6366f1');
        var colorDanger  = getColor('--danger',  '#ef4444');

        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: src.labels,
                datasets: [
                    {
                        label:           'Pemasukan',
                        data:            src.dataPemasukan,
                        backgroundColor: colorPrimary,
                        borderRadius:    4,
                        borderSkipped:   false,
                    },
                    {
                        label:           'Pengeluaran',
                        data:            src.dataPengeluaran,
                        backgroundColor: colorDanger,
                        borderRadius:    4,
                        borderSkipped:   false,
                    },
                ],
            },
            options: (function () {
                var opt = defaultOptions();
                opt.plugins.legend.position = 'top';
                opt.plugins.tooltip = rupiahTooltip();
                opt.scales = {
                    x: { grid: { display: false }, ticks: { font: { size: 11 }, color: getColor('--text-secondary', '#6b7280') } },
                    y: {
                        beginAtZero: true,
                        grid: { color: getColor('--border', '#e5e7eb'), drawBorder: false },
                        ticks: {
                            font: { size: 11 }, color: getColor('--text-secondary', '#6b7280'),
                            callback: function (val) {
                                if (val >= 1000000) return (val / 1000000).toFixed(1) + 'jt';
                                if (val >= 1000)    return (val / 1000).toFixed(0) + 'rb';
                                return val;
                            },
                        },
                    },
                };
                return opt;
            }()),
        });
    }

    /* B6. chartKategoriLaporan — Donut (laporan/index.php)
       window.dataKategoriLaporan = { labels, data }
    ------------------------------------------------------------------ */
    function initChartKategoriLaporan() {
        var canvas = document.getElementById('chartKategoriLaporan');
        if (!canvas) return;

        var src = window.dataKategoriLaporan;
        if (!src || !src.labels) {
            console.warn('[admin.js] window.dataKategoriLaporan tidak ditemukan.');
            return;
        }

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: src.labels,
                datasets: [{
                    data:            src.data,
                    backgroundColor: paletteSlice(src.data.length),
                    borderWidth:     2,
                    borderColor:     getColor('--bg-card', '#ffffff'),
                    hoverOffset:     6,
                }],
            },
            options: (function () {
                var opt = defaultOptions();
                opt.plugins.legend.position = 'bottom';
                opt.plugins.tooltip = rupiahTooltip();
                opt.cutout = '60%';
                return opt;
            }()),
        });
    }

    /* B7. chartDetailEvent — Donut (events/detail.php)
       window.dataDetailEvent = { labels, data }
    ------------------------------------------------------------------ */
    function initChartDetailEvent() {
        var canvas = document.getElementById('chartDetailEvent');
        if (!canvas) return;

        var src = window.dataDetailEvent;
        if (!src || !src.labels) {
            console.warn('[admin.js] window.dataDetailEvent tidak ditemukan.');
            return;
        }

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: src.labels,
                datasets: [{
                    data:            src.data,
                    backgroundColor: paletteSlice(src.data.length),
                    borderWidth:     2,
                    borderColor:     getColor('--bg-card', '#ffffff'),
                    hoverOffset:     6,
                }],
            },
            options: (function () {
                var opt = defaultOptions();
                opt.plugins.legend.position = 'bottom';
                opt.plugins.tooltip = rupiahTooltip();
                opt.cutout = '60%';
                return opt;
            }()),
        });
    }

    /* ====================================================================
       C. HELPER KHUSUS ADMIN
    ==================================================================== */

    function initLiveSearch() {
        var inputs = document.querySelectorAll('.table-search__input');
        if (!inputs.length) return;

        inputs.forEach(function (input) {
            var container = input.closest('section, .admin-section, .card, [class*="wrap"], main, .admin-content');
            var table     = container ? container.querySelector('.data-table') : null;

            if (!table) {
                var next = input.parentElement;
                while (next && !table) {
                    next  = next.parentElement;
                    table = next ? next.querySelector('.data-table') : null;
                }
            }

            if (!table) return;

            var tbody = table.querySelector('tbody');
            if (!tbody) return;

            var emptyRow  = document.createElement('tr');
            emptyRow.className   = 'live-search-empty';
            emptyRow.style.display = 'none';
            var emptyCell = document.createElement('td');
            emptyCell.colSpan    = table.querySelectorAll('thead th').length || 99;
            emptyCell.style.cssText = 'text-align:center;padding:24px;color:var(--text-secondary,#6b7280);font-style:italic;';
            emptyCell.textContent   = 'Tidak ada data yang cocok.';
            emptyRow.appendChild(emptyCell);
            tbody.appendChild(emptyRow);

            input.addEventListener('input', function () {
                var query = this.value.trim().toLowerCase();
                var rows  = tbody.querySelectorAll('tr:not(.live-search-empty)');
                var visibleCount = 0;

                rows.forEach(function (row) {
                    var rowText = Array.from(row.querySelectorAll('td'))
                                       .map(function (td) { return td.textContent; })
                                       .join(' ')
                                       .toLowerCase();
                    var match = !query || rowText.indexOf(query) !== -1;
                    row.style.display = match ? '' : 'none';
                    if (match) visibleCount++;
                });

                emptyRow.style.display = (visibleCount === 0 && query) ? '' : 'none';
            });
        });
    }

    function initStatusRowHighlight() {
        var tables = document.querySelectorAll('.data-table');
        if (!tables.length) return;

        var statusMap = {
            'badge--draft':      'row-status--draft',
            'badge--aktif':      'row-status--aktif',
            'badge--selesai':    'row-status--selesai',
            'badge--dibatalkan': 'row-status--dibatalkan',
        };

        tables.forEach(function (table) {
            table.querySelectorAll('tbody tr').forEach(function (row) {
                var badge = row.querySelector('[class*="badge--"]');
                if (!badge) return;
                Array.from(badge.classList).forEach(function (cls) {
                    if (statusMap[cls]) row.classList.add(statusMap[cls]);
                });
            });
        });
    }

    /* ====================================================================
       INISIALISASI
    ==================================================================== */
    initChartTren();
    initChartKategori();
    initChartProfit();
    initChartAnggaran();
    initChartLaporan();
    initChartKategoriLaporan();
    initChartDetailEvent();
    initLiveSearch();
    initStatusRowHighlight();

}); // end DOMContentLoaded