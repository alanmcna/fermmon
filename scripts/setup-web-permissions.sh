#!/bin/bash
# Make data/ and fermmon.db writable by Apache (www-data).
# Run once after deploy. fermmon.py (ubuntu) and Apache (www-data) both need write access.
#
# Usage: sudo ./scripts/setup-web-permissions.sh

set -e
BASE_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DATA_DIR="$BASE_DIR/data"
DB="$DATA_DIR/fermmon.db"

# Add www-data to ubuntu group so it can write to group-writable files
usermod -a -G ubuntu www-data 2>/dev/null || true

# Ensure data dir exists (fermmon.py creates it, but may not exist yet)
mkdir -p "$DATA_DIR"

# Group-writable: ubuntu group gets rwx on dir, rw on db
chgrp ubuntu "$DATA_DIR"
chmod g+rwx "$DATA_DIR"
[ -f "$DB" ] && chgrp ubuntu "$DB" && chmod g+rw "$DB"

# Ensure new files in data/ get group write (setgid on dir)
chmod g+s "$DATA_DIR"

echo "Done. Restart Apache: sudo systemctl restart apache2"
