#!/usr/bin/env python3
"""
One-off migration: set end_date on all versions < 14 to the date of their last reading.

Usage:
  python scripts/migrate-end-dates.py           # dry run - show what would be updated
  python scripts/migrate-end-dates.py --execute # apply changes
"""
import sqlite3
import os
import sys

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, 'data', 'fermmon.db')
CURRENT_THRESHOLD = 14


def main():
    execute = '--execute' in sys.argv
    if not execute:
        print("Dry run. Use --execute to apply changes.\n")

    if not os.path.exists(DB_PATH):
        print("Database not found: %s" % DB_PATH, file=sys.stderr)
        sys.exit(1)

    conn = sqlite3.connect(DB_PATH)

    # Ensure end_date column exists
    cur = conn.execute("PRAGMA table_info(versions)")
    cols = [r[1] for r in cur.fetchall()]
    if 'end_date' not in cols:
        print("Adding end_date column to versions...")
        conn.execute("ALTER TABLE versions ADD COLUMN end_date TEXT")
        conn.commit()

    # Find versions < 14 with readings; get last reading date for each
    cur = conn.execute("""
        SELECT v.version, v.brew, v.end_date,
               (SELECT MAX(r.date_time) FROM readings r WHERE r.version = v.version)
        FROM versions v
        WHERE CAST(v.version AS INTEGER) < ?
        ORDER BY CAST(v.version AS INTEGER)
    """, (CURRENT_THRESHOLD,))
    rows = cur.fetchall()

    updated = 0
    for version, brew, existing_end, last_reading in rows:
        if not last_reading:
            print("  v%s (%s): no readings, skipping" % (version, brew))
            continue
        if existing_end:
            print("  v%s (%s): already has end_date=%s, skipping" % (version, brew, existing_end))
            continue
        print("  v%s (%s): would set end_date=%s" % (version, brew, last_reading))
        if execute:
            conn.execute("UPDATE versions SET end_date = ? WHERE version = ?", (last_reading, version))
            updated += 1

    if execute:
        conn.commit()
        print("\nUpdated %d version(s)." % updated)
    else:
        print("\nRun with --execute to apply.")

    conn.close()


if __name__ == '__main__':
    main()
