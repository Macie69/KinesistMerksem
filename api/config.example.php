<?php
/**
 * KOPIEER DIT BESTAND NAAR api/config.php EN VUL HET IN.
 *
 * config.php staat in .gitignore en komt dus NOOIT op GitHub.
 * Zet hier nooit echte wachtwoorden in: dit voorbeeldbestand is openbaar.
 */

return [
    // Waar nieuwe aanvragen naartoe gemaild worden
    'practice_email' => 'info@kinesistmerksem.com',

    // Afzender van alle mails (moet een mailbox op je eigen domein zijn)
    'from_email' => 'info@kinesistmerksem.com',
    'from_name'  => 'M-Physio Care',

    // SMTP van je Hostinger-mailbox (aanbevolen: mails komen niet in spam).
    // Laat 'password' leeg om de gewone PHP mail() van de server te gebruiken.
    'smtp' => [
        'host'     => 'smtp.hostinger.com',
        'port'     => 465,
        'secure'   => 'ssl',            // 'ssl' bij poort 465, 'tls' bij poort 587
        'username' => 'info@kinesistmerksem.com',
        'password' => '',               // <-- wachtwoord van de mailbox
    ],

    // Wachtwoord voor de beheerpagina (https://kinesistmerksem.com/beheer/)
    // Kies iets sterks van minstens 12 tekens.
    'admin_password' => '',

    // Willekeurige geheime tekst (bv. 40 willekeurige tekens), gebruikt om IP-adressen te hashen
    'secret_salt' => 'verander-dit-in-een-lange-willekeurige-tekst',

    // Database: SQLite-bestand in de beschermde map /data
    'db_path' => __DIR__ . '/../data/aanvragen.sqlite',

    // Spambeperking: maximaal aantal aanvragen per bezoeker per uur
    'rate_limit_per_hour' => 5,

    // Bevestigingsmail naar de patiënt sturen?
    'send_confirmation' => true,
];
