# Multi-fermenter architecture plan

A plan for supporting multiple fermenters, with many `fermmon.py` instances running on individual Pi Zeros, but one central app and database.

## First step: move fermmon.py SQLite writes to a web API

### Rationale

- **Single source of truth** – one DB for all fermenters
- **fermmon.py becomes a thin sensor client** – reads hardware, POSTs data, no local storage
- **Same write path for all devices** – adding fermenters is mostly configuration
- **Central app validates and logs** – all incoming data flows through one place

### Trade-offs

1. **Network dependency** – If the Pi can’t reach the server, writes fail. Options:
   - Accept occasional data loss during outages
   - Add a local buffer (SQLite or JSON file) and retry when back online
   - Buffer adds complexity but improves robustness

2. **Latency** – Each write is a network call instead of a local DB write. With 5-minute intervals this is usually fine; at higher rates you might batch.

3. **Server availability** – The central server must be reachable from the Pi network (same LAN or VPN).

## Multi-fermenter data model

- Each Pi needs a stable identity: `device_id` or `fermenter_id` (config file, hostname, or MAC).
- `readings` and `versions` tables get a `device_id` column so each fermenter has its own brews and readings.
- Config becomes per-device where it matters (e.g. `target_temp`, `recording`) and possibly global for things like intervals.

## Phased approach

### Phase 1: API write endpoint

- Add `POST /api/readings` (or similar).
- fermmon.py POSTs instead of writing to SQLite.
- Single fermenter, single server.
- No schema changes yet.

### Phase 2: Introduce device identity

- Add `device_id` to schema and API.
- Existing single-fermenter setup uses a default `device_id` (e.g. `"default"`).

### Phase 3: Multiple fermenters

- Run multiple fermmon.py instances, each with its own `device_id`.
- All POST to the same central API.

## Config

- Currently fermmon.py reads config from the DB.
- With a remote DB, it would call `GET /api/config?device_id=X` instead.
- Recording state, target temp, etc. become per-device; intervals can stay global or also be per-device.

## Authentication

- The write API should be protected (API key, token, or mTLS) so only authorised Pis can POST.
- A shared secret per device or per deployment is a simple starting point.

## Summary

Moving writes to an API is a good first step: it decouples storage from the Pis and sets up the path to multiple fermenters. The main design choice is whether to add local buffering for offline resilience or keep the client thin and accept occasional data loss during outages.
