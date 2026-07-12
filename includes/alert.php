<?php
/**
 * includes/alert.php
 *
 * Sistem alert/flash message terpusat untuk EO Finance.
 *
 * CARA PAKAI:
 *   1. Set flash (sebelum redirect):
 *        setAlert('success', 'Data berhasil disimpan.');
 *        header('Location: somewhere.php'); exit;
 *
 *   2. Render flash (di halaman tujuan, sekali):
 *        renderAlert();
 *
 *   3. Render inline (langsung, tanpa session):
 *        renderAlertInline('error', 'Email sudah terdaftar.');
 *
 * TIPE YANG TERSEDIA: success | error | warning | info
 */

// Pastikan session sudah aktif sebelum memanggil fungsi session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ------------------------------------------------------------------ */
/*  Konstanta tipe alert                                                */
/* ------------------------------------------------------------------ */
const ALERT_SUCCESS = 'success';
const ALERT_ERROR   = 'error';
const ALERT_WARNING = 'warning';
const ALERT_INFO    = 'info';

/* ------------------------------------------------------------------ */
/*  Konfigurasi visual per tipe                                         */
/* ------------------------------------------------------------------ */
function _getAlertConfig(string $type): array
{
    $configs = [
        'success' => [
            'bg'          => '#f0fdf4',
            'border'      => '#86efac',
            'text'        => '#166534',
            'icon_color'  => '#16a34a',
            'icon'        => <<<SVG
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                  <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            SVG,
            'label' => 'Berhasil',
        ],
        'error' => [
            'bg'         => '#fef2f2',
            'border'     => '#fca5a5',
            'text'       => '#991b1b',
            'icon_color' => '#dc2626',
            'icon'       => <<<SVG
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="15" y1="9" x2="9" y2="15"/>
                  <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            SVG,
            'label' => 'Gagal',
        ],
        'warning' => [
            'bg'         => '#fffbeb',
            'border'     => '#fcd34d',
            'text'       => '#92400e',
            'icon_color' => '#d97706',
            'icon'       => <<<SVG
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71
                           3.86a2 2 0 0 0-3.42 0z"/>
                  <line x1="12" y1="9" x2="12" y2="13"/>
                  <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            SVG,
            'label' => 'Peringatan',
        ],
        'info' => [
            'bg'         => '#eff6ff',
            'border'     => '#93c5fd',
            'text'       => '#1e3a8a',
            'icon_color' => '#2563eb',
            'icon'       => <<<SVG
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="12" y1="8" x2="12" y2="12"/>
                  <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
            SVG,
            'label' => 'Informasi',
        ],
    ];

    return $configs[$type] ?? $configs['info'];
}

/* ------------------------------------------------------------------ */
/*  API Publik                                                           */
/* ------------------------------------------------------------------ */

/**
 * Simpan alert ke session (dipakai sebelum redirect).
 *
 * @param string $type    Tipe alert: success | error | warning | info
 * @param string $message Pesan yang ingin ditampilkan
 */
function setAlert(string $type, string $message): void
{
    $allowed = [ALERT_SUCCESS, ALERT_ERROR, ALERT_WARNING, ALERT_INFO];
    if (!in_array($type, $allowed, true)) {
        $type = ALERT_INFO;
    }

    $_SESSION['_alert'] = [
        'type'    => $type,
        'message' => htmlspecialchars(strip_tags($message), ENT_QUOTES, 'UTF-8'),
    ];
}

/**
 * Ambil & hapus alert dari session, lalu render HTML-nya.
 * Harus dipanggil sekali di halaman setelah redirect.
 *
 * @param bool $autoDismiss Tambahkan auto-dismiss (5 detik) via JS
 */
function renderAlert(bool $autoDismiss = true): void
{
    if (empty($_SESSION['_alert'])) {
        return;
    }

    $alert = $_SESSION['_alert'];
    unset($_SESSION['_alert']);

    _printAlertHtml($alert['type'], $alert['message'], $autoDismiss);
}

