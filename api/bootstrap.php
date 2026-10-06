<?php
/**
 * M-Physio Care – gedeelde basis voor formulier en beheerpagina.
 * Laadt de configuratie, opent de SQLite-database en verstuurt e-mails.
 */

declare(strict_types=1);

if (!defined('MPHYSIO')) {
    http_response_code(403);
    exit;
}

require __DIR__ . '/lib/PHPMailer/Exception.php';
require __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

date_default_timezone_set('Europe/Brussels');

function config(): array
{
    static $config = null;
    if ($config === null) {
        $file = __DIR__ . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            exit('config.php ontbreekt. Kopieer api/config.example.php naar api/config.php en vul het in.');
        }
        $config = require $file;
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $path = config()['db_path'];
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL');

    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS aanvragen (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        aangemaakt_op TEXT    NOT NULL,
        naam          TEXT    NOT NULL,
        email         TEXT    NOT NULL,
        telefoon      TEXT,
        behandeling   TEXT,
        bericht       TEXT,
        voorkeursdatum TEXT,
        taal          TEXT    NOT NULL DEFAULT 'nl',
        status        TEXT    NOT NULL DEFAULT 'nieuw',
        mail_status   TEXT,
        ip_hash       TEXT
    )
    SQL);
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ip_tijd ON aanvragen (ip_hash, aangemaakt_op)');

    return $pdo;
}

/** IP-adres gehasht opslaan (privacy/GDPR): alleen bruikbaar voor spambeperking. */
function ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return hash('sha256', $ip . '|' . config()['secret_salt']);
}

/** Tekstfuncties die ook werken als de mbstring-extensie ontbreekt. */
function str_len(string $s): int
{
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen(utf8_decode_safe($s));
}

function str_cut(string $s, int $max): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($s, 0, $max, 'UTF-8');
    }
    return preg_match('/^.{0,' . $max . '}/us', $s, $m) ? $m[0] : substr($s, 0, $max);
}

function utf8_decode_safe(string $s): string
{
    return preg_replace('/[\x{80}-\x{10FFFF}]/u', '?', $s) ?? $s;
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Verstuurt een e-mail. Gebruikt SMTP (Hostinger-mailbox) als er een wachtwoord
 * is ingevuld, anders de ingebouwde PHP mail()-functie van de server.
 */
function send_mail(string $to, string $subject, string $html, string $text, ?string $replyTo = null, ?string $replyName = null): bool
{
    $c = config();
    $mail = new PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';

        if (!empty($c['smtp']['password'])) {
            $mail->isSMTP();
            $mail->Host       = $c['smtp']['host'];
            $mail->Port       = (int) $c['smtp']['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $c['smtp']['username'];
            $mail->Password   = $c['smtp']['password'];
            $mail->SMTPSecure = $c['smtp']['secure'] === 'tls'
                ? PHPMailer::ENCRYPTION_STARTTLS
                : PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->isMail();
        }

        $mail->setFrom($c['from_email'], $c['from_name']);
        $mail->addAddress($to);
        if ($replyTo) {
            $mail->addReplyTo($replyTo, $replyName ?? '');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;

        return $mail->send();
    } catch (Throwable $e) {
        error_log('M-Physio mailfout: ' . $mail->ErrorInfo);
        return false;
    }
}

/** Opmaak van e-mails in de huisstijl. */
function mail_layout(string $title, string $body): string
{
    return '<!DOCTYPE html><html><body style="margin:0;background:#F8F5F2;font-family:Arial,Helvetica,sans-serif;color:#2D2D2D;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#F8F5F2;padding:24px 0;"><tr><td align="center">'
        . '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;">'
        . '<tr><td style="background:#BB8588;padding:20px 28px;color:#ffffff;font-size:20px;font-weight:bold;">M-Physio Care</td></tr>'
        . '<tr><td style="padding:28px;">'
        . '<h2 style="margin:0 0 16px;font-size:20px;color:#9A6B6E;">' . h($title) . '</h2>'
        . $body
        . '</td></tr>'
        . '<tr><td style="padding:16px 28px;background:#F5E6E0;font-size:12px;color:#666666;">'
        . 'M-Physio Care · Laarsebaan 44, 2170 Antwerpen · +32 483 18 26 63 · '
        . '<a href="https://kinesistmerksem.com" style="color:#9A6B6E;">kinesistmerksem.com</a>'
        . '</td></tr></table></td></tr></table></body></html>';
}
