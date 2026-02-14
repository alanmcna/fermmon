# What / why?

Set-up to monitor my brew fermentation (over a ~10 day period) to watch for co2 levels to drop to indicate the brew is done.

Also have a Rowi (API enabled plug) to turn on my heater belt if the temp drops low.

Currently a WIP - will improve and add some 3d print files for the housing ( fits on top of the airlock).

# data collection

The CCS811 sensor (Mode 1) produces new eCO2/tVOC readings every 1 second. fermmon.py polls every 10 seconds, accumulates samples, and writes one averaged reading to SQLite every 5 minutes. This gives ~288 readings/day (~2900 for a 10-day fermentation), which is sufficient to see the trend while keeping storage and chart load manageable.

| Setting | Value | Purpose |
|---------|-------|---------|
| Poll interval | 10 s | Check sensor, read when data available |
| Write interval | 5 min | DB write and temp/relay logic |
| Samples per write | ~30 | Rolling average for noise reduction |

**API push**: Set `API_URL` (default `https://localhost:443`) to have fermmon POST readings to the web API instead of local SQLite. On API failure, it falls back to local DB. Set `API_URL=` (empty) to use local SQLite only.

## CCS811 operating modes

| Mode | Interval | Use |
|------|----------|-----|
| 0 | Idle | No measurements, low power |
| 1 | 1 s | Constant power (fermmon uses this) |
| 2 | 10 s | Pulse heating, lower power |
| 3 | 60 s | Low power pulse |
| 4 | 250 ms | Raw data only, factory test |

## Reset and mode change (CCS811 datasheet)

**Software reset**: On startup, the qwiic library writes `0x11,0xE5,0x72,0x8A` to register 0xFF. This resets the device into Bootloader; `APP_START` then switches to Application mode. The sensor is left in a known state before setting Mode 1.

**Mode change rules** (when changing drive mode at runtime):
- **Lower sample rate** (e.g. Mode 1 → Mode 3): Put in Mode 0 (Idle) for **at least 10 minutes** before enabling the new mode.
- **Higher sample rate** (e.g. Mode 3 → Mode 1): No wait required.

fermmon starts in Mode 1 and does not change mode at runtime, so no idle transition is needed.

**Burn-in**: 48-hour initial burn-in recommended for new sensors; 20 min warm-up when resuming use.

**Environment**: The CCS811 is sensitive to humidity and temperature. In warm, moist spaces (e.g. laundry room with washer/dryer running), readings can spike or become unreliable. Good ventilation helps: run an extractor fan and/or a secondary fan to reduce humidity and heat buildup. If readings go haywire, moving the sensor to fresh air for 20–30 minutes to reset the baseline often helps.

# hardware

* pi-zero 2w
* ccs811 - air quality sensor (sparkfun)
* DS18b20 - Temperature Sensor Probe - Stainless steel
* rowi 2
* Mangrove Jack starter brew kit
* Thermowell 300mm - cut to suit

## /boot/firmware/config.txt

```
# Uncomment some or all of these to enable the optional hardware interfaces
dtparam=i2c_arm=on
dtparam=i2s=on
dtparam=spi=on

# Enable I2C clock stretching
dtparam=i2c_arm_baudrate=10000

...

dtoverlay=w1-gpio
enable_uart=1
```


# packages

- git 
- python3-pip
- php (>=7.4) php-sqlite3 php-mbstring
- apache2 (or nginx + php-fpm) with mod_rewrite
- composer

## python packages

- pip3 install sparkfun-qwiic-i2c # see also https://github.com/sparkfun/Qwiic_I2C_Py

Note: see also https://github.com/sparkfun/Qwiic_CCS811_Py but there is a pull request open for this

## web (PHP PWA)

```bash
cd web && composer install
```

### development

Run the PHP app locally with the built-in server:

```bash
cd /path/to/fermmon
php -S localhost:8080 -t web/public web/public/router.php
```

Then open http://localhost:8080. The router forwards requests to `index.php` for clean URLs.

### HTTPS (for notifications and PWA)

Browser notifications and PWA features (service worker, install prompt) require a secure context (HTTPS or localhost). On a LAN, use a self-signed certificate:

**1. Generate a self-signed cert** (valid 10 years):

