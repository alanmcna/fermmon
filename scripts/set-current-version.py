#!/usr/bin/env python3
"""
Set the current brew version. Run when starting a new fermentation.
Version stored as numeric ID (15 not v15).
Usage: python scripts/set-current-version.py VERSION "Brew Name" [url]
Example: python scripts/set-current-version.py 15 "My New IPA"
"""
import sqlite3
import os
import sys

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, 'data', 'fermmon.db')


def version_id(v):
    s = str(v).strip()
    return s.lstrip('vV') if s.lower().startswith('v') else s


if len(sys.argv) < 3:
    print("Usage: set-current-version.py VERSION \"Brew Name\" [url]", file=sys.stderr)
    sys.exit(1)

version = version_id(sys.argv[1])
brew = sys.argv[2]
url = sys.argv[3] if len(sys.argv) > 3 else ''

conn = sqlite3.connect(DB_PATH)
conn.execute("UPDATE versions SET is_current = 0")
conn.execute(
    "INSERT OR REPLACE INTO versions (version, brew, url, is_current) VALUES (?, ?, ?, 1)",
    (version, brew, url)
)
conn.commit()
conn.close()
print("Current version set to: %s - %s" % (version, brew))
