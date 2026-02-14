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

    <div class="card mb-4">
        <div class="card-header">Temperature alerts</div>
        <div class="card-body">
            <p class="text-muted small">Target temp (heat belt trigger) and warning threshold. Alert when internal temp is ± threshold from target.</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Target temp (°C)</label>
                    <input type="number" class="form-control" id="targetTemp" min="10" max="30" step="0.5" value="<?= (float)($config['target_temp'] ?? 19.5) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Warning threshold (°C)</label>
                    <input type="number" class="form-control" id="tempWarningThreshold" min="1" max="10" step="0.5" value="<?= (float)($config['temp_warning_threshold'] ?? 3) ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary" id="btnSaveTemp">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Dashboard refresh</div>
        <div class="card-body">
            <p class="text-muted small">How often the dashboard polls for new data. Summary = latest readings; Charts = incremental chart update.</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Summary (s)</label>
                    <input type="number" class="form-control" id="summaryRefreshInterval" min="10" max="300" value="<?= (int)($config['summary_refresh_interval'] ?? 30) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Charts (s)</label>
                    <input type="number" class="form-control" id="chartUpdateInterval" min="60" max="3600" value="<?= (int)($config['chart_update_interval'] ?? 300) ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-secondary" id="btnSaveRefresh">Save</button>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="hideOutliers" <?= (($config['hide_outliers'] ?? '1') === '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="hideOutliers">Hide outliers (CO2/tVOC &gt; 6000)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Timing (advanced)</div>
        <div class="card-body">
            <p class="text-muted small">fermmon sampling and write intervals.</p>
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
        if (window.updateNavTimers) updateNavTimers(cfg);
        const recording = cfg.recording === '1';
        document.getElementById('recordingStatus').textContent = recording ? 'Recording' : 'Paused';
        document.getElementById('recordingStatus').className = 'badge ' + (recording ? 'bg-success' : 'bg-secondary');
        document.getElementById('btnToggle').textContent = recording ? 'Pause' : 'Start';
        document.getElementById('btnToggle').className = 'btn ' + (recording ? 'btn-warning' : 'btn-success');
    }

    // Dirty-state: highlight Save buttons when form values differ from initial
    const saveGroups = [
        { btn: 'btnSaveTemp', inputs: ['targetTemp', 'tempWarningThreshold'], getValues: () => [
            document.getElementById('targetTemp').value,
            document.getElementById('tempWarningThreshold').value
        ]},
        { btn: 'btnSaveRefresh', inputs: ['summaryRefreshInterval', 'chartUpdateInterval', 'hideOutliers'], getValues: () => [
            document.getElementById('summaryRefreshInterval').value,
            document.getElementById('chartUpdateInterval').value,
            document.getElementById('hideOutliers').checked ? '1' : '0'
        ]},
        { btn: 'btnSaveTiming', inputs: ['sampleInterval', 'writeInterval'], getValues: () => [
            document.getElementById('sampleInterval').value,
            document.getElementById('writeInterval').value
        ]}
    ];

    const initialValues = saveGroups.map(g => g.getValues());

    function setSaveButtonDirty(btnId, dirty) {
        const btn = document.getElementById(btnId);
        btn.classList.toggle('btn-primary', dirty);
        btn.classList.toggle('btn-outline-secondary', !dirty);
    }

    function checkDirty() {
        saveGroups.forEach((g, i) => {
            const current = g.getValues();
            const changed = current.some((v, j) => v !== initialValues[i][j]);
            setSaveButtonDirty(g.btn, changed);
        });
    }

    saveGroups.forEach(g => {
        g.inputs.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', checkDirty);
                el.addEventListener('change', checkDirty);
            }
        });
    });

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

    document.getElementById('btnSaveTemp').addEventListener('click', async () => {
        const tt = document.getElementById('targetTemp').value;
        const tw = document.getElementById('tempWarningThreshold').value;
        await fetch(base + '/api/config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ target_temp: tt, temp_warning_threshold: tw })
        });
        initialValues[0] = [tt, tw];
        checkDirty();
        alert('Saved. Note: fermmon.py uses its own TARGET_TEMP (19.5) for the heat belt.');
    });

    document.getElementById('btnSaveRefresh').addEventListener('click', async () => {
        const sri = document.getElementById('summaryRefreshInterval').value;
        const cui = document.getElementById('chartUpdateInterval').value;
        const ho = document.getElementById('hideOutliers').checked ? '1' : '0';
        await fetch(base + '/api/config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ summary_refresh_interval: sri, chart_update_interval: cui, hide_outliers: ho })
        });
        initialValues[1] = [sri, cui, ho];
        checkDirty();
        alert('Saved. Dashboard will use new settings on next load.');
    });

    document.getElementById('btnSaveTiming').addEventListener('click', async () => {
        const si = document.getElementById('sampleInterval').value;
        const wi = document.getElementById('writeInterval').value;
        await fetch(base + '/api/config', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sample_interval: si, write_interval: wi })
        });
        initialValues[2] = [si, wi];
        checkDirty();
        alert('Saved. fermmon will use new values on next cycle.');
    });

    updateStatus();
})();
</script>
</body>
</html>
