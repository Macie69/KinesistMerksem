<?php
/**
 * M-Physio Care – inhoud van de e-mails.
 * Pas hier de teksten aan. De opmaak zelf staat in bootstrap.php (mail_layout).
 *
 * $a = ['id', 'naam', 'email', 'telefoon', 'behandeling', 'bericht', 'datum', 'lang']
 * Elke functie geeft terug: [onderwerp, html, platte tekst]
 */

declare(strict_types=1);

if (!defined('MPHYSIO')) {
    http_response_code(403);
    exit;
}

/** Mail naar de praktijk: er is een nieuwe aanvraag. */
function mail_voor_praktijk(array $a, array $layout = []): array
{
    $naam = nice_name($a['naam']);
    $datum = $a['datum'] ? date('d/m/Y', strtotime($a['datum'])) : '';
    $onderwerp = 'Nieuwe aanvraag: ' . $naam . ($a['behandeling'] ? ' – ' . $a['behandeling'] : '');

    $antwoordUrl = 'mailto:' . rawurlencode($a['email'])
        . '?subject=' . rawurlencode($a['lang'] === 'pl' ? 'Twoja wizyta w M-Physio Care' : 'Uw afspraak bij M-Physio Care');

    $html = mail_layout($layout + [
        'preheader' => $naam . ' wil graag een afspraak maken.',
        'badge'     => 'NIEUWE AANVRAAG #' . $a['id'],
        'title'     => 'Nieuwe afspraakaanvraag',
        'intro'     => '<strong style="color:#3A3533;">' . h($naam) . '</strong> wil graag een afspraak maken.'
                     . ($a['lang'] === 'pl' ? '<br>Deze patiënt spreekt <strong style="color:#3A3533;">Pools</strong>.' : ''),
        'body'      => mail_details([
            'E-mail'         => $a['email'],
            'Telefoon'       => $a['telefoon'],
            'Behandeling'    => $a['behandeling'],
            'Voorkeursdatum' => $datum,
            'Bericht'        => $a['bericht'],
        ]),
        'button'    => ['Beantwoord ' . first_name($naam), $antwoordUrl],
        'link'      => ['Bekijk alle aanvragen', 'https://kinesistmerksem.com/beheer/'],
        'footnote'  => 'Deze aanvraag kwam binnen via het contactformulier op kinesistmerksem.com.',
    ]);

    $tekst = "Nieuwe afspraakaanvraag #{$a['id']}\n\n"
        . "Naam: $naam\nE-mail: {$a['email']}\nTelefoon: {$a['telefoon']}\n"
        . "Behandeling: {$a['behandeling']}\nVoorkeursdatum: $datum\n\nBericht:\n{$a['bericht']}\n";

    return [$onderwerp, $html, $tekst];
}

/** Bevestiging naar de patiënt, in zijn eigen taal. */
function mail_voor_patient(array $a, array $layout = []): array
{
    $voornaam = first_name($a['naam']);
    $datum = $a['datum'] ? date('d/m/Y', strtotime($a['datum'])) : '';

    if ($a['lang'] === 'pl') {
        $a['behandeling'] = [
            'Musculoskeletale therapie'  => 'Terapia mięśniowo-szkieletowa',
            'Postoperatieve revalidatie' => 'Rehabilitacja pooperacyjna',
            'Sportblessures'             => 'Kontuzje sportowe',
            'Nek- & kaakpijn'            => 'Ból szyi i szczęki',
            'Zwangerschapsbegeleiding'   => 'Opieka w ciąży',
            'Preventie & houdingsadvies' => 'Profilaktyka i ergonomia',
            'Dry needling'               => 'Suche igłowanie',
            'Andere'                     => 'Inne',
        ][$a['behandeling']] ?? $a['behandeling'];

        $t = [
            'onderwerp' => 'Otrzymaliśmy Twoje zapytanie – M-Physio Care',
            'badge'     => '✓ ZAPYTANIE OTRZYMANE',
            'titel'     => 'Dziękujemy, ' . $voornaam . '!',
            'intro'     => 'Otrzymaliśmy Twoje zapytanie o wizytę. Skontaktujemy się z Tobą jak najszybciej, '
                         . 'aby ustalić dogodny termin.',
            'behandeling' => 'Zabieg',
            'datum'     => 'Preferowana data',
            'knop'      => 'Zadzwoń: +32 483 18 26 63',
            'link'      => 'Odwiedź naszą stronę',
            'url'       => 'https://kinesistmerksem.com/pl/',
            'voetnoot'  => 'Jeśli to nie Ty wysłałeś to zapytanie, możesz zignorować tę wiadomość.',
            'groet'     => "Pozdrawiam serdecznie,\nMarta – M-Physio Care",
        ];
    } else {
        $t = [
            'onderwerp' => 'We hebben uw aanvraag ontvangen – M-Physio Care',
            'badge'     => '✓ AANVRAAG ONTVANGEN',
            'titel'     => 'Bedankt, ' . $voornaam . '!',
            'intro'     => 'We hebben uw afspraakaanvraag goed ontvangen. We nemen zo snel mogelijk contact met u op '
                         . 'om een geschikt moment af te spreken.',
            'behandeling' => 'Behandeling',
            'datum'     => 'Voorkeursdatum',
            'knop'      => 'Bel ons: +32 483 18 26 63',
            'link'      => 'Bezoek onze website',
            'url'       => 'https://kinesistmerksem.com/nl/',
            'voetnoot'  => 'Heeft u deze aanvraag niet verstuurd? Dan kunt u deze e-mail gewoon negeren.',
            'groet'     => "Met vriendelijke groet,\nMarta – M-Physio Care",
        ];
    }

    $html = mail_layout($layout + [
        'preheader' => $t['intro'],
        'badge'     => $t['badge'],
        'title'     => $t['titel'],
        'intro'     => h($t['intro']),
        'body'      => mail_details([
            $t['behandeling'] => $a['behandeling'],
            $t['datum']       => $datum,
        ]) . '<p style="margin:28px 0 0;text-align:center;font-size:15px;line-height:1.6;color:#6B6461;">'
           . nl2br(h($t['groet'])) . '</p>',
        'button'    => [$t['knop'], 'tel:+32483182663'],
        'link'      => [$t['link'], $t['url']],
        'footnote'  => $t['voetnoot'],
    ]);

    $tekst = "{$t['titel']}\n\n{$t['intro']}\n\n{$t['groet']}\n\nM-Physio Care\nLaarsebaan 44, 2170 Antwerpen\n+32 483 18 26 63";

    return [$t['onderwerp'], $html, $tekst];
}
