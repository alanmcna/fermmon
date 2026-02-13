#!/bin/bash
# Backup fermmon SQLite DB to a destination (USB, NFS, or local).
# Uses sqlite3 .backup for a consistent copy without stopping fermmon.
#
# Usage:
#   ./scripts/backup-db.sh /media/usb/fermmon-backups
#   ./scripts/backup-db.sh /mnt/nfs/fermmon-backups
#
# Add to crontab for daily backup (e.g. 2am):
#   0 2 * * * /home/ubuntu/fermmon/scripts/backup-db.sh /media/usb/fermmon-backups

set -e
BASE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DB="$BASE_DIR/data/fermmon.db"
DEST="${1:?Usage: $0 <backup-dir>}"

if [ ! -f "$DB" ]; then
    echo "DB not found: $DB"
    exit 1
fi

mkdir -p "$DEST"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="$DEST/fermmon-$STAMP.db"

sqlite3 "$DB" ".backup '$BACKUP'"
echo "Backed up to $BACKUP"

# Optional: keep only last N backups
KEEP=7
ls -t "$DEST"/fermmon-*.db 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm --
echo "Kept last $KEEP backups"
