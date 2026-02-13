#!/usr/bin/env php
<?php
/**
 * One-time migration: import fermmon.csv, fermmon_v*.csv and version.csv into SQLite.
 * Version IDs stored without "v" prefix (14 not v14).
 * Run once: cd /path/to/fermmon && php data/migrate-csv.php
 */

function versionId($v) {
    return preg_replace('/^v/i', '', trim($v));
}

$baseDir = dirname(__DIR__);
$versionPath = $baseDir . '/version.csv';
$dbPath = __DIR__ . '/fermmon.db';

$db = new PDO('sqlite:' . $dbPath);
$db->exec(file_get_contents(__DIR__ . '/schema.sql'));

// Normalize existing readings: v14 -> 14
$db->exec("UPDATE readings SET version = TRIM(SUBSTR(version, 2)) WHERE version GLOB 'v*'");
$db->exec("UPDATE versions SET version = TRIM(SUBSTR(version, 2)) WHERE version GLOB 'v*'");

$totalReadings = 0;

// Migrate version.csv - store version as numeric ID (14 not v14)
if (file_exists($versionPath)) {
    $lines = array_filter(array_map('trim', file($versionPath)));
    $stmt = $db->prepare(
        'INSERT OR REPLACE INTO versions (version, brew, url, is_current) VALUES (?, ?, ?, ?)'
    );
    foreach ($lines as $i => $line) {
        $parts = explode(',', $line, 3);
        if (count($parts) >= 2) {
            $version = versionId($parts[0]);
            $brew = trim($parts[1]);
            $url = isset($parts[2]) ? trim($parts[2]) : '';
            $isCurrent = ($i === 0) ? 1 : 0;
            $stmt->execute([$version, $brew, $url, $isCurrent]);
        }
    }
    if ($lines) {
        $first = versionId(explode(',', $lines[0])[0]);
        $db->exec('UPDATE versions SET is_current = 0');
        $db->prepare('UPDATE versions SET is_current = 1 WHERE version = ?')->execute([$first]);
    }
    echo "Migrated " . count($lines) . " versions.\n";
}

// Migrate readings from fermmon.csv and fermmon_v*.csv
$readStmt = $db->prepare(
    'INSERT OR IGNORE INTO readings (date_time, co2, tvoc, temp, version, rtemp, rhumi, relay) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
);

$csvFiles = [];
if (file_exists($baseDir . '/fermmon.csv')) {
    $csvFiles['fermmon.csv'] = null;  // version from data column
}
foreach (glob($baseDir . '/fermmon_v*.csv') as $path) {
    $name = basename($path);
    if (preg_match('/fermmon_v(\d+)\.csv/', $name, $m)) {
        $csvFiles[$name] = $m[1];  // version from filename
    }
}

foreach ($csvFiles as $name => $fileVersion) {
    $path = $baseDir . '/' . $name;
    $db->beginTransaction();
    $count = 0;
    $fp = fopen($path, 'r');
    while (($line = fgets($fp)) !== false) {
        $parts = array_map('trim', explode(',', $line, 8));
        if (count($parts) < 8) continue;
        $version = $fileVersion !== null ? $fileVersion : versionId($parts[4]);
        $readStmt->execute([
            $parts[0], (float)$parts[1], (float)$parts[2], (float)$parts[3],
            $version, (float)$parts[5], (float)$parts[6], (int)$parts[7],
        ]);
        $count++;
    }
    fclose($fp);
    $db->commit();
    $totalReadings += $count;
    echo "Migrated $count readings from $name\n";
}

echo "Done. Total readings: $totalReadings. You can remove CSV files after verifying.\n";
