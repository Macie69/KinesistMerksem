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

        $logo = __DIR__ . '/../img/logo-email.png';
        if (is_file($logo)) {
            $mail->addEmbeddedImage($logo, 'mphysio-logo', 'm-physio-care.png', PHPMailer::ENCODING_BASE64, 'image/png');
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

/**
 * Naam netjes tonen: "jan peter" -> "Jan Peter".
 * Namen die al hoofdletters bevatten (bv. "van der Berg") blijven ongewijzigd.
 */
function nice_name(string $naam): string
{
    $lower = function_exists('mb_strtolower') ? mb_strtolower($naam, 'UTF-8') : strtolower($naam);
    if ($naam !== $lower) {
        return $naam;
    }
    return function_exists('mb_convert_case') ? mb_convert_case($naam, MB_CASE_TITLE, 'UTF-8') : ucwords($naam);
}

function first_name(string $naam): string
{
    return explode(' ', trim(nice_name($naam)))[0];
}

/** Grijs blok met gegevens (label links, waarde rechts). */
function mail_details(array $rijen): string
{
    $html = '';
    foreach ($rijen as $label => $waarde) {
        if ($waarde === '' || $waarde === null) {
            continue;
        }
        $html .= '<tr>'
            . '<td style="padding:7px 16px 7px 0;font-size:13px;color:#A08A85;width:40%;vertical-align:top;white-space:nowrap;">' . h($label) . '</td>'
            . '<td style="padding:7px 0;font-size:14px;color:#3A3533;vertical-align:top;">' . nl2br(h((string) $waarde)) . '</td>'
            . '</tr>';
    }
    if ($html === '') {
        return '';
    }
    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        . 'style="background:#FBF6F3;border:1px solid #F2E7E2;border-radius:10px;margin:28px 0 4px;">'
        . '<tr><td style="padding:16px 22px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . $html . '</table></td></tr></table>';
}

/**
 * Opmaak van e-mails: rustig wit kaartje, rond icoon, gecentreerde titel en één duidelijke knop.
 *
 * Opties: icon, title, intro (html), body (html), button [label, url],
 *         link [label, url], footnote, preheader
 */
function mail_layout(array $o): string
{
    // Huisstijl M-Physio Care
    $roze    = '#BB8588';   // hoofdkleur (knop)
    $donker  = '#9A6B6E';   // links, accenten
    $perzik  = '#F5E6E0';   // badge, zachte vlakken
    $achter  = '#F7F0EC';   // achtergrond van de mail
    $rand    = '#EEDFD8';
    $tekst   = '#3A3533';
    $grijs   = '#6B6461';
    $licht   = '#A89C97';

    $logoSrc = $o['logo_src'] ?? 'cid:mphysio-logo';

    $preheader = '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">'
        . h($o['preheader'] ?? '') . '</div>';

    $logo = '<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:0 auto;"><tr><td align="center">'
        . '<img src="' . h($logoSrc) . '" width="120" height="120" alt="M-Physio Care" '
        . 'style="display:block;width:120px;height:120px;border:0;outline:none;text-decoration:none;">'
        . '</td></tr></table>';

    $badge = '';
    if (!empty($o['badge'])) {
        $badge = '<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:18px auto 0;"><tr>'
            . '<td style="background:' . $perzik . ';border-radius:20px;padding:6px 16px;font-size:12px;font-weight:bold;'
            . 'letter-spacing:0.4px;color:' . $donker . ';font-family:Arial,Helvetica,sans-serif;">'
            . h($o['badge']) . '</td></tr></table>';
    }

    $button = '';
    if (!empty($o['button'])) {
        [$label, $url] = $o['button'];
        $button = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:32px 0 0;"><tr>'
            . '<td align="center" bgcolor="' . $roze . '" style="background:' . $roze . ';border-radius:8px;">'
            . '<a href="' . h($url) . '" style="display:block;padding:16px 24px;font-size:15px;font-weight:bold;'
            . 'color:#ffffff;text-decoration:none;font-family:Arial,Helvetica,sans-serif;border-radius:8px;">'
            . h($label) . '</a></td></tr></table>';
    }

    $link = '';
    if (!empty($o['link'])) {
        [$label, $url] = $o['link'];
        $link = '<p style="margin:16px 0 0;text-align:center;font-size:13px;">'
            . '<a href="' . h($url) . '" style="color:' . $donker . ';text-decoration:underline;">' . h($label) . '</a></p>';
    }

    $footnote = '';
    if (!empty($o['footnote'])) {
        $footnote = '<table role="presentation" align="center" cellpadding="0" cellspacing="0" style="margin:36px auto 18px;">'
            . '<tr><td width="56" style="border-top:1px solid ' . $rand . ';font-size:0;line-height:0;">&nbsp;</td></tr></table>'
            . '<p style="margin:0;text-align:center;font-size:12px;line-height:1.6;color:' . $licht . ';font-style:italic;">'
            . h($o['footnote']) . '</p>';
    }

    return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="color-scheme" content="light"><title>' . h($o['title'] ?? '') . '</title>'
        . '<style>@media only screen and (max-width:480px){.mp-kaart{padding:30px 22px 28px !important;}'
        . '.mp-titel{font-size:23px !important;}}</style></head>'
        . '<body style="margin:0;padding:0;background:' . $achter . ';-webkit-text-size-adjust:100%;">'
        . $preheader
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $achter . ';">'
        . '<tr><td align="center" style="padding:40px 16px;font-family:Arial,Helvetica,sans-serif;">'

        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
        . 'style="max-width:520px;background:#ffffff;border:1px solid ' . $rand . ';border-top:4px solid ' . $roze . ';border-radius:12px;">'
        . '<tr><td class="mp-kaart" style="padding:36px 40px 34px;">'
        . $logo
        . $badge
        . '<h1 class="mp-titel" style="margin:22px 0 0;text-align:center;font-family:Georgia,\'Times New Roman\',serif;font-size:26px;'
        . 'font-weight:normal;color:' . $tekst . ';line-height:1.3;">' . h($o['title'] ?? '') . '</h1>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:22px 0;">'
        . '<tr><td style="border-top:1px solid ' . $rand . ';font-size:0;line-height:0;">&nbsp;</td></tr></table>'
        . '<p style="margin:0;text-align:center;font-size:15px;line-height:1.7;color:' . $grijs . ';">' . ($o['intro'] ?? '') . '</p>'
        . ($o['body'] ?? '')
        . $button
        . $link
        . $footnote
        . '</td></tr></table>'

        . '<p style="margin:24px 0 0;font-size:12px;line-height:1.7;color:' . $licht . ';">'
        . 'M-Physio Care · Laarsebaan 44, 2170 Antwerpen<br>'
        . '<a href="tel:+32483182663" style="color:' . $licht . ';text-decoration:none;">+32 483 18 26 63</a> · '
        . '<a href="https://kinesistmerksem.com" style="color:' . $licht . ';text-decoration:underline;">kinesistmerksem.com</a></p>'

        . '</td></tr></table></body></html>';
}
