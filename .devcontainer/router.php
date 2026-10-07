<?php
/**
 * Router voor de PHP-testserver in Codespaces.
 * Bootst de .htaccess-regels van Hostinger na: nette URL's en afgeschermde mappen.
 */
$pad = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Afgeschermde bestanden en mappen (net als op de echte server)
if (preg_match('#^/(data/|api/lib/|\.git|\.devcontainer/|\.github/)#', $pad)
    || preg_match('#^/api/(bootstrap|config|config\.example|mails)\.php$#', $pad)) {
    http_response_code(403);
    exit('403 – Geen toegang');
}

// Nette URL's (zelfde regels als .htaccess op Hostinger)
$redirects = [
    '/index.html' => '/', '/index-pl.html' => '/pl/',
    '/nl/index.html' => '/nl/', '/pl/index.html' => '/pl/',
    '/nl' => '/nl/', '/pl' => '/pl/',
];
if (isset($redirects[$pad])) {
    header('Location: ' . $redirects[$pad], true, 301);
    exit;
}

// Hoofdpagina -> juiste taal: gekozen taal (cookie), anders browsertaal, anders Nederlands
if ($pad === '/') {
    $taal = $_COOKIE['mp_lang'] ?? '';
    if (!in_array($taal, ['nl', 'pl'], true)) {
        $taal = stripos($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 'pl') === 0 ? 'pl' : 'nl';
    }
    header('Location: /' . $taal . '/', true, 302);
    exit;
}

// /beheer/ -> beheer/index.php
if ($pad === '/beheer' || $pad === '/beheer/') {
    if ($pad === '/beheer') {
        header('Location: /beheer/', true, 301);
        exit;
    }
    chdir(__DIR__ . '/../beheer');
    require __DIR__ . '/../beheer/index.php';
    return true;
}

return false; // gewone bestanden (html, css, js, img, api/contact.php) zelf laten serveren
