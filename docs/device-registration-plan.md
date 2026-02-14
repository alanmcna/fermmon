# Device Registration & Control Auth – Implementation Plan

## Overview

Secure the Control page and introduce a device registration flow so that:
- Only allowed devices can push readings
- Devices are approved via the Control UI
- New brews can be scoped to a specific device

---

## 1. Control Page Auth

**Goal**: Protect `/control` (and any write APIs) when the app is exposed online.

| Approach | Pros | Cons |
|----------|------|------|
| HTTP Basic Auth | Simple, no DB changes | Weak, no user management |
| Session + login form | Familiar UX | Needs session store, password storage |
| API key in config | Simple, scriptable | Single shared secret |

**Recommendation**: Start with HTTP Basic Auth (username/password in config or env). Add a login form + session later if needed.

**Config**: `control_user`, `control_password` (or env). If unset, Control is open (backward compat for local-only).

---

## 2. Device Model

**Device** = a fermmon instance (Pi + sensor) that pushes readings.

| Field | Purpose |
|-------|---------|
| `id` | UUID or short ID (e.g. `ferm-abc123`) |
| `name` | Optional label (e.g. "Garage fermenter") |
| `token` | Secret used to authenticate POST requests |
| `status` | `pending` \| `allowed` \| `revoked` |
| `created_at` | When first seen |
| `allowed_at` | When user approved (null if pending) |

**Token**: Generated on first contact or when user approves. Device stores it (env or config file) and sends it with every POST.

---

## 3. Registration Flow

```
┌─────────────┐                    ┌─────────────┐                    ┌─────────────┐
│   Device    │                    │  Web API    │                    │   Control   │
│ (fermmon)   │                    │             │                    │     UI      │
└──────┬──────┘                    └──────┬──────┘                    └──────┬──────┘
       │                                  │                                  │
       │  POST /api/readings               │                                  │
       │  (device_id, token or none)       │                                  │
       │─────────────────────────────────>│                                  │
       │                                  │                                  │
       │                    ┌──────────────┴──────────────┐                   │
       │                    │ Device unknown?             │                   │
       │                    │ → Create pending device      │                   │
       │                    │ → Return 403 + "pending"    │                   │
       │                    │                             │                   │
       │                    │ Device allowed?             │                   │
       │                    │ → Accept reading             │                   │
       │                    │                             │                   │
       │                    │ Device revoked?             │                   │
       │                    │ → Return 403                 │                   │
       │                    └──────────────┬──────────────┘                   │
       │                                  │                                  │
       │ 201 or 403                       │                                  │
       │<─────────────────────────────────│                                  │
       │                                  │                                  │
       │                                  │  User opens Control              │
       │                                  │<─────────────────────────────────│
       │                                  │                                  │
       │                                  │  GET /api/devices (pending)       │
       │                                  │<─────────────────────────────────│
       │                                  │                                  │
       │                                  │  User clicks "Allow"              │
       │                                  │  POST /api/devices/{id}/allow     │
       │                                  │<─────────────────────────────────│
       │                                  │                                  │
       │                                  │  Generate token, set status=allowed│
       │                                  │  Return token to UI (show once)   │
       │                                  │─────────────────────────────────>│
       │                                  │                                  │
       │  Next POST includes token         │                                  │
       │  → 201                            │                                  │
       │<─────────────────────────────────│                                  │
```

---

## 4. API Changes

### 4.1 POST /api/readings

**Request** (add to existing body):

| Field | Required | Description |
|-------|----------|-------------|
| `device_id` | Yes* | Unique device identifier (e.g. MAC, hostname, or generated UUID) |
| `device_token` | Yes* | Auth token (required once device is allowed) |

\* For backward compat: if both omitted, treat as "legacy" single-fermenter mode (no device model). When device model is enabled, require both.

**Responses**:
- `201` – Reading stored
- `403` – Device pending (include `{"error": "pending", "device_id": "..."}`)
- `403` – Device revoked or token invalid
- `400` – Missing required fields

### 4.2 New: GET /api/devices

List devices. Query: `?status=pending|allowed|revoked` (default: all).

**Response**: `[{ id, name, status, created_at, allowed_at }, ...]`

### 4.3 New: POST /api/devices/{id}/allow

Approve a pending device. Optionally set `name`. Returns `{ token }` – show once for user to copy to device.

### 4.4 New: POST /api/devices/{id}/revoke

Revoke device. Future POSTs from that device return 403.

---

## 5. Version (Brew) ↔ Device

**Option A – Device per version**: When adding a version, optionally select which device(s) it applies to. Readings from other devices are ignored for that version.

**Option B – Version per device**: Each device has a "current version" (like today). Adding a brew can target a specific device.

**Recommendation**: Start with **Option B** – simpler. `versions` table gets optional `device_id`. If set, only that device's readings use this version. If null, any device can use it (backward compat).

Schema addition:
```sql
ALTER TABLE versions ADD COLUMN device_id TEXT REFERENCES devices(id);
```

---

## 6. Schema Additions

```sql
CREATE TABLE devices (
    id TEXT PRIMARY KEY,           -- device_id from fermmon (e.g. MAC or UUID)
    name TEXT,
    token TEXT NOT NULL UNIQUE,    -- hashed or plain (start plain for simplicity)
    status TEXT DEFAULT 'pending', -- pending | allowed | revoked
    created_at TEXT DEFAULT (datetime('now')),
    allowed_at TEXT
);

CREATE INDEX idx_devices_status ON devices(status);
CREATE INDEX idx_devices_token ON devices(token);

-- Optional: link versions to devices
ALTER TABLE versions ADD COLUMN device_id TEXT REFERENCES devices(id);
```

---

## 7. fermmon.py Changes

1. **Device ID**: Generate once and persist (e.g. `~/.fermmon/device_id` or `/etc/fermmon/device_id`). Options: MAC address, `uuid.uuid4()`, or hostname.
2. **Device token**: After approval, user copies token. Store in `API_TOKEN` env or `~/.fermmon/token`.
3. **POST body**: Add `device_id` and `device_token` to every request when both are set.
4. **403 handling**: If `error: "pending"`, log "Device pending approval in Control" and retry later. Don't fall back to local DB for pending devices (optional: still fall back for network errors).

---

## 8. Control UI Changes

1. **Auth**: If `control_user`/`control_password` set, prompt before showing Control. Use HTTP Basic or a login form.
2. **Devices section**: New "Devices" block:
   - Table: device_id, name, status, created_at
   - Pending: "Allow" button → calls allow API, shows token in modal (copy to device)
   - Allowed: "Revoke" button
3. **Add version**: Optional "Device" dropdown (if devices exist). If selected, only that device's readings use this version.

---

## 9. Implementation Order

1. **Schema** – Add `devices` table
2. **DataService** – `getDevices()`, `allowDevice()`, `revokeDevice()`, device lookup by id/token
3. **POST /api/readings** – Device validation (with legacy fallback when no device_id)
4. **GET/POST devices APIs**
5. **Control UI** – Devices section
6. **fermmon.py** – device_id, token, 403 handling
7. **Control auth** – Basic auth or login form
8. **Version ↔ device** – Optional `device_id` on versions, filter logic

---

## 10. Security Notes

- **Token storage**: Start with plain tokens. Move to hashed tokens (e.g. bcrypt) when hardening.
- **HTTPS**: Required when Control is online; tokens in transit must be protected.
- **Rate limiting**: Consider limiting POST /api/readings per device to reduce abuse.
