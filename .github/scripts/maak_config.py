"""
Maakt api/config.php aan op basis van GitHub Secrets.
De standaardwaarden komen uit api/config.example.php; alleen de geheimen
worden hier ingevuld. Zo staan er nooit wachtwoorden in de repository.
"""
import os
import sys


def php_string(value: str) -> str:
    """Zet tekst veilig om naar een PHP-string tussen enkele aanhalingstekens."""
    return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"


smtp_password = os.environ.get("SMTP_PASSWORD", "")
admin_password = os.environ.get("ADMIN_PASSWORD", "")
secret_salt = os.environ.get("SECRET_SALT", "")

fouten = []
if len(admin_password) < 12:
    fouten.append("ADMIN_PASSWORD ontbreekt of is korter dan 12 tekens.")
if len(secret_salt) < 20:
    fouten.append("SECRET_SALT ontbreekt of is korter dan 20 tekens.")
if fouten:
    for f in fouten:
        print(f"::error::{f} Voeg het toe via GitHub → Settings → Secrets and variables → Actions.")
    sys.exit(1)

if not smtp_password:
    print("::warning::SMTP_PASSWORD is leeg: mails gaan via PHP mail() in plaats van de Hostinger-mailbox.")

inhoud = f"""<?php
// AUTOMATISCH AANGEMAAKT door GitHub Actions. Niet handmatig aanpassen:
// wijzig de waarden via GitHub Secrets of in config.example.php.
$config = require __DIR__ . '/config.example.php';
$config['smtp']['password'] = {php_string(smtp_password)};
$config['admin_password']   = {php_string(admin_password)};
$config['secret_salt']      = {php_string(secret_salt)};
return $config;
"""

with open("api/config.php", "w", encoding="utf-8") as bestand:
    bestand.write(inhoud)

print("api/config.php aangemaakt.")
