<?php

require __DIR__ . '/../vendor/autoload.php';

// Use isolated test database for API tests (DataService expects baseDir/data/fermmon.db)
$projectRoot = dirname(__DIR__, 2);
$testBaseDir = $projectRoot . '/data/test';
$testDataDir = $testBaseDir . '/data';
$dbPath = $testDataDir . '/fermmon.db';
$schemaPath = $projectRoot . '/data/schema.sql';

if (!is_dir($testDataDir)) {
    mkdir($testDataDir, 0755, true);
}
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec(file_get_contents($schemaPath));

putenv('FERMMON_BASE_DIR=' . $testBaseDir);
