#!/usr/bin/env python3
"""
Remove orphan readings at start and end of each version:
- End: data after a >10 day gap (e.g. v13 data still tagged as v12)
- Start: data before first >10 day gap (e.g. v10 data still tagged as v11)

Usage:
  python data/clean-orphan-readings.py           # dry run - show what would be deleted
  python data/clean-orphan-readings.py --execute # actually delete
"""

import sqlite3
import os
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, 'data', 'fermmon.db')
GAP_DAYS = 1  # Gaps > 1 day can have orphan head/tail
MAX_ORPHAN_TAIL = 500  # Only delete if head/tail around gap is small


def parse_dt(s):
    if not s or not isinstance(s, str) or len(s) < 10:
        return None
    try:
        return datetime.strptime(s[:19], '%Y-%m-%d %H:%M:%S')
    except (ValueError, TypeError):
        return None


def find_orphan_ids(conn):
    """For each version, find orphan head (before first gap) and tail (after last gap). Return their IDs."""
    cur = conn.execute(
        'SELECT version FROM versions ORDER BY CAST(version AS INTEGER)'
    )
    versions = [r[0] for r in cur.fetchall()]

    to_delete = []
    for version in versions:
        cur = conn.execute(
            'SELECT id, date_time FROM readings WHERE version = ? ORDER BY date_time ASC',
            (version,)
        )
        rows = cur.fetchall()
        if len(rows) < 2:
            continue

        # Find all gaps >= GAP_DAYS
        gap_indices = []
        for i in range(1, len(rows)):
            prev_dt = parse_dt(rows[i - 1][1])
            curr_dt = parse_dt(rows[i][1])
            if prev_dt is None or curr_dt is None:
                continue
            gap_days = (curr_dt - prev_dt).total_seconds() / 86400
            if gap_days > GAP_DAYS:
                gap_indices.append(i)

        if not gap_indices:
            continue

        first_gap = gap_indices[0]
        last_gap = gap_indices[-1]

        # Head orphans: readings before first gap
        head_ids = [r[0] for r in rows[:first_gap]]
        if len(head_ids) <= MAX_ORPHAN_TAIL:
            to_delete.extend(head_ids)
            print(f"  v{version}: {len(head_ids)} orphan readings at start (before gap at {rows[first_gap][1]}) -> delete")
        elif head_ids:
            print(f"  v{version}: {len(head_ids)} readings at start (skip - head too large)")

        # Tail orphans: readings after last gap
        tail_ids = [r[0] for r in rows[last_gap:]]
        if len(tail_ids) <= MAX_ORPHAN_TAIL:
            to_delete.extend(tail_ids)
            print(f"  v{version}: {len(tail_ids)} orphan readings at end (after gap before {rows[last_gap][1]}) -> delete")
        else:
            print(f"  v{version}: {len(tail_ids)} readings at end (skip - tail too large)")

    return to_delete


def main():
    import sys
    execute = '--execute' in sys.argv

    if not os.path.exists(DB_PATH):
        print(f"DB not found: {DB_PATH}")
        sys.exit(1)

    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row

    print("Scanning for orphan readings (gap > {} day(s))...".format(GAP_DAYS))
    to_delete = find_orphan_ids(conn)

    if not to_delete:
        print("No orphan readings found.")
        conn.close()
        return

    print(f"\nTotal: {len(to_delete)} readings to delete")

    if execute:
        placeholders = ','.join('?' * len(to_delete))
        conn.execute(f'DELETE FROM readings WHERE id IN ({placeholders})', to_delete)
        conn.commit()
        print("Deleted.")
    else:
        print("Dry run. Use --execute to delete.")

    conn.close()


if __name__ == '__main__':
    main()
