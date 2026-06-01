import qwiic_ccs811
import time, datetime
import glob, os
import sys
import sqlite3
import json
import ssl
import urllib.request
import urllib.error
from rowi import Rowi, RowiError

TARGET_TEMP = 19.5
LOW_TEMP_WARNING = 10

# API URL for remote push (e.g. https://localhost:443). If set, readings are POSTed here instead of local SQLite.
API_URL = os.environ.get('API_URL', 'https://localhost:443')

# SQLite DB path (relative to script directory)
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH = os.path.join(BASE_DIR, 'data', 'fermmon.db')


def get_db():
    """Get SQLite connection, creating schema if needed."""
    os.makedirs(os.path.dirname(DB_PATH), exist_ok=True)
    conn = sqlite3.connect(DB_PATH)
    conn.executescript(open(os.path.join(BASE_DIR, 'data', 'schema.sql')).read())
    return conn


def _version_id(v):
    """Normalize version to numeric ID (14 not v14)."""
    s = str(v).strip()
    return s.lstrip('vV') if s.lower().startswith('v') else s


def migrate_version_csv(conn):
    """Import version.csv into versions table if versions is empty."""
    cur = conn.execute("SELECT COUNT(*) FROM versions")
    if cur.fetchone()[0] > 0:
        return  # already populated
    version_csv = os.path.join(BASE_DIR, 'version.csv')
    if not os.path.exists(version_csv):
        return
    with open(version_csv, 'r') as f:
        lines = [l.strip() for l in f if l.strip()]
    for i, line in enumerate(lines):
        parts = line.split(',', 2)
        if len(parts) >= 2:
            version, brew = _version_id(parts[0]), parts[1].strip()
            url = parts[2].strip() if len(parts) > 2 else ''
            conn.execute(
                "INSERT OR IGNORE INTO versions (version, brew, url, is_current) VALUES (?, ?, ?, 0)",
                (version, brew, url)
            )
    if lines:
        first_version = _version_id(lines[0].split(',', 1)[0])
        conn.execute("UPDATE versions SET is_current = 0")
        conn.execute("UPDATE versions SET is_current = 1 WHERE version = ?", (first_version,))
    conn.commit()
    print("info: migrated version.csv into SQLite")


def get_version(conn):
    """Get current version and brew from SQLite (or migrate from version.csv)."""
    migrate_version_csv(conn)
    cur = conn.execute("SELECT version, brew FROM versions WHERE is_current = 1 LIMIT 1")
    row = cur.fetchone()
    if row:
        version, brew = row
        print("info: version:%s, brew:%s" % (version, brew))
        return version, brew
    raise RuntimeError("No current version set. Create data/fermmon.db and add a row to versions with is_current=1, or ensure version.csv exists.")


def get_config(conn):
    """Get config dict from DB. Creates table if missing."""
    try:
        cur = conn.execute("SELECT key, value FROM config")
        return {r[0]: r[1] for r in cur.fetchall()}
    except sqlite3.OperationalError:
        conn.executescript(open(os.path.join(BASE_DIR, 'data', 'schema.sql')).read())
        return {'recording': '1', 'sample_interval': '10', 'write_interval': '300'}


