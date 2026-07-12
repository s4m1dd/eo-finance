<?php
/**
 * EO Finance — Loader sederhana untuk file .env.
 *
 * Membaca file .env di root project dan memasukkan nilainya ke $_ENV / getenv().
 * Tidak menimpa environment variable yang sudah diset di level server
 * (misalnya lewat Apache/Nginx atau hosting panel).
 *
 * File ini TIDAK memerlukan Composer / vlucas/phpdotenv — cukup PHP native.
 *
 * @package EOFinance
 */

if (!defined('EO_FINANCE_APP')) {
    http_response_code(403);
    exit('Akses langsung ke file konfigurasi tidak diizinkan.');
}

/**
 * Baca file .env dan set ke $_ENV + putenv() jika variabel belum ada.
 *
 * @param string $path Path absolut ke file .env
 * @return void
 */
function loadEnv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        // Tidak ada file .env — mungkin environment variable diset langsung
        // di level server (umum di hosting production). Bukan error fatal.
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Lewati baris kosong dan komentar
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        // Format wajib: KEY=VALUE
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Buang tanda kutip pembungkus jika ada: KEY="value" atau KEY='value'
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // Jangan timpa jika sudah diset di level server (server env menang)
        if (getenv($key) !== false) {
            continue;
        }

        putenv("{$key}={$value}");
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
    }
}

/**
 * Ambil nilai environment variable dengan fallback default.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function env(string $key, $default = null)
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    // Konversi string boolean/null umum ke tipe PHP asli
    return match (strtolower($value)) {
        'true', '(true)'   => true,
        'false', '(false)' => false,
        'null', '(null)'   => null,
        'empty', '(empty)' => '',
        default             => $value,
    };
}

// Muat file .env dari root project (satu level di atas folder config/)
loadEnv(dirname(__DIR__) . '/.env');
