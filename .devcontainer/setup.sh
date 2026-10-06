#!/usr/bin/env bash
# Eenmalige installatie van de ontwikkelomgeving (Codespaces)
set -e
cd "$(dirname "$0")/.."

SUDO=""
[ "$(id -u)" -ne 0 ] && SUDO="sudo"

echo "📬 Mailpit (testmailbox) installeren..."
if ! command -v mailpit >/dev/null; then
    curl -sfL -o /tmp/mailpit.tgz \
        https://github.com/axllent/mailpit/releases/latest/download/mailpit-linux-amd64.tar.gz
    tar -xzf /tmp/mailpit.tgz -C /tmp mailpit
    $SUDO install -m 755 /tmp/mailpit /usr/local/bin/mailpit
fi

echo "⚙️  PHP laten mailen naar Mailpit..."
INI_DIR=$(php --ini | sed -n 's/^Scan for additional .ini files in: //p')
echo 'sendmail_path = "/usr/local/bin/mailpit sendmail"' | $SUDO tee "$INI_DIR/zz-mailpit.ini" >/dev/null

echo "🔑 config.php voor ontwikkeling aanmaken..."
if [ ! -f api/config.php ]; then
    cat > api/config.php << 'PHP'
<?php
// ONTWIKKELCONFIGURATIE (alleen Codespaces). Staat in .gitignore.
$config = require __DIR__ . '/config.example.php';
$config['smtp']['password'] = '';            // leeg = mails gaan naar Mailpit
$config['admin_password']   = 'ontwikkeling';
$config['secret_salt']      = 'alleen-voor-ontwikkeling-niet-geheim';
$config['rate_limit_per_hour'] = 100;        // makkelijker testen
return $config;
PHP
fi

mkdir -p data
echo "✅ Klaar."