```bash
sudo openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout /etc/ssl/private/fermmon.key \
  -out /etc/ssl/certs/fermmon.crt \
  -subj "/CN=fermmon.local" \
  -addext "basicConstraints=critical,CA:FALSE" \
  -addext "subjectAltName=DNS:fermmon.local,DNS:asteroids,IP:192.168.0.10"
```

Replace `192.168.0.10` with your Pi's IP. Add other hostnames to the `subjectAltName` list if needed. The `basicConstraints=CA:FALSE` avoids Apache's "CA certificate" warning.

**2. Add HTTPS VirtualHosts** to your Apache config (e.g. in `apache-fermmon.conf` or a separate SSL config). Replace the existing `*:80` VirtualHost with a redirect, and add the HTTPS host:

```apache
# Redirect HTTP to HTTPS (preserves host: fermmon.local or IP)
<VirtualHost *:80>
    ServerName fermmon.local
    RewriteEngine On
    RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]
</VirtualHost>

<VirtualHost *:443>
    ServerName fermmon.local
    DocumentRoot /home/ubuntu/fermmon/web/public
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/fermmon.crt
    SSLCertificateKeyFile /etc/ssl/private/fermmon.key
    <Directory /home/ubuntu/fermmon/web/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog ${APACHE_LOG_DIR}/fermmon-ssl-error.log
    CustomLog ${APACHE_LOG_DIR}/fermmon-ssl-access.log combined
</VirtualHost>
```

**3. Enable SSL** and restart:

```bash
sudo a2enmod ssl
sudo systemctl restart apache2
```

**4. Access** via `https://fermmon.local` or `https://192.168.0.10`. Accept the browser's certificate warning once per device; after that, notifications and PWA work.

