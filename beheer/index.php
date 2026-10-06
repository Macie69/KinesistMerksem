<?php
/**
 * M-Physio Care – beheerpagina
 * https://kinesistmerksem.com/beheer/
 * Overzicht van alle aanvragen uit het contactformulier.
 */

declare(strict_types=1);
define('MPHYSIO', true);
require __DIR__ . '/../api/bootstrap.php';

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/beheer/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow');

$fout = '';
$wachtwoord = (string) (config()['admin_password'] ?? '');

// ---------- Uitloggen ----------
if (isset($_GET['uitloggen'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

// ---------- Inloggen ----------
if (empty($_SESSION['ingelogd'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wachtwoord'])) {
        if ($wachtwoord === '' || str_len($wachtwoord) < 8) {
            $fout = 'Er is nog geen (sterk genoeg) wachtwoord ingesteld in api/config.php.';
        } elseif (hash_equals($wachtwoord, (string) $_POST['wachtwoord'])) {
            session_regenerate_id(true);
            $_SESSION['ingelogd'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: ./');
            exit;
        } else {
            sleep(2); // vertraagt het raden van wachtwoorden
            $fout = 'Onjuist wachtwoord.';
        }
    }
    toon_login($fout);
    exit;
}

$csrf = $_SESSION['csrf'];
$pdo = db();

// ---------- Acties ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Ongeldige sessie. Vernieuw de pagina.');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $actie = $_POST['actie'] ?? '';

    if ($actie === 'afgehandeld') {
        $pdo->prepare("UPDATE aanvragen SET status = 'afgehandeld' WHERE id = ?")->execute([$id]);
    } elseif ($actie === 'heropen') {
        $pdo->prepare("UPDATE aanvragen SET status = 'nieuw' WHERE id = ?")->execute([$id]);
    } elseif ($actie === 'verwijder') {
        $pdo->prepare('DELETE FROM aanvragen WHERE id = ?')->execute([$id]);
    }
    header('Location: ./' . (isset($_GET['filter']) ? '?filter=' . urlencode((string) $_GET['filter']) : ''));
    exit;
}

// ---------- CSV-export ----------
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="aanvragen-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // zodat Excel de accenten goed toont
    fputcsv($out, ['ID', 'Datum', 'Naam', 'E-mail', 'Telefoon', 'Behandeling', 'Voorkeursdatum', 'Taal', 'Status', 'Bericht'], ';');
    foreach ($pdo->query('SELECT * FROM aanvragen ORDER BY id DESC') as $r) {
        fputcsv($out, [$r['id'], $r['aangemaakt_op'], $r['naam'], $r['email'], $r['telefoon'], $r['behandeling'],
            $r['voorkeursdatum'], $r['taal'], $r['status'], $r['bericht']], ';');
    }
    exit;
}

// ---------- Overzicht ----------
$filter = $_GET['filter'] ?? 'nieuw';
if (!in_array($filter, ['nieuw', 'afgehandeld', 'alle'], true)) {
    $filter = 'nieuw';
}
$sql = 'SELECT * FROM aanvragen' . ($filter === 'alle' ? '' : ' WHERE status = :s') . ' ORDER BY id DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($filter === 'alle' ? [] : [':s' => $filter]);
$rijen = $stmt->fetchAll();

$telling = $pdo->query("SELECT status, COUNT(*) AS n FROM aanvragen GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$aantalNieuw = (int) ($telling['nieuw'] ?? 0);
$aantalAf = (int) ($telling['afgehandeld'] ?? 0);

pagina_kop('Aanvragen');
?>
<header class="top">
    <div>
        <h1>Aanvragen</h1>
        <p class="sub">Contactformulier kinesistmerksem.com</p>
    </div>
    <div class="top-acties">
        <a class="knop licht" href="?export=1">Exporteer CSV</a>
        <a class="knop licht" href="?uitloggen=1">Uitloggen</a>
    </div>
</header>

<nav class="tabs">
    <a href="?filter=nieuw" class="<?= $filter === 'nieuw' ? 'actief' : '' ?>">Nieuw <span><?= $aantalNieuw ?></span></a>
    <a href="?filter=afgehandeld" class="<?= $filter === 'afgehandeld' ? 'actief' : '' ?>">Afgehandeld <span><?= $aantalAf ?></span></a>
    <a href="?filter=alle" class="<?= $filter === 'alle' ? 'actief' : '' ?>">Alle <span><?= $aantalNieuw + $aantalAf ?></span></a>
</nav>

<?php if (!$rijen): ?>
    <p class="leeg">Geen aanvragen in deze lijst.</p>
<?php endif; ?>

<?php foreach ($rijen as $r): ?>
    <article class="kaart <?= $r['status'] === 'afgehandeld' ? 'klaar' : '' ?>">
        <div class="kaart-kop">
            <div>
                <h2><?= h($r['naam']) ?> <?php if ($r['taal'] === 'pl'): ?><span class="label">PL</span><?php endif; ?></h2>
                <p class="meta">#<?= (int) $r['id'] ?> · <?= h(date('d/m/Y H:i', strtotime($r['aangemaakt_op']))) ?>
                    <?php if ($r['mail_status'] && str_contains($r['mail_status'], 'mislukt')): ?>
                        · <span class="waarschuwing">mail niet verzonden</span>
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($r['behandeling']): ?><span class="label groot"><?= h($r['behandeling']) ?></span><?php endif; ?>
        </div>

        <dl>
            <dt>E-mail</dt><dd><a href="mailto:<?= h($r['email']) ?>"><?= h($r['email']) ?></a></dd>
            <dt>Telefoon</dt><dd><?= $r['telefoon'] ? '<a href="tel:' . h(preg_replace('/\s+/', '', $r['telefoon'])) . '">' . h($r['telefoon']) . '</a>' : '–' ?></dd>
            <dt>Voorkeur</dt><dd><?= $r['voorkeursdatum'] ? h(date('d/m/Y', strtotime($r['voorkeursdatum']))) : '–' ?></dd>
        </dl>
        <?php if ($r['bericht']): ?><p class="bericht"><?= nl2br(h($r['bericht'])) ?></p><?php endif; ?>

        <div class="kaart-acties">
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <?php if ($r['status'] === 'afgehandeld'): ?>
                    <button name="actie" value="heropen" class="knop licht">Terug naar nieuw</button>
                <?php else: ?>
                    <button name="actie" value="afgehandeld" class="knop">✓ Afgehandeld</button>
                <?php endif; ?>
                <button name="actie" value="verwijder" class="knop gevaar"
                        onclick="return confirm('Deze aanvraag definitief verwijderen?')">Verwijderen</button>
            </form>
        </div>
    </article>
<?php endforeach; ?>
<?php
pagina_voet();

// ======================= Weergave-hulpfuncties =======================

function toon_login(string $fout): void
{
    pagina_kop('Inloggen');
    ?>
    <form method="post" class="login">
        <h1>Beheer</h1>
        <p class="sub">M-Physio Care · aanvragen</p>
        <?php if ($fout): ?><p class="fout"><?= h($fout) ?></p><?php endif; ?>
        <label for="ww">Wachtwoord</label>
        <input type="password" id="ww" name="wachtwoord" autocomplete="current-password" required autofocus>
        <button class="knop">Inloggen</button>
    </form>
    <?php
    pagina_voet();
}

function pagina_kop(string $titel): void
{
    ?><!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= h($titel) ?> · M-Physio Care</title>
    <style>
        :root { --p:#BB8588; --pd:#9A6B6E; --pl:#F5E6E0; --bg:#F8F5F2; --t:#2D2D2D; --m:#777; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--t); font:15px/1.5 -apple-system, "Segoe UI", Arial, sans-serif; }
        main { max-width:860px; margin:0 auto; padding:32px 20px 60px; }
        h1 { margin:0; font-size:28px; font-weight:600; }
        h2 { margin:0; font-size:18px; }
        .sub { margin:2px 0 0; color:var(--m); font-size:14px; }
        .top { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-bottom:24px; }
        .top-acties { display:flex; gap:8px; }
        .knop { display:inline-block; border:0; background:var(--p); color:#fff; padding:9px 16px; border-radius:50px;
                font:inherit; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; }
        .knop:hover { background:var(--pd); }
        .knop.licht { background:#fff; color:var(--t); border:1px solid #ddd; }
        .knop.licht:hover { border-color:var(--p); color:var(--pd); }
        .knop.gevaar { background:transparent; color:#b3261e; }
        .knop.gevaar:hover { background:#fdecea; }
        .tabs { display:flex; gap:6px; margin-bottom:20px; flex-wrap:wrap; }
        .tabs a { padding:8px 14px; border-radius:50px; text-decoration:none; color:var(--t); background:#fff; border:1px solid #e5e0db; font-size:14px; }
        .tabs a span { background:var(--pl); color:var(--pd); border-radius:20px; padding:1px 8px; margin-left:4px; font-size:12px; font-weight:600; }
        .tabs a.actief { background:var(--p); color:#fff; border-color:var(--p); }
        .tabs a.actief span { background:rgba(255,255,255,.25); color:#fff; }
        .kaart { background:#fff; border-radius:14px; padding:20px 22px; margin-bottom:14px; box-shadow:0 2px 10px rgba(0,0,0,.05); border-left:4px solid var(--p); }
        .kaart.klaar { border-left-color:#c9c4bd; opacity:.8; }
        .kaart-kop { display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; }
        .meta { margin:2px 0 0; color:var(--m); font-size:13px; }
        .label { background:var(--pl); color:var(--pd); font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; vertical-align:middle; }
        .label.groot { font-size:12px; padding:4px 12px; align-self:flex-start; }
        .waarschuwing { color:#b3261e; font-weight:600; }
        dl { display:grid; grid-template-columns:auto 1fr; gap:4px 16px; margin:14px 0 0; font-size:14px; }
        dt { color:var(--m); }
        dd { margin:0; word-break:break-word; }
        dd a { color:var(--pd); }
        .bericht { background:var(--bg); padding:12px 14px; border-radius:10px; margin:14px 0 0; font-size:14px; white-space:normal; }
        .kaart-acties { margin-top:14px; }
        .kaart-acties form { display:flex; gap:6px; flex-wrap:wrap; }
        .leeg { text-align:center; color:var(--m); padding:40px 0; }
        .login { max-width:360px; margin:12vh auto 0; background:#fff; padding:32px; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.07); }
        .login label { display:block; margin:22px 0 6px; font-size:14px; font-weight:600; }
        .login input { width:100%; padding:11px 14px; border:1px solid #ddd; border-radius:10px; font:inherit; margin-bottom:16px; }
        .login input:focus { outline:2px solid var(--pl); border-color:var(--p); }
        .login .knop { width:100%; padding:12px; }
        .fout { background:#fdecea; color:#b3261e; padding:10px 12px; border-radius:10px; font-size:14px; margin:16px 0 0; }
    </style>
</head>
<body><main>
<?php
}

function pagina_voet(): void
{
    echo "</main></body></html>";
}
