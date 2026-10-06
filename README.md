# KinesistMerksem

Website van **M-Physio Care**, kinesitherapiepraktijk in Merksem (Antwerpen).

🌐 Live: [kinesistmerksem.com](https://kinesistmerksem.com) · 🇵🇱 [kinesistmerksem.com/pl/](https://kinesistmerksem.com/pl/)

## Structuur

| Pad | Inhoud |
|-----|--------|
| `index.html` | Nederlandse website → `kinesistmerksem.com/` |
| `pl/index.html` | Poolse website → `kinesistmerksem.com/pl/` |
| `css/`, `js/`, `img/` | Styling, scripts, afbeeldingen |
| `api/contact.php` | Contactformulier: opslaan in database + e-mails |
| `api/config.example.php` | Voorbeeld-instellingen (kopiëren naar `config.php`) |
| `beheer/` | Beheerpagina om aanvragen te bekijken (met wachtwoord) |
| `data/` | SQLite-database met aanvragen (beschermd, niet op GitHub) |
| `.htaccess` | Nette URL's, HTTPS, beveiliging |

## Contactformulier

Volledig eigen systeem, zonder externe dienst, zonder limiet en zonder kosten:

- elke aanvraag wordt opgeslagen in een eigen database (`data/aanvragen.sqlite`)
- de praktijk krijgt een e-mail (met "Beantwoorden" mail je de patiënt meteen terug)
- de patiënt krijgt een bevestiging in zijn eigen taal (NL/PL)
- spambeveiliging: verborgen honeypot-veld, tijdscontrole en maximaal 5 aanvragen per uur per bezoeker
- alle aanvragen bekijken, afhandelen en exporteren naar Excel via `/beheer/`

Gebruikt [PHPMailer](https://github.com/PHPMailer/PHPMailer) (open source, LGPL).

## Ontwikkelen in GitHub Codespaces

De volledige website draait in Codespaces, inclusief formulier, database en beheerpagina.
Je hebt geen Hostinger en geen installatie op je eigen computer nodig.

1. Op GitHub: **Code → Codespaces → Create codespace on main**
2. Wacht ±2 minuten tot alles klaar is (alleen de eerste keer).
3. Tabblad **PORTS** onderaan:
   - **8000 – Website**: de site, met werkend formulier
   - **8025 – Testmailbox**: alle mails die het formulier verstuurt komen hier binnen (Mailpit)
4. Beheerpagina: `/beheer/` · wachtwoord in Codespaces: `ontwikkeling`

Website gestopt? *Terminal → Run Task → 🧪 Website + testmailbox (her)starten*.

Opslaan op GitHub: *Source Control → bericht typen → Commit* (pusht automatisch).

> In Codespaces gaan er geen echte mails naar patiënten: alles wordt opgevangen in de testmailbox.

## Later: automatisch publiceren naar Hostinger

> ⏸️ **Staat voorlopig uit.** Aanzetten: GitHub → Settings → Secrets and variables →
> Actions → tabblad *Variables* → `HOSTINGER_DEPLOY` = `true` (en de secrets hieronder toevoegen).

Elke push naar `main` zet de website **automatisch** online via GitHub Actions
(`.github/workflows/deploy.yml`). Alleen gewijzigde bestanden worden geüpload.
`api/config.php` wordt bij elke publicatie automatisch aangemaakt uit GitHub Secrets.

**Dagelijks gebruik:** bestand aanpassen → opslaan → in VS Code bij *Source Control*
een bericht typen → **Commit** (pusht automatisch) → na ±1 minuut staat het online.
Of: *Terminal → Run Task → 🚀 Website publiceren*.

### Eenmalige instelling

1. **FTP-account** – hPanel → Bestanden → FTP-accounts → nieuw account met map `public_html`.
2. **GitHub Secrets** – repo → Settings → Secrets and variables → Actions → *New repository secret*:

| Secret | Waarde |
|--------|--------|
| `FTP_SERVER` | FTP-host uit hPanel (bv. `ftp.kinesistmerksem.com` of het IP-adres) |
| `FTP_USERNAME` | gebruikersnaam van het FTP-account |
| `FTP_PASSWORD` | wachtwoord van het FTP-account |
| `SMTP_PASSWORD` | wachtwoord van de mailbox `info@kinesistmerksem.com` |
| `ADMIN_PASSWORD` | wachtwoord voor `/beheer/` (min. 12 tekens) |
| `SECRET_SALT` | lange willekeurige tekst (min. 20 tekens) |

3. Pushen of in het tabblad **Actions** op *Run workflow* klikken.

> ⚠️ Wachtwoorden staan alleen in GitHub Secrets, nooit in de code.
> De database (`data/`) blijft op de server en wordt nooit overschreven.

## Contactformulier

Volledig eigen systeem, zonder externe dienst, zonder limiet en zonder kosten:

- elke aanvraag wordt opgeslagen in een eigen database (`data/aanvragen.sqlite`)
- de praktijk krijgt een e-mail (met "Beantwoorden" mail je de patiënt meteen terug)
- de patiënt krijgt een bevestiging in zijn eigen taal (NL/PL)
- spambeveiliging: verborgen honeypot-veld, tijdscontrole en maximaal 5 aanvragen per uur per bezoeker
- alle aanvragen bekijken, afhandelen en exporteren naar Excel via `/beheer/`

Gebruikt [PHPMailer](https://github.com/PHPMailer/PHPMailer) (open source, LGPL).

## Installatie op Hostinger (eenmalig)

1. Upload alle bestanden naar `public_html/`.
2. Maak in hPanel een mailbox `info@kinesistmerksem.com` aan (als die nog niet bestaat).
3. Kopieer in de Bestandsbeheerder `api/config.example.php` naar **`api/config.php`** en vul in:
   - `smtp → password`: het wachtwoord van de mailbox
   - `admin_password`: een sterk wachtwoord voor de beheerpagina
   - `secret_salt`: een lange willekeurige tekst
4. Test het formulier op de website en log in op `kinesistmerksem.com/beheer/`.

> ⚠️ `api/config.php` en de map `data/` staan in `.gitignore`. Wachtwoorden en
> patiëntgegevens komen dus **nooit** op GitHub. Maak `config.php` alleen op de server aan.

## Contact

Laarsebaan 44, 2170 Antwerpen · +32 483 18 26 63 · info@kinesistmerksem.com
