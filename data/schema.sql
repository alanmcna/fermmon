-- Fermmon SQLite schema
-- readings: sensor data written by fermmon.py
-- versions: brew metadata; first/current has is_current=1

CREATE TABLE IF NOT EXISTS readings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date_time TEXT NOT NULL UNIQUE,
    co2 REAL,
    tvoc REAL,
    temp REAL,
    version TEXT,
    rtemp REAL,
    rhumi REAL,
    relay INTEGER,
    created_at TEXT DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_readings_date ON readings(date_time);
CREATE INDEX IF NOT EXISTS idx_readings_version ON readings(version);

CREATE TABLE IF NOT EXISTS versions (
    version TEXT PRIMARY KEY,
    brew TEXT NOT NULL,
    url TEXT,
    description TEXT,
    end_date TEXT,
    is_current INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_versions_current ON versions(is_current);

CREATE TABLE IF NOT EXISTS brew_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    version TEXT NOT NULL,
    date_time TEXT NOT NULL,
    note TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (version) REFERENCES versions(version)
);
CREATE INDEX IF NOT EXISTS idx_brew_logs_version ON brew_logs(version);
CREATE INDEX IF NOT EXISTS idx_brew_logs_date ON brew_logs(date_time);

CREATE TABLE IF NOT EXISTS config (
    key TEXT PRIMARY KEY,
    value TEXT
);
-- Default config (insert if empty)
INSERT OR IGNORE INTO config (key, value) VALUES ('recording', '1');
INSERT OR IGNORE INTO config (key, value) VALUES ('sample_interval', '10');
INSERT OR IGNORE INTO config (key, value) VALUES ('write_interval', '300');
INSERT OR IGNORE INTO config (key, value) VALUES ('summary_refresh_interval', '30');
INSERT OR IGNORE INTO config (key, value) VALUES ('chart_update_interval', '300');
INSERT OR IGNORE INTO config (key, value) VALUES ('target_temp', '19.5');
INSERT OR IGNORE INTO config (key, value) VALUES ('temp_warning_threshold', '3');
INSERT OR IGNORE INTO config (key, value) VALUES ('hide_outliers', '1');
