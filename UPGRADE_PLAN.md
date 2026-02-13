# Fermmon Upgrade Plan

## Overview

Restructure the project to separate data and web concerns, replace R charts with Chart.js, and provide a PHP PWA with Slim templating.

## Architecture

```
fermmon/
├── fermmon.py          # Data logger - writes directly to SQLite
├── data/               # Data layer
│   ├── fermmon.db      # SQLite (readings + versions)
│   ├── schema.sql
│   └── migrate-csv.php # One-time: CSV → SQLite
├── web/                # PHP PWA - Slim + Chart.js
│   ├── public/         # Web root (nginx/Apache DocumentRoot)
│   │   ├── index.php   # Front controller
│   │   └── ...
│   ├── src/
│   │   ├── DataService.php
│   │   └── ...
│   ├── templates/
│   └── composer.json
└── fermmon.csv, latest.csv, version.csv  # Stay in root (fermmon.py writes here)
```

## Key Decisions

1. **Data**: SQLite only. `fermmon.py` writes directly to `data/fermmon.db` (readings + versions).
2. **No CSV**: CSV files are deprecated. One-time `migrate-csv.php` imports existing data.
3. **Web**: PHP with Slim 4, PHP-View templating, Chart.js for charts.
4. **PWA**: manifest.json + service worker for offline/cache.

## Deployment

1. **Web server**: Replace `http.service` (Python) with Apache or nginx + PHP-FPM.
   - Use `apache-fermmon.conf` or `nginx-fermmon.conf` as a template.
   - Point DocumentRoot to `web/public/`.
2. **PHP deps**: `cd web && composer install`
3. **Migration** (if you have existing CSV): `php data/migrate-csv.php` once, then restart fermmon.
4. **Remove**: R cron for `fermmon.r` (charts now client-side with Chart.js).
5. **Legacy**: `index.html`, `fermmon.r` can be kept for reference or removed.
