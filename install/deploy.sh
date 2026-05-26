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

echo "[1/7] Vytvářím adresáře..."
mkdir -p /var/www/press/ftp
mkdir -p /var/www/press/previews
mkdir -p /var/www/press/published
mkdir -p /var/www/press/upload-tmp
mkdir -p /var/www/press/web
chown -R www-data:www-data /var/www/press/published
chown -R www-data:www-data /var/www/press/upload-tmp
find /var/www/press/published -type d -exec chmod 0775 {} +
find /var/www/press/published -type f -exec chmod 0664 {} +
find /var/www/press/upload-tmp -type d -exec chmod 0775 {} +
find /var/www/press/upload-tmp -type f -exec chmod 0664 {} +

echo "[2/7] Kopíruji konfigurace..."
copy_if_exists "$CONFIG_DIR/vsftpd.conf" /etc/vsftpd.conf
copy_if_exists "$CONFIG_DIR/pam.vsftpd" /etc/pam.d/vsftpd
copy_if_exists "$CONFIG_DIR/press-watcher.service" /etc/systemd/system/press-watcher.service

echo "[3/7] Nastavuji životnost PHP sessions..."
php_session_config_installed=0
for apache_php_conf_dir in /etc/php/*/apache2/conf.d; do
  if [[ -d "$apache_php_conf_dir" ]]; then
    copy_if_exists "$CONFIG_DIR/press-session.ini" "$apache_php_conf_dir/99-press-session.ini"
    php_session_config_installed=1
  fi
done
if [[ $php_session_config_installed -ne 1 ]]; then
  echo "Varování: Nenalezena Apache PHP konfigurace; ověř session.gc_maxlifetime ručně." >&2
fi

echo "[4/7] Kopíruji skripty..."
install -m 0755 "$SCRIPTS_DIR/press-watcher.sh" /usr/local/bin/press-watcher.sh
install -m 0755 "$SCRIPTS_DIR/press-backfill.sh" /usr/local/bin/press-backfill.sh
install -m 0755 "$SCRIPTS_DIR/press-backfill-captured-at.sh" /usr/local/bin/press-backfill-captured-at.sh

echo "[5/7] Kopíruji webovou aplikaci..."
rsync -av --delete \
  --exclude 'config/config.php' \
  "$WEB_DIR/" \
  /var/www/press/web/

echo "[6/7] Reload služeb..."
systemctl daemon-reload
if [[ $php_session_config_installed -eq 1 ]]; then
  systemctl reload apache2
fi

echo "[7/7] Restartuji služby..."
systemctl restart vsftpd
systemctl enable press-watcher
systemctl restart press-watcher

echo "Hotovo. Volitelně můžeš aplikovat DB schema:"
echo "    mysql -u root -p < $SQL_DIR/schema.sql"
echo
echo "Status služeb:"
systemctl --no-pager --full status vsftpd press-watcher || true
