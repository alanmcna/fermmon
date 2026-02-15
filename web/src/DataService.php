<?php

namespace Fermmon\Web;

/**
 * Data access for fermmon - SQLite only (no CSV).
 */
class DataService
{
    private string $baseDir;
    private ?\PDO $db = null;

    public function __construct(string $baseDir)
    {
        $this->baseDir = $baseDir;
        $dbPath = $baseDir . '/data/fermmon.db';
        $schemaPath = $baseDir . '/data/schema.sql';
        if (file_exists($dbPath)) {
            $this->db = new \PDO('sqlite:' . $dbPath);
            if (file_exists($schemaPath)) {
                $this->db->exec(file_get_contents($schemaPath));
            }
            $this->migrateVersionsDescription();
        }
    }

    /**
     * Get first and last reading date_time for a version. Returns null if no readings.
     */
    public function getReadingRange(?string $version): ?array
    {
        if (!$this->db || !$version) return null;

        $version = preg_replace('/^v/i', '', trim($version));
        $stmt = $this->db->prepare(
            'SELECT MIN(date_time) AS first_date_time, MAX(date_time) AS last_date_time 
             FROM readings WHERE version = ? AND co2 <= 25000 AND tvoc <= 25000 AND temp > 0'
        );
        $stmt->execute([$version]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || $row['first_date_time'] === null) return null;
        return $row;
    }

