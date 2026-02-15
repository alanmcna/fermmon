<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log – Fermmon</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="container my-4">
    <h1>Log</h1>

    <div class="mb-4">
        <label class="form-label">Brew</label>
        <select class="form-select" id="brewLogVersion">
            <option value="">Choose a brew</option>
        </select>
    </div>

    <div id="brewLogContent" style="display:none">
        <div class="card mb-4">
            <div class="card-header">Add brew log entry</div>
            <div class="card-body">
                <p class="text-muted small">Add timeline entries (e.g. dry hop at day 2). Shown on the CO2 chart. Default: now (UTC).</p>
                <form id="addBrewLogForm">
                    <input type="hidden" name="version" id="brewLogVersionHidden">
                    <div class="mb-3">
                        <label class="form-label">Date / time</label>
                        <input type="datetime-local" class="form-control" name="date_time" id="brewLogDateTime">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Note (max 256 chars)</label>
                        <textarea class="form-control" name="note" id="brewLogNote" rows="3" maxlength="256" placeholder="e.g. Dry hop – Cascade (newlines allowed)" required></textarea>
                    </div>
                    <div class="mb-0">
                        <button type="submit" class="btn btn-success">Add</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Log entries for this brew</div>
            <div class="card-body">
                <p class="text-muted small">All log entries (chart may show a different date range).</p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead><tr><th>Date / time</th><th>Note</th><th></th></tr></thead>
                        <tbody id="brewLogEntriesBody"></tbody>
                    </table>
                </div>
                <p id="brewLogEntriesEmpty" class="text-muted small mb-0" style="display:none">No log entries yet.</p>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const base = window.location.origin;

    function escapeHtml(s) {
        const d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    async function loadVersions() {
        const r = await fetch(base + '/api/versions');
        const versions = await r.json();
        const logSel = document.getElementById('brewLogVersion');
        if (logSel) {
            logSel.innerHTML = '<option value="">Choose a brew</option>' + versions.map(v => {
                const label = 'v' + escapeHtml(v.version) + ' – ' + escapeHtml(v.brew || '');
                return `<option value="${escapeHtml(v.version)}">${label}</option>`;
            }).join('');
        }
    }

    function toLocalDatetimeLocal(d) {
        const pad = n => String(n).padStart(2, '0');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    async function loadLogEntries(version) {
        const tbody = document.getElementById('brewLogEntriesBody');
        const empty = document.getElementById('brewLogEntriesEmpty');
        if (!version) {
            tbody.innerHTML = '';
            empty.style.display = 'none';
            return;
        }
        const r = await fetch(base + '/api/versions/' + encodeURIComponent(version) + '/brew-logs');
        let logs = r.ok ? await r.json() : [];
        logs = logs.sort((a, b) => (a.date_time || '').localeCompare(b.date_time || ''));
        if (logs.length === 0) {
            tbody.innerHTML = '';
            empty.style.display = 'block';
        } else {
            empty.style.display = 'none';
            tbody.innerHTML = logs.map(log => {
                const dt = escapeHtml(log.date_time || '');
                const note = escapeHtml(log.note || '').replace(/\n/g, '<br>');
                const id = parseInt(log.id, 10) || 0;
                return `<tr><td>${dt}</td><td style="white-space:pre-wrap">${note}</td><td><button type="button" class="btn btn-outline-danger btn-sm brew-log-delete" data-id="${id}">Delete</button></td></tr>`;
            }).join('');
        }
    }

    document.getElementById('brewLogVersion')?.addEventListener('change', () => {
        const version = document.getElementById('brewLogVersion').value;
        const content = document.getElementById('brewLogContent');
        if (!version) {
            content.style.display = 'none';
            return;
        }
        content.style.display = 'block';
        document.getElementById('brewLogVersionHidden').value = version;
        document.getElementById('brewLogDateTime').value = toLocalDatetimeLocal(new Date());
        loadLogEntries(version);
    });

    document.getElementById('brewLogEntriesBody')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.brew-log-delete');
        if (!btn) return;
        const id = parseInt(btn.dataset.id, 10);
        const version = document.getElementById('brewLogVersion').value;
        if (!id || !version) return;
        if (!confirm('Delete this log entry?')) return;
        const r = await fetch(base + '/api/versions/' + encodeURIComponent(version) + '/brew-logs/' + id, { method: 'DELETE' });
        if (r.ok) {
            loadLogEntries(version);
        } else {
            const err = await r.json();
            alert(err.error || 'Failed to delete');
        }
    });

    document.getElementById('addBrewLogForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const version = document.getElementById('brewLogVersionHidden').value;
        const dtInput = document.getElementById('brewLogDateTime').value;
        const note = document.getElementById('brewLogNote').value.trim();
        if (!version || !note) return;
        const dateTime = dtInput ? new Date(dtInput).toISOString().slice(0, 19).replace('T', ' ') : new Date().toISOString().slice(0, 19).replace('T', ' ');
        const r = await fetch(base + '/api/versions/' + encodeURIComponent(version) + '/brew-logs', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ date_time: dateTime, note })
        });
        if (r.ok) {
            document.getElementById('brewLogNote').value = '';
            document.getElementById('brewLogDateTime').value = toLocalDatetimeLocal(new Date());
            loadLogEntries(version);
            alert('Log entry added.');
        } else {
            const err = await r.json();
            alert(err.error || 'Failed');
        }
    });

    loadVersions();
})();
</script>
</body>
</html>
