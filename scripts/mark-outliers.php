#!/usr/bin/env php
<?php
/**
 * Mark outlier readings in SQLite. Adds is_outlier column if not present.
 * Outliers: CO2 > 6000 ppm or tVOC > 6000 ppb (typical fermentation spikes).
 * Run: php scripts/mark-outliers.php
 * Then DataService can optionally filter WHERE is_outlier = 0.
 */
$baseDir = dirname(__DIR__);
$dbPath = $baseDir . '/data/fermmon.db';

if (!file_exists($dbPath)) {
    echo "DB not found\n";
    exit(1);
}

$db = new PDO('sqlite:' . $dbPath);

// Add column if missing
try {
    $db->exec("ALTER TABLE readings ADD COLUMN is_outlier INTEGER DEFAULT 0");
} catch (Throwable $e) {
    if (strpos($e->getMessage(), 'duplicate column') === false) throw $e;
}

$db->exec("UPDATE readings SET is_outlier = 0");
$db->exec("UPDATE readings SET is_outlier = 1 WHERE co2 > 6000 OR tvoc > 6000");
$marked = $db->query("SELECT COUNT(*) FROM readings WHERE is_outlier = 1")->fetchColumn();

echo "Marked $marked readings as outliers (CO2 or tVOC > 6000)\n";