**Alternative (no warnings):** Use [mkcert](https://github.com/FiloSottile/mkcert) to create locally-trusted certs. Install the mkcert CA on each device that will use the app.

### UI tests (Pest)

API and integration tests verify the config and hide-outliers flow.

```bash
cd web && composer install
composer test
```

Tests use an isolated SQLite database (`data/test/`). They live in `web/tests/`.

## Control page

The **Control** page (`/control`) lets you manage recording and versions from the browser:

| Feature | Description |
|---------|-------------|
| Start/Stop recording | Pause data writes while fermmon keeps running (temp/relay still active). Use when changing batches. |
| Add new version | Add a new brew and set it as current. Best done when recording is paused. |
| Timing | Adjust sample and write intervals (advanced). |

fermmon reads the `config` table each cycle, so changes take effect without restart.

## API: POST reading

`POST /api/readings` accepts a JSON body for a single reading (single-fermenter mode):

| Field | Required | Description |
|-------|----------|-------------|
| co2 | ✓ | CO2 (ppm) |
| tvoc | ✓ | tVOC (ppb) |
| temp | ✓ | Internal temp (°C) |
| date_time | | Defaults to now |
| version | | Defaults to current version |
| rtemp, rhumi, relay | | Optional |

Example: `{"co2": 1200, "tvoc": 450, "temp": 19.2}`

*Planned*: Security (device ID + token), multi-fermenter support, and securing Control when the app is online.

# services

```bash
sudo cp fermmon.service /etc/systemd/system/
sudo systemctl enable fermmon.service   # start on boot
sudo systemctl start fermmon.service    # start now
```

The old `http.service` (Python HTTP server) is replaced by Apache/nginx. Point DocumentRoot to `web/public/`.

**Apache (single-site setup)**: See `apache-fermmon.conf` for step-by-step instructions. After install, run `sudo ./scripts/setup-web-permissions.sh` so Apache can write to the DB (Control page, config, etc.).

# operations

| Action | Command |
|--------|---------|
| Start | `sudo systemctl start fermmon.service` |
| Stop | `sudo systemctl stop fermmon.service` |
| Restart | `sudo systemctl restart fermmon.service` |
| Status | `sudo systemctl status fermmon.service` |
| View logs | `journalctl -u fermmon -f` |

# creating a new brew

When you begin a new fermentation, set the current version so new readings are tagged correctly.

**Via Control page** (recommended): Open `/control`, pause recording, add the new version (number, brew name, optional URL), then start recording again.

**Via CLI**:

```bash
cd /home/ubuntu/fermmon
python scripts/set-current-version.py 15 "My New Brew Name" https://optional-url
```

Accepts version as `15` or `v15`. The version is added to the DB and marked current. No restart needed.

# backup and restore

Keep the DB on the Pi; back up to USB or NFS. SQLite on network/USB filesystems can be unreliable, so avoid putting the live DB there.

**Backup** (uses `sqlite3 .backup` – safe while fermmon is running):

```bash
./scripts/backup-db.sh /media/usb/fermmon-backups   # USB mount
./scripts/backup-db.sh /mnt/nfs/fermmon-backups    # NFS mount
```

The script keeps the last 7 backups. Add to crontab for daily runs:

```
0 2 * * * /home/ubuntu/fermmon/scripts/backup-db.sh /media/usb/fermmon-backups
```

**Restore**: Stop fermmon, copy a backup over `data/fermmon.db`, start fermmon.

**USB mount**: Add to `/etc/fstab` so the drive mounts at boot (use UUID for reliability). Ensure the mount point exists and is writable.

# migration (one-time, when switching from CSV)

Imports `fermmon.csv`, `fermmon_v4.csv`, `fermmon_v5.csv`, etc., and `version.csv` into SQLite. Version IDs stored without "v" prefix (14 not v14).
```bash
cd /home/ubuntu/fermmon && php data/migrate-csv.php
```
Then restart fermmon.service. CSV files can be removed after verifying.

The R script (`fermmon.r`) is no longer needed; charts are now rendered client-side with Chart.js.

# charts

The web dashboard shows two Chart.js time-series:

1. **CO2 and tVOC over Time** – Primary fermentation indicators. When CO2 drops toward the "normal air" reference (1000 ppm for a small indoor room), fermentation is likely done.
2. **Temperature and Humidity over Time** – Internal/external temp, humidity, and heat belt status.

The X-axis shows **Day 0, Day 1, Day 2...** from the first reading of the selected brew, with the start date in the axis title. A "Hide outliers" toggle excludes sensor spikes (CO2/tVOC > 6000) so the chart scales to the baseline.

## Chart colours

Colours follow common IAQ and environmental conventions:

| Metric | Colour | Rationale |
|--------|--------|-----------|
| CO2 | Teal (#0d9488) | Common for air/gas in IAQ displays |
| tVOC | Amber (#d97706) | Organic/VOC, complementary to CO2 |
| Int. temp | Red (#dc2626) | Thermometer convention (warm) |
| Ext. temp | Orange (#ea580c) | Distinct from internal |
| Humidity | Cyan (#0891b2) | Water convention |
| Heat belt | Yellow (#f5c842) | Active/on |

Dashed reference lines show "normal air" (1000 ppm CO2, 200 ppb tVOC) for comparison.

# outliers

CO2/tVOC sensors can spike to 16k+ ppm/ppb while fermentation typically runs 1–3k. Options:

1. **Display filter** (default): "Hide outliers" toggle excludes readings > 6000. Chart scales to baseline.
2. **API**: `?max_co2=6000&max_tvoc=6000` on `/api/readings`.
3. **SQLite**: `php scripts/mark-outliers.php` adds `is_outlier` column for analysis.
4. **Data collection**: `fermmon.py` records all readings; the web "Hide outliers" toggle filters display.

# testing
* readTemp.py to check if the 1-wire temperature probe is working
* testRowi.py to check that Rowi is working
* check cron 
** journalctl -u cron -f 
* creck for debugging messages
** journalctl -u fermmon -f

# references

* https://www.sparkfun.com/sparkfun-environmental-combo-breakout-ens160-bme280-qwiic.html
* https://surplustronics.co.nz/products/7361-temperature-sensor-probe-ds18b20-stainless-steel?srsltid=AfmBOoqpXowka8xFb00-9X7AfwTPzsrlTYhQia4CZAeGhiJS74ml-dqN
* https://www.kiwi-warmer.co.nz/shop/product/826130/rowi-2-smart-plug-with-api/
* https://mangrovejacks.com/collections/starter-brewery/products/traditional-series-blonde-lager-starter-brewery
* https://allthingsfermented.nz/products/ss-brewtech-stainless-steel-weldless-thermowell
