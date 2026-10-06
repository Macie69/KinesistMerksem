<?php
/**
 * M-Physio Care – contactformulier
 * POST /api/contact.php
 *
 * 1. Controleert de invoer en weert spam (honeypot, tijdscontrole, limiet per uur)
 * 2. Slaat de aanvraag op in de SQLite-database
 * 3. Mailt de praktijk en stuurt de patiënt een bevestiging
 */

declare(strict_types=1);
define('MPHYSIO', true);
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$lang = (($_POST['lang'] ?? 'nl') === 'pl') ? 'pl' : 'nl';

$t = [
    'nl' => [
        'method'   => 'Ongeldige aanvraag.',
        'name'     => 'Vul alstublieft uw naam in.',
        'email'    => 'Vul alstublieft een geldig e-mailadres in.',
        'consent'  => 'Geef alstublieft toestemming voor het verwerken van uw gegevens.',
        'toolong'  => 'Uw bericht is te lang (maximaal 2000 tekens).',
        'rate'     => 'U heeft al meerdere aanvragen verstuurd. Probeer het later opnieuw of bel ons.',
        'server'   => 'Er is iets misgegaan. Probeer het opnieuw of neem telefonisch contact op.',
        'success'  => 'Bedankt voor uw aanvraag! We nemen zo snel mogelijk contact met u op.',
    ],
    'pl' => [
        'method'   => 'Nieprawidłowe żądanie.',
        'name'     => 'Proszę podać imię i nazwisko.',
        'email'    => 'Proszę podać prawidłowy adres e-mail.',
        'consent'  => 'Proszę wyrazić zgodę na przetwarzanie danych.',
        'toolong'  => 'Wiadomość jest za długa (maksymalnie 2000 znaków).',
        'rate'     => 'Wysłano już kilka zapytań. Spróbuj później lub zadzwoń do nas.',
        'server'   => 'Coś poszło nie tak. Spróbuj ponownie lub zadzwoń.',
        'success'  => 'Dziękujemy za zapytanie! Skontaktujemy się z Tobą jak najszybciej.',
    ],
][$lang];

function respond(bool $ok, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, $t['method'], 405);
}

// --- Spam: honeypot (verborgen veld dat mensen niet zien, bots wel invullen) ---
if (!empty($_POST['website'])) {
    respond(true, $t['success']); // doe alsof het gelukt is
}

// --- Spam: formulier binnen 3 seconden verstuurd = bot ---
$ts = (int) ($_POST['ts'] ?? 0);
if ($ts > 0 && (time() * 1000 - $ts) < 3000) {
    respond(true, $t['success']);
}

// --- Invoer ophalen en opschonen ---
$clean = static fn(string $key, int $max): string =>
    str_cut(trim(strip_tags((string) ($_POST[$key] ?? ''))), $max);

$naam     = $clean('name', 100);
$email    = $clean('email', 150);
$telefoon = $clean('phone', 30);
$bericht  = trim((string) ($_POST['message'] ?? ''));
$datum    = $clean('voorkeursdatum', 10);
$service  = $clean('service', 30);

$diensten = [
    'musculoskeletaal' => 'Musculoskeletale therapie',
    'revalidatie'      => 'Postoperatieve revalidatie',
    'sport'            => 'Sportblessures',
    'nek-kaak'         => 'Nek- & kaakpijn',
    'zwangerschap'     => 'Zwangerschapsbegeleiding',
    'preventie'        => 'Preventie & houdingsadvies',
    'dryneedling'      => 'Dry needling',
    'andere'           => 'Andere',
];
$behandeling = $diensten[$service] ?? '';

// --- Validatie ---
if (str_len($naam) < 2) {
    respond(false, $t['name'], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, $t['email'], 422);
}
if (empty($_POST['consent'])) {
    respond(false, $t['consent'], 422);
}
if (str_len($bericht) > 2000) {
    respond(false, $t['toolong'], 422);
}
$bericht = strip_tags($bericht);
if ($datum !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
    $datum = '';
}
$telefoon = preg_replace('/[^0-9+\s\/().-]/', '', $telefoon);

try {
    $pdo = db();
    $iph = ip_hash();

    // --- Limiet per uur ---
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM aanvragen WHERE ip_hash = ? AND aangemaakt_op > ?');
    $stmt->execute([$iph, date('Y-m-d H:i:s', time() - 3600)]);
    if ((int) $stmt->fetchColumn() >= (int) config()['rate_limit_per_hour']) {
        respond(false, $t['rate'], 429);
    }

    // --- Opslaan ---
    $stmt = $pdo->prepare('INSERT INTO aanvragen
        (aangemaakt_op, naam, email, telefoon, behandeling, bericht, voorkeursdatum, taal, ip_hash)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([date('Y-m-d H:i:s'), $naam, $email, $telefoon, $behandeling, $bericht, $datum, $lang, $iph]);
    $id = (int) $pdo->lastInsertId();
} catch (Throwable $e) {
    error_log('M-Physio databasefout: ' . $e->getMessage());
    respond(false, $t['server'], 500);
}

// --- E-mails versturen (teksten staan in api/mails.php) ---
require __DIR__ . '/mails.php';

$aanvraag = [
    'id' => $id, 'naam' => $naam, 'email' => $email, 'telefoon' => $telefoon,
    'behandeling' => $behandeling, 'bericht' => $bericht, 'datum' => $datum, 'lang' => $lang,
];

[$onderwerp, $html, $tekst] = mail_voor_praktijk($aanvraag);
$okPraktijk = send_mail(config()['practice_email'], $onderwerp, $html, $tekst, $email, nice_name($naam));

$okPatient = null;
if (config()['send_confirmation']) {
    [$onderwerp, $html, $tekst] = mail_voor_patient($aanvraag);
    $okPatient = send_mail($email, $onderwerp, $html, $tekst, config()['practice_email'], config()['from_name']);
}

// Mailstatus bijhouden (de aanvraag is hoe dan ook veilig opgeslagen)
$mailStatus = ($okPraktijk ? 'praktijk:ok' : 'praktijk:mislukt')
    . ($okPatient === null ? '' : ($okPatient ? ', patiënt:ok' : ', patiënt:mislukt'));
db()->prepare('UPDATE aanvragen SET mail_status = ? WHERE id = ?')->execute([$mailStatus, $id]);

respond(true, $t['success']);
