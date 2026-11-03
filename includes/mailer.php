<?php
/**
 * GigaChefAI — отправка email без Composer/PHPMailer.
 * Пытается SMTP (если задан SMTP_HOST), иначе — встроенный mail().
 */
declare(strict_types=1);
require_once __DIR__ . '/../config/yandex_ai.php';

function send_mail(string $to, string $subject, string $htmlBody): bool
{
    if (defined('SMTP_HOST') && SMTP_HOST !== '') {
        return smtp_send($to, $subject, $htmlBody);
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . ">\r\n";
    $encoded  = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $encoded, $htmlBody, $headers);
}

function smtp_send(string $to, string $subject, string $htmlBody): bool
{
    $ctx  = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $host = (SMTP_PORT == 465 ? 'ssl://' : '') . SMTP_HOST;
    $fp   = @stream_socket_client("$host:" . SMTP_PORT, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) return false;

    $read  = fn() => fgets($fp, 512);
    $write = fn($cmd) => fputs($fp, $cmd . "\r\n");

    $read();
    $write('EHLO cookai'); $read();
    if (SMTP_PORT == 587) { $write('STARTTLS'); $read(); stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $write('EHLO cookai'); $read(); }
    $write('AUTH LOGIN'); $read();
    $write(base64_encode(SMTP_USER)); $read();
    $write(base64_encode(SMTP_PASS)); $read();
    $write('MAIL FROM: <' . SMTP_FROM . '>'); $read();
    $write('RCPT TO: <' . $to . '>'); $read();
    $write('DATA'); $read();

    $data  = 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    $data .= 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM . ">\r\n";
    $data .= 'To: <' . $to . ">\r\n";
    $data .= "MIME-Version: 1.0\r\n";
    $data .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $data .= $htmlBody . "\r\n.";
    $write($data); $read();
    $write('QUIT');
    fclose($fp);
    return true;
}