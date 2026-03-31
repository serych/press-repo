#!/bin/bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CONFIG_DIR="$REPO_ROOT/config"
SCRIPTS_DIR="$REPO_ROOT/scripts"
DOCS_DIR="$REPO_ROOT/docs"
SQL_DIR="$REPO_ROOT/sql"

if [[ $EUID -ne 0 ]]; then
  echo "Tento skript musí být spuštěn jako root, aby měl přístup ke všem systémovým souborům." >&2
  exit 1
fi

mkdir -p "$CONFIG_DIR" "$SCRIPTS_DIR" "$DOCS_DIR" "$SQL_DIR" "$REPO_ROOT/install"

copy_file() {
  local src="$1"
  local dst="$2"
  if [[ -f "$src" ]]; then
    install -m 0644 "$src" "$dst"
    echo "OK  $src -> $dst"
  else
    echo "WARN chybí $src"
  fi
}

copy_exec() {
  local src="$1"
  local dst="$2"
  if [[ -f "$src" ]]; then
    install -m 0755 "$src" "$dst"
    echo "OK  $src -> $dst"
  else
    echo "WARN chybí $src"
  fi
}

echo "Synchronizuji systémové soubory do repozitáře..."
copy_file /etc/vsftpd.conf "$CONFIG_DIR/vsftpd.conf"
copy_file /etc/pam.d/vsftpd "$CONFIG_DIR/pam.vsftpd"
copy_file /etc/systemd/system/press-watcher.service "$CONFIG_DIR/press-watcher.service"
copy_exec /usr/local/bin/press-watcher.sh "$SCRIPTS_DIR/press-watcher.sh"
copy_exec /usr/local/bin/press-backfill.sh "$SCRIPTS_DIR/press-backfill.sh"


# Webová aplikace
rsync -av --delete \
    --exclude 'config/config.php' \
    /var/www/press/web/ \
    "$REPO_ROOT/web/"

echo "Hotovo."
echo "Pozn.: /var/www/press/web/config/config.php se z bezpečnostních důvodů nekopíruje."


echo
echo "Doporučený další postup:"
echo "  cd $REPO_ROOT"
echo "  git status"
echo "  git add ."
echo "  git commit -m 'Synchronizace konfigurace ze serveru'"
