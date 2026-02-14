# Debugging fermmon

## Design note: never drop data silently

If data is ever filtered or dropped at collection time (e.g. in fermmon.py), it must be logged clearly. Silently not recording data is a significant failure mode—users may not notice for days. Prefer recording everything and filtering at display time (web "Hide outliers" toggle).

## Hide outliers not persisting

If the "Hide outliers" setting on the Control page doesn't stay enabled (or doesn't take effect), check the following.

### 1. Verify config in SQLite

From the fermmon project root:

```bash
sqlite3 data/fermmon.db "SELECT * FROM config WHERE key='hide_outliers';"
```

Expected: `hide_outliers|1` (enabled) or `hide_outliers|0` (disabled). If the row is missing, the schema default hasn't been applied.

### 2. Check database permissions

Apache (www-data) needs write access to the database:

```bash
ls -la data/fermmon.db
# Should show group write (e.g. -rw-rw-r-- with ubuntu or www-data group)
```

If the DB is not group-writable, run:

```bash
sudo ./scripts/setup-web-permissions.sh
sudo systemctl restart apache2
```

### 3. Test the config API

**GET** (should return `hide_outliers`):

```bash
curl -s http://localhost/api/config | jq .
# or without jq:
curl -s http://localhost/api/config
```

**POST** (save hide_outliers=1):

```bash
curl -s -X POST http://localhost/api/config \
  -H "Content-Type: application/json" \
  -d '{"hide_outliers":"1"}' | jq .
```

Then check SQLite again to confirm the value was written.

### 4. Apache / PHP error logs

- **Apache error log**: `/var/log/apache2/fermmon-error.log` (or `error.log`)
- **PHP errors**: Often in the Apache error log, or check `php.ini` for `error_log`

```bash
sudo tail -f /var/log/apache2/fermmon-error.log
```

Reproduce the issue (click Save on Control page) and watch for errors.

### 5. Browser Network tab

1. Open DevTools (F12) → Network
2. Go to Control page, toggle Hide outliers, click Save
3. Find the `POST /api/config` request
4. Check: Status 200, Response body includes `"hide_outliers":"1"` or `"0"`

If the POST fails (4xx/5xx) or the response doesn't include the updated value, the problem is server-side.
