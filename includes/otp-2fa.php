<?php
/**
 * EO Finance — Helper OTP 2FA.
 *
 * File ini HANYA berisi fungsi (tidak ada kode eksekusi tingkat atas),
 * sehingga aman untuk di-require dari halaman mana pun (login, verifikasi,
 * kirim ulang, dll) tanpa memicu logika proses lain secara tidak sengaja.
 *
 * @package EOFinance
 */

if (!function_exists('kirim_otp_2fa')) {
    function kirim_otp_2fa(mysqli $conn, int $userId): void
    {
        // Hapus OTP lama yang belum dipakai untuk user ini supaya tidak menumpuk
        $stmtDel = $conn->prepare(
            'DELETE FROM otp_2fa WHERE user_id = ? AND is_used = 0'
        );
        $stmtDel->bind_param('i', $userId);
        $stmtDel->execute();
        $stmtDel->close();

        // Buat kode 6 digit acak
        $kode      = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiredAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $ip        = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmtIns = $conn->prepare(
            'INSERT INTO otp_2fa (user_id, kode_otp, expired_at, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmtIns->bind_param('isss', $userId, $kode, $expiredAt, $ip);
        $stmtIns->execute();
        $stmtIns->close();

        // TODO: Implementasikan pengiriman email dengan PHPMailer atau SwiftMailer.
        // Contoh menggunakan PHPMailer:
        //
        // use PHPMailer\PHPMailer\PHPMailer;
        // $mail = new PHPMailer(true);
        // $mail->isSMTP();
        // $mail->Host       = 'smtp.example.com';
        // $mail->SMTPAuth   = true;
        // $mail->Username   = 'noreply@example.com';
        // $mail->Password   = 'your_smtp_password';
        // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // $mail->Port       = 587;
        // $mail->setFrom('noreply@example.com', APP_NAME);
        // $mail->addAddress($userEmail);
        // $mail->Subject = '[' . APP_NAME . '] Kode Verifikasi 2FA';
        // $mail->Body    = "Kode 2FA kamu: {$kode}\nBerlaku 10 menit.";
        // $mail->send();
        //
        // Sebelum email dikonfigurasi, catat kode ke server log saja (JANGAN ke flash/browser).
        error_log('[2FA] Kode untuk user_id ' . $userId . ' telah di-generate. Konfigurasikan SMTP untuk pengiriman email.');
    }
}