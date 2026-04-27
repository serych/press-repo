#!/bin/bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CONFIG_DIR="$REPO_ROOT/config"
SCRIPTS_DIR="$REPO_ROOT/scripts"
SQL_DIR="$REPO_ROOT/sql"
WEB_DIR="$REPO_ROOT/web"

if [[ $EUID -ne 0 ]]; then
  echo "Tento skript musí být spuštěn jako root." >&2
  exit 1
fi

copy_if_exists() {
  local src="$1"
  local dst="$2"
  if [[ ! -f "$src" ]]; then
    echo "Chybí soubor: $src" >&2
    exit 1
  fi
  install -m 0644 "$src" "$dst"
}

echo "[1/6] Vytvářím adresáře..."
mkdir -p /var/www/press/ftp
mkdir -p /var/www/press/previews
mkdir -p /var/www/press/web

echo "[2/6] Kopíruji konfigurace..."
copy_if_exists "$CONFIG_DIR/vsftpd.conf" /etc/vsftpd.conf
copy_if_exists "$CONFIG_DIR/pam.vsftpd" /etc/pam.d/vsftpd
copy_if_exists "$CONFIG_DIR/press-watcher.service" /etc/systemd/system/press-watcher.service

echo "[3/7] Kopíruji skripty..."
install -m 0755 "$SCRIPTS_DIR/press-watcher.sh" /usr/local/bin/press-watcher.sh
install -m 0755 "$SCRIPTS_DIR/press-backfill.sh" /usr/local/bin/press-backfill.sh
install -m 0755 "$SCRIPTS_DIR/press-backfill-captured-at.sh" /usr/local/bin/press-backfill-captured-at.sh

echo "[4/7] Kopíruji webovou aplikaci..."
rsync -av --delete \
  --exclude 'config/config.php' \
  "$WEB_DIR/" \
  /var/www/press/web/

echo "[5/7] Reload systemd..."
systemctl daemon-reload

echo "[6/7] Restartuji služby..."
systemctl restart vsftpd
systemctl enable press-watcher
systemctl restart press-watcher

echo "[7/7] Hotovo. Volitelně můžeš aplikovat DB schema:"
echo "    mysql -u root -p < $SQL_DIR/schema.sql"
echo
echo "Status služeb:"
systemctl --no-pager --full status vsftpd press-watcher || true
