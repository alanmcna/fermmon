#!/usr/bin/env php
<?php
/**
 * Backfill missing v14 (or specified version) data from CSV into SQLite.
 * Use after upgrading from CSV-based fermmon: the old CSV may have recent
 * readings not yet in the DB. INSERT OR IGNORE skips duplicates (date_time).
 *
 * Usage:
 *   php data/backfill-v14-from-csv.php           # backfill v14
 *   php data/backfill-v14-from-csv.php 13        # backfill v13
 */

function versionId($v) {
    return preg_replace('/^v/i', '', trim((string)$v));
}

$version = isset($argv[1]) ? versionId($argv[1]) : '14';
$baseDir = dirname(__DIR__);
$dbPath = __DIR__ . '/fermmon.db';

if (!file_exists($dbPath)) {
    echo "DB not found: $dbPath\n";
    exit(1);
}

$db = new PDO('sqlite:' . $dbPath);
$db->exec(file_get_contents(__DIR__ . '/schema.sql'));

$readStmt = $db->prepare(
    'INSERT OR IGNORE INTO readings (date_time, co2, tvoc, temp, version, rtemp, rhumi, relay) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$totalAdded = 0;

// Check fermmon.csv (version from column)
if (file_exists($baseDir . '/fermmon.csv')) {
    $count = 0;
    $fp = fopen($baseDir . '/fermmon.csv', 'r');
    while (($line = fgets($fp)) !== false) {
        $parts = array_map('trim', explode(',', $line, 8));
        if (count($parts) < 8 || preg_match('/^\d{4}-\d{2}-\d{2}/', $parts[0]) !== 1) continue;
        $rowVersion = versionId($parts[4]);
        if ($rowVersion !== $version) continue;
        $readStmt->execute([
            $parts[0], (float)$parts[1], (float)$parts[2], (float)$parts[3],
            $version, (float)$parts[5], (float)$parts[6], (int)$parts[7],
        ]);
        $count += $readStmt->rowCount();
    }
    fclose($fp);
    $totalAdded += $count;
    echo "Added $count v$version readings from fermmon.csv\n";
}

// Check fermmon_vN.csv (version from filename)
$vCsv = $baseDir . "/fermmon_v{$version}.csv";
if (file_exists($vCsv)) {
    $count = 0;
    $fp = fopen($vCsv, 'r');
    while (($line = fgets($fp)) !== false) {
        $parts = array_map('trim', explode(',', $line, 8));
        if (count($parts) < 8) continue;
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $parts[0]) !== 1) continue; // skip header
        $readStmt->execute([
            $parts[0], (float)$parts[1], (float)$parts[2], (float)$parts[3],
            $version, (float)$parts[5], (float)$parts[6], (int)$parts[7],
        ]);
        $count += $readStmt->rowCount();
    }
    fclose($fp);
    $totalAdded += $count;
    echo "Added $count v$version readings from fermmon_v{$version}.csv\n";
}

if ($totalAdded === 0) {
    echo "No new v$version readings found (CSV missing or all already in DB).\n";
} else {
    echo "Done. Total added: $totalAdded\n";
}
