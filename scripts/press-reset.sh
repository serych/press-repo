#!/bin/bash
set -euo pipefail

FTP_ROOT="/var/www/press/ftp"
PREVIEW_ROOT="/var/www/press/previews"

echo "Stopping watcher..."
pkill -f press-watcher.sh || true
sleep 1

echo "Cleaning FTP..."
find "$FTP_ROOT" -mindepth 2 -type f -delete

echo "Cleaning previews..."
find "$PREVIEW_ROOT" -type f -delete

echo "Cleaning database..."
mysql press <<'SQL'
SET FOREIGN_KEY_CHECKS=0;

DELETE FROM photo_log;
DELETE FROM download_jobs;
DELETE FROM photos;

SET FOREIGN_KEY_CHECKS=1;
SQL

echo "Restart watcher..."
/var/www/press-repo/scripts/press-watcher.sh &

echo "DONE"