/**
 * Render alert langsung (inline, tanpa session).
 * Cocok untuk validasi di halaman yang sama tanpa redirect.
 *
 * @param string $type       Tipe alert
 * @param string $message    Pesan (boleh mengandung tag aman seperti <strong>)
 * @param bool   $autoDismiss Auto-dismiss setelah 5 detik
 */
function renderAlertInline(string $type, string $message, bool $autoDismiss = false): void
{
    $allowed = [ALERT_SUCCESS, ALERT_ERROR, ALERT_WARNING, ALERT_INFO];
    if (!in_array($type, $allowed, true)) {
        $type = ALERT_INFO;
    }

    // Strip semua tag kecuali yang aman
    $safeMessage = strip_tags($message, '<strong><em><b><i><br>');

    _printAlertHtml($type, $safeMessage, $autoDismiss);
}

/**
 * Tampilkan banyak error sekaligus (misal: kumpulan validation errors).
 *
 * @param string[] $errors Array of pesan error
 */
function renderAlertList(array $errors): void
{
    if (empty($errors)) {
        return;
    }

    $items = '';
    foreach ($errors as $err) {
        $safe   = htmlspecialchars(strip_tags((string)$err), ENT_QUOTES, 'UTF-8');
        $items .= "<li>{$safe}</li>";
    }

    $listMessage = "<ul style='margin:4px 0 0 16px;padding:0;'>{$items}</ul>";
    _printAlertHtml(ALERT_ERROR, $listMessage, false);
}

/* ------------------------------------------------------------------ */
/*  Renderer Internal                                                   */
/* ------------------------------------------------------------------ */

/**
 * Generate HTML alert. Digunakan secara internal oleh semua fungsi render.
 */
function _printAlertHtml(string $type, string $message, bool $autoDismiss): void
{
    $cfg = _getAlertConfig($type);
    $uid = 'alert-' . bin2hex(random_bytes(4)); // ID unik agar multi-alert aman

    $dismissBtn = <<<HTML
        <button
            type="button"
            onclick="document.getElementById('{$uid}').remove()"
            aria-label="Tutup notifikasi"
            style="
                background:none;border:none;cursor:pointer;
                color:{$cfg['text']};opacity:.7;padding:2px;
                display:flex;align-items:center;flex-shrink:0;
                transition:opacity .15s;
            "
            onmouseover="this.style.opacity='1'"
            onmouseout="this.style.opacity='.7'"
        >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    HTML;

    $autoDismissJs = $autoDismiss ? <<<JS
        <script>
        (function(){
            var el = document.getElementById('{$uid}');
            if (!el) return;
            setTimeout(function(){
                el.style.transition = 'opacity .4s ease, transform .4s ease';
                el.style.opacity    = '0';
                el.style.transform  = 'translateY(-6px)';
                setTimeout(function(){ el && el.remove(); }, 400);
            }, 5000);
        })();
        </script>
    JS : '';

    echo <<<HTML
    <div
        id="{$uid}"
        role="alert"
        aria-live="assertive"
        style="
            display:flex;align-items:flex-start;gap:12px;
            background:{$cfg['bg']};
            border:1px solid {$cfg['border']};
            border-left:4px solid {$cfg['icon_color']};
            border-radius:8px;
            padding:14px 16px;
            margin-bottom:16px;
            font-family:inherit;
            font-size:14px;
            line-height:1.5;
            color:{$cfg['text']};
            box-shadow:0 1px 3px rgba(0,0,0,.06);
        "
    >
        <span style="color:{$cfg['icon_color']};flex-shrink:0;margin-top:1px;">
            {$cfg['icon']}
        </span>
        <div style="flex:1;min-width:0;">
            <strong style="font-size:13px;font-weight:600;display:block;margin-bottom:2px;">
                {$cfg['label']}
            </strong>
            <span>{$message}</span>
        </div>
        {$dismissBtn}
    </div>
    {$autoDismissJs}
    HTML;
}