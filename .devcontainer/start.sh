#!/usr/bin/env bash
# Start de website (poort 8000) en de testmailbox (poort 8025) op de achtergrond
cd "$(dirname "$0")/.."

if ! pgrep -x mailpit >/dev/null; then
    setsid nohup mailpit --listen 0.0.0.0:8025 --smtp 0.0.0.0:1025 >/tmp/mailpit.log 2>&1 < /dev/null &
fi

if ! pgrep -f "php -S 0.0.0.0:8000" >/dev/null; then
    setsid nohup php -S 0.0.0.0:8000 .devcontainer/router.php >/tmp/website.log 2>&1 < /dev/null &
fi

sleep 1
echo "🌐 Website:      poort 8000 (tabblad PORTS)"
echo "📬 Testmailbox:  poort 8025"
echo "🔐 Beheer:       /beheer/  (wachtwoord: ontwikkeling)"