    /**
     * Get latest reading with brew name. Optionally filter by version ID.
     */
    public function getLatest(?string $version = null): ?array
    {
        if (!$this->db) return null;

        if ($version) {
            $stmt = $this->db->prepare(
                'SELECT r.date_time, r.co2, r.tvoc, r.temp, r.version, r.rtemp, r.rhumi, r.relay, v.brew
                 FROM readings r
                 LEFT JOIN versions v ON r.version = v.version
                 WHERE r.version = ?
                 ORDER BY r.date_time DESC LIMIT 1'
            );
            $stmt->execute([$version]);
        } else {
            $stmt = $this->db->query(
                'SELECT r.date_time, r.co2, r.tvoc, r.temp, r.version, r.rtemp, r.rhumi, r.relay, v.brew
                 FROM readings r
                 LEFT JOIN versions v ON r.version = v.version
                 WHERE r.version = (SELECT version FROM versions WHERE is_current = 1 LIMIT 1)
                 ORDER BY r.date_time DESC LIMIT 1'
            );
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Get readings for charts.
     * Optional outlier filter: max_co2, max_tvoc cap to exclude sensor spikes.
     * Optional date filter: from (readings on or after), to (readings on or before).
     * Typical fermentation: 1–3k ppm CO2, 500–2k ppb tVOC. Spikes to 16k+ are often sensor glitches.
     */
    public function getReadings(
        ?string $version = null,
        ?int $limit = 5000,
        ?int $maxCo2 = null,
        ?int $maxTvoc = null,
        ?string $since = null,
        ?string $from = null,
        ?string $to = null
    ): array {
        if (!$this->db) return [];

        $sql = 'SELECT date_time, co2, tvoc, temp, version, rtemp, rhumi, relay 
                FROM readings 
                WHERE co2 <= 25000 AND tvoc <= 25000 AND temp > 0';
        $params = [];

        if ($maxCo2 !== null) {
            $sql .= ' AND co2 <= ?';
            $params[] = $maxCo2;
        }
        if ($maxTvoc !== null) {
            $sql .= ' AND tvoc <= ?';
            $params[] = $maxTvoc;
        }
        if ($version) {
            $sql .= ' AND version = ?';
            $params[] = $version;
        }
        if ($since !== null) {
            $sql .= ' AND date_time > ?';
            $params[] = $since;
        }
        if ($from !== null) {
            $sql .= ' AND date_time >= ?';
            $params[] = $from;
        }
        if ($to !== null) {
            $sql .= ' AND date_time <= ?';
            $params[] = $to;
        }

        $sql .= ' ORDER BY date_time DESC';
        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $params[] = $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_reverse($rows);
    }

    /**
     * Add description column to versions if missing (migration).
     */
    private function migrateVersionsDescription(): void
    {
        if (!$this->db) return;
        $cols = $this->db->query('PRAGMA table_info(versions)')->fetchAll(\PDO::FETCH_ASSOC);
        if (!in_array('description', array_column($cols, 'name'), true)) {
            $this->db->exec('ALTER TABLE versions ADD COLUMN description TEXT');
        }
        $cols = $this->db->query('PRAGMA table_info(versions)')->fetchAll(\PDO::FETCH_ASSOC);
        if (!in_array('end_date', array_column($cols, 'name'), true)) {
            $this->db->exec('ALTER TABLE versions ADD COLUMN end_date TEXT');
        }
    }

    /**
     * Get available versions from DB. Current first, then by version ID descending.
     */
    public function getVersions(): array
    {
        if (!$this->db) return [];

        $stmt = $this->db->query(
            'SELECT version, brew, url, description, end_date, is_current FROM versions 
             ORDER BY is_current DESC, CAST(version AS INTEGER) DESC'
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get config (recording, sample_interval, write_interval).
     */
    public function getConfig(): array
    {
        if (!$this->db) return ['recording' => '1', 'sample_interval' => '10', 'write_interval' => '300',
            'summary_refresh_interval' => '30', 'chart_update_interval' => '300',
            'target_temp' => '19.5', 'temp_warning_threshold' => '3', 'hide_outliers' => '1', 'cache_apis' => '0'];

        $stmt = $this->db->query('SELECT key, value FROM config');
        $config = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $config[$row['key']] = $row['value'];
        }
        $defaults = ['recording' => '1', 'sample_interval' => '10', 'write_interval' => '300',
            'summary_refresh_interval' => '30', 'chart_update_interval' => '300',
            'target_temp' => '19.5', 'temp_warning_threshold' => '3', 'hide_outliers' => '1', 'cache_apis' => '0'];
        return array_merge($defaults, $config);
    }

    /**
     * Set config value.
     */
    public function setConfig(string $key, string $value): bool
    {
        if (!$this->db) return false;

        $stmt = $this->db->prepare('INSERT OR REPLACE INTO config (key, value) VALUES (?, ?)');
        return $stmt->execute([$key, $value]);
    }

    /**
     * Check if a version is marked as finished (has end_date).
     */
    public function versionIsFinished(?string $version): bool
    {
        if (!$this->db) return false;

        if ($version === null || $version === '') {
            $row = $this->db->query('SELECT end_date FROM versions WHERE is_current = 1 LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
            return !empty($row['end_date']);
        }
        $version = preg_replace('/^v/i', '', trim($version));
        $stmt = $this->db->prepare('SELECT end_date FROM versions WHERE version = ?');
        $stmt->execute([$version]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row && !empty($row['end_date']);
    }

    /**
     * Insert a reading. For single-fermenter mode, version defaults to current.
     */
    public function addReading(
        string $dateTime,
        float $co2,
        float $tvoc,
        float $temp,
        ?string $version = null,
        ?float $rtemp = null,
        ?float $rhumi = null,
        ?int $relay = null
    ): bool {
        if (!$this->db) return false;

        if ($version === null) {
            $row = $this->db->query('SELECT version FROM versions WHERE is_current = 1 LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
            $version = $row['version'] ?? '';
        }
        $version = preg_replace('/^v/i', '', trim($version));
        $rtemp = $rtemp ?? 0;
        $rhumi = $rhumi ?? 0;
        $relay = $relay ?? 0;

        $stmt = $this->db->prepare(
            'INSERT OR IGNORE INTO readings (date_time, co2, tvoc, temp, version, rtemp, rhumi, relay) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        return $stmt->execute([$dateTime, $co2, $tvoc, $temp, $version, $rtemp, $rhumi, $relay]);
    }

    /**
     * Add new version and set as current.
     */
    public function addVersion(string $version, string $brew, string $url = '', string $description = ''): bool
    {
        if (!$this->db) return false;

        $version = preg_replace('/^v/i', '', trim($version));
        $this->db->exec('UPDATE versions SET is_current = 0');
        $stmt = $this->db->prepare('INSERT OR REPLACE INTO versions (version, brew, url, description, is_current) VALUES (?, ?, ?, ?, 1)');
        return $stmt->execute([$version, $brew, $url, $description]);
    }

    /**
     * Update an existing version (brew, url, description, end_date). Version ID cannot be changed.
     */
    public function updateVersion(string $version, string $brew, string $url = '', string $description = '', ?string $endDate = null): bool
    {
        if (!$this->db) return false;

        $version = preg_replace('/^v/i', '', trim($version));
        $stmt = $this->db->prepare('UPDATE versions SET brew = ?, url = ?, description = ?, end_date = ? WHERE version = ?');
        return $stmt->execute([$brew, $url, $description, $endDate ?: null, $version]);
    }

    /**
     * Get brew log entries for a version, ordered by date_time ascending.
     */
    public function getBrewLogs(string $version): array
    {
        if (!$this->db) return [];

        $version = preg_replace('/^v/i', '', trim($version));
        $stmt = $this->db->prepare('SELECT id, version, date_time, note FROM brew_logs WHERE version = ? ORDER BY date_time ASC');
        $stmt->execute([$version]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Add a brew log entry. date_time in UTC (YYYY-MM-DD HH:MM:SS). note max 256 chars.
     */
    public function addBrewLog(string $version, string $dateTime, string $note): bool
    {
        if (!$this->db) return false;

        $version = preg_replace('/^v/i', '', trim($version));
        $note = mb_substr(trim($note), 0, 256);
        if ($note === '') return false;

        $stmt = $this->db->prepare('INSERT INTO brew_logs (version, date_time, note) VALUES (?, ?, ?)');
        return $stmt->execute([$version, $dateTime, $note]);
    }

    /**
     * Delete a brew log entry by id. Version must match (security check).
     */
    public function deleteBrewLog(int $id, string $version): bool
    {
        if (!$this->db) return false;

        $version = preg_replace('/^v/i', '', trim($version));
        $stmt = $this->db->prepare('DELETE FROM brew_logs WHERE id = ? AND version = ?');
        return $stmt->execute([$id, $version]) && $stmt->rowCount() > 0;
    }
}
