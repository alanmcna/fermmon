<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Control – Fermmon</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="container my-4">
    <h1>Control</h1>

    <div class="card mb-4">
        <div class="card-header">Data recorder</div>
        <div class="card-body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span id="recordingStatus" class="badge">—</span>
                <button type="button" class="btn btn-primary" id="btnToggle">—</button>
            </div>
            <p class="text-muted small mb-0">When paused, fermmon keeps running (temp/relay) but stops writing readings. Use when changing batches.</p>
        </div>
    </div>

    <div class="card mb-4" id="addVersionCard">
        <div class="card-header">Add new version</div>
        <div class="card-body">
            <p class="text-muted small">Add a new brew and set it as current. Best done when recorder is paused.</p>
            <form id="addVersionForm" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Version</label>
                    <input type="text" class="form-control" name="version" placeholder="15" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Brew name</label>
                    <input type="text" class="form-control" name="brew" placeholder="My New IPA" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">URL (optional)</label>
                    <input type="url" class="form-control" name="url" placeholder="https://...">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-success">Add and set current</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Timing (advanced)</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Sample interval (s)</label>
                    <input type="number" class="form-control" id="sampleInterval" min="5" max="60" value="<?= (int)($config['sample_interval'] ?? 10) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Write interval (s)</label>
                    <input type="number" class="form-control" id="writeInterval" min="60" max="3600" value="<?= (int)($config['write_interval'] ?? 300) ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary" id="btnSaveTiming">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const base = window.location.origin;

    async function getConfig() {
        const r = await fetch(base + '/api/config');
        return r.json();
    }

    async function updateStatus() {
        const cfg = await getConfig();
        const recording = cfg.recording === '1';
        document.getElementById('recordingStatus').textContent = recording ? 'Recording' : 'Paused';
        document.getElementById('recordingStatus').className = 'badge ' + (recording ? 'bg-success' : 'bg-secondary');
        document.getElementById('btnToggle').textContent = recording ? 'Pause' : 'Start';
        document.getElementById('btnToggle').className = 'btn ' + (recording ? 'btn-warning' : 'btn-success');
    }

    document.getElementById('btnToggle').addEventListener('click', async () => {
        const cfg = await getConfig();
        const next = cfg.recording === '1' ? '0' : '1';
        await fetch(base + '/api/config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ recording: next })
        });
        updateStatus();
    });

    document.getElementById('addVersionForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const r = await fetch(base + '/api/versions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                version: fd.get('version'),
                brew: fd.get('brew'),
                url: fd.get('url') || ''
            })
        });
        if (r.ok) {
            e.target.reset();
            alert('Version added. You can start recording.');
        } else {
            const err = await r.json();
            alert(err.error || 'Failed');
        }
    });

    document.getElementById('btnSaveTiming').addEventListener('click', async () => {
        const si = document.getElementById('sampleInterval').value;
        const wi = document.getElementById('writeInterval').value;
        await fetch(base + '/api/config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sample_interval: si, write_interval: wi })
        });
        alert('Saved. fermmon will use new values on next cycle.');
    });

    updateStatus();
})();
</script>
</body>
</html>