def write_heartbeat(conn):
    """Update fermmon_heartbeat in config so the web UI can detect a dead process.
    Stored as naive local time (same format as readings.date_time) so PHP can
    compute age with strtotime() in the server's local timezone."""
    conn.execute(
        "INSERT OR REPLACE INTO config (key, value) VALUES ('fermmon_heartbeat', ?)",
        (datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S"),)
    )
    conn.commit()


def post_heartbeat_to_api():
    """POST a heartbeat to the web API so the remote DB reflects that the
    recorder is alive. In a split web + recorder deployment the web app reads
    the heartbeat from its own DB, so a purely local heartbeat (write_heartbeat)
    would always look 'not running' to the UI. The server timestamps the
    heartbeat itself, avoiding clock skew between the Pi and the web host.
    Returns True on success, False on error."""
    if not API_URL or not API_URL.strip():
        return False
    url = API_URL.rstrip('/') + '/api/heartbeat'
    req = urllib.request.Request(url, data=b'{}', method='POST',
                                  headers={'Content-Type': 'application/json'})
    try:
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE  # allow self-signed certs for localhost
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            return 200 <= resp.getcode() < 300
    except Exception as e:
        print("error: heartbeat API error - %s" % e, file=sys.stderr)
    return False


def get_config_from_api():
    """Fetch config from the web API so a remote recorder honors Start/Pause and
    interval changes made in the UI (which write to the web DB, not the Pi's).
    Returns a dict of str->str on success, or None on error so the caller can
    fall back to local config."""
    if not API_URL or not API_URL.strip():
        return None
    url = API_URL.rstrip('/') + '/api/config'
    req = urllib.request.Request(url, method='GET')
    try:
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE  # allow self-signed certs for localhost
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            if 200 <= resp.getcode() < 300:
                data = json.loads(resp.read().decode('utf-8'))
                if isinstance(data, dict):
                    return {k: str(v) for k, v in data.items()}
    except Exception as e:
        print("error: config API error - %s" % e, file=sys.stderr)
    return None


def write_reading(conn, date_time, co2, tvoc, temp, version, rtemp, rhumi, relay):
    """Insert a reading into SQLite."""
    conn.execute(
        """INSERT OR IGNORE INTO readings (date_time, co2, tvoc, temp, version, rtemp, rhumi, relay)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?)""",
        (date_time, co2, tvoc, temp, version, rtemp, rhumi, relay)
    )
    conn.commit()
    print("info: writing result - %s" % date_time)


def post_reading_to_api(date_time, co2, tvoc, temp, version, rtemp, rhumi, relay):
    """POST a reading to the web API. Returns True on success, None if declined (409), False on error."""
    if not API_URL or not API_URL.strip():
        return False
    url = API_URL.rstrip('/') + '/api/readings'
    payload = json.dumps({
        'date_time': date_time,
        'co2': co2,
        'tvoc': tvoc,
        'temp': temp,
        'version': version,
        'rtemp': rtemp,
        'rhumi': rhumi,
        'relay': relay,
    }).encode('utf-8')
    req = urllib.request.Request(url, data=payload, method='POST',
                                  headers={'Content-Type': 'application/json'})
    try:
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE  # allow self-signed certs for localhost
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            if 200 <= resp.getcode() < 300:
                print("info: posted to API - %s" % date_time)
                return True
    except urllib.error.HTTPError as e:
        body = e.read().decode()[:200]
        print("error: API HTTP %d - %s" % (e.code, body), file=sys.stderr)
        if e.code == 409:
            print("info: reading declined (brew finished) - %s" % date_time)
            return None  # declined; do not fall back to local SQLite
    except Exception as e:
        print("error: API error - %s" % e, file=sys.stderr)
    return False


# function to read ds18b20 probe
def ds18B20(SensorID):
    try:
        f = open( "/sys/bus/w1/devices/" + SensorID + "/w1_slave")
        txt = f.read()
        f.close()
        line = txt.split("\n")[0]
        crc  = line.split("crc=")[1]
        if crc.find("YES")<0:
            return None
    except:
        return None
    line = txt.split("\n")[1]
    txt_temp = line.split(" ")[9]
    return (float(txt_temp[2:])/1000.0)


ccs811Sensor = qwiic_ccs811.QwiicCcs811()

if ccs811Sensor.is_connected() == False:
    print("Error: the qwiic CCS811 device isn't connected to the system - please check your connection", file=sys.stderr)
    sys.exit(1)

ccs811Sensor.begin()

tempSensor = ''

for ts in glob.glob("/sys/bus/w1/devices/28-*"):
    tempSensor = ts.split('/')[5]
    break  # just grab the first one

# Defaults; overridden by config table (sample_interval, write_interval).
# recording=0 in config stops writing to DB.
SAMPLE_INTERVAL = 10
WRITE_INTERVAL = 300

co2 = tvoc = incr = 0

r = Rowi()

conn = get_db()
version, brew = get_version(conn)

while True:
    # Wrap the whole loop so transient failures (I2C glitch, SQLite lock,
    # network blip on the rowi) don't exit the process and chew through
    # systemd's restart budget. systemd still restarts us on real crashes.
    try:
        # In API mode push the heartbeat to the web DB (otherwise the UI, which
        # reads its own DB, can't see the recorder). Fall back to local on error.
        if API_URL and API_URL.strip():
            if not post_heartbeat_to_api():
                write_heartbeat(conn)
        else:
            write_heartbeat(conn)
        # In API mode read config from the web DB so UI changes (Start/Pause,
        # intervals) reach a remote recorder. Fall back to local on error.
        cfg = None
        if API_URL and API_URL.strip():
            cfg = get_config_from_api()
        if cfg is None:
            cfg = get_config(conn)
        recording = cfg.get('recording', '1') == '1'
        sample_interval = int(cfg.get('sample_interval', str(SAMPLE_INTERVAL)))
        write_interval = int(cfg.get('write_interval', str(WRITE_INTERVAL)))
        # get (external) temp and humidity from the rowi and set the ccs811 env data.
        # If the rowi is offline, skip env compensation (and relay control below)
        # for this iteration rather than aborting the whole loop; CCS811 sampling
        # and recording continue with rtemp/rhumi left unset.
        rtemp = rhumi = None
        rowi_online = True
        try:
            rtemp, rhumi = r.getTemperature()
            ccs811Sensor.set_environmental_data(rhumi, rtemp)
        except RowiError as e:
            rowi_online = False
            print("warn: rowi offline, skipping temp/humidity (%s)" % e, file=sys.stderr)

        if ccs811Sensor.data_available():
            ccs811Sensor.read_algorithm_results()

            co2 += ccs811Sensor.get_co2()
            tvoc += ccs811Sensor.get_tvoc()
            print("debug: co2:%.02f, tvoc:%.02f, incr:%d" % (co2, tvoc, incr));

            incr += sample_interval
        else:
            print("debug: ccs811 data unavailable (incr:%d)" % incr);

        if incr >= write_interval:
            n = incr / sample_interval
            co2 = co2 / n
            tvoc = tvoc / n

            temp = ds18B20(tempSensor)  # internal temperature
            if temp is None:
                temp = 0  # just hack it

            # Relay (heat belt) control needs the rowi; skip it when offline and
            # record relay as unknown (None) so the reading is still written.
            relay = None
            if rowi_online:
                try:
                    if temp > 12 and temp < TARGET_TEMP and r.getRelayStatus() == "0":
                        r.setRelayStatus(True)
                        print("debug: turning on rowi - too cold")
                    elif temp >= TARGET_TEMP and r.getRelayStatus() == "1":
                        r.setRelayStatus(False)
                        print("debug: turning off rowi - too hot")
                    elif temp < LOW_TEMP_WARNING and r.getRelayStatus() == "1":
                        r.setRelayStatus(False)
                        print("debug: turning off rowi - bogus temp reading")

                    relay = r.getRelayStatus()
                except RowiError as e:
                    print("warn: rowi offline, skipping relay control (%s)" % e, file=sys.stderr)
            else:
                print("debug: rowi offline - skipping relay control")

            if not recording:
                print("debug: recording paused - skipping write")
                incr = co2 = tvoc = 0
                time.sleep(sample_interval)
                continue

            # Refresh version from DB (picks up new versions added via Control page)
            version, brew = get_version(conn)
            version_id = _version_id(version)

            # Record all readings; web "Hide outliers" filters display. If filtering is ever
            # re-added here, it must be logged (e.g. print("warn: skipping outlier ...")).
            date_time = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")

            if API_URL and API_URL.strip():
                result = post_reading_to_api(date_time, co2, tvoc, temp, version_id, rtemp, rhumi, relay)
                if result is False:
                    write_reading(conn, date_time, co2, tvoc, temp, version_id, rtemp, rhumi, relay)
                # result is True: success. result is None: declined (409), do not store
            else:
                write_reading(conn, date_time, co2, tvoc, temp, version_id, rtemp, rhumi, relay)

            # reset vars
            incr = co2 = tvoc = 0

        print("debug: sleep %ds (incr:%d, write at %d)" % (sample_interval, incr, write_interval));
        time.sleep(sample_interval)
    except KeyboardInterrupt:
        raise
    except Exception as e:
        print("error: main loop exception (%s: %s) - continuing" % (type(e).__name__, e), file=sys.stderr)
        time.sleep(SAMPLE_INTERVAL)
