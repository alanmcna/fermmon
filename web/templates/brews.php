<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brews – Fermmon</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="container my-4">
    <h1>Brews</h1>

    <div class="card mb-4" id="addVersionCard">
        <div class="card-header">Add new version</div>
        <div class="card-body">
            <p class="text-muted small">Add a new brew and set it as current. Best done when recorder is paused (Control page).</p>
            <form id="addVersionForm">
                <div class="mb-3">
                    <label class="form-label">Version</label>
                    <input type="text" class="form-control" name="version" placeholder="15" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Brew name</label>
                    <input type="text" class="form-control" name="brew" placeholder="My New IPA" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">URL (optional)</label>
                    <input type="url" class="form-control" name="url" placeholder="https://...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Description (optional)</label>
                    <textarea class="form-control" name="description" rows="3" placeholder="Ingredients, hops, yeast, method customisations..."></textarea>
                </div>
                <div class="mb-0">
                    <button type="submit" class="btn btn-success">Add and set current</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">Update past brew details</div>
        <div class="card-body">
            <p class="text-muted small">Edit brew name, URL or description for an existing version.</p>
            <div class="mb-3">
                <label class="form-label">Version</label>
                <select class="form-select" id="editVersionSelect">
                    <option value="">Choose version to edit...</option>
                </select>
            </div>
            <div id="editVersionFormWrap" class="mt-3" style="display:none">
                <form id="editVersionForm">
                    <input type="hidden" name="version" id="editVersion">
                    <div class="mb-3">
                        <label class="form-label">Brew name</label>
                        <input type="text" class="form-control" name="brew" id="editBrew" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">URL (optional)</label>
                        <input type="url" class="form-control" name="url" id="editUrl">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (optional)</label>
                        <textarea class="form-control" name="description" id="editDescription" rows="4" placeholder="Ingredients, hops, yeast, method customisations..."></textarea>
                    </div>
                    <div class="mb-0">
                        <button type="button" class="btn btn-primary" id="btnSaveEditVersion">Save</button>
                    </div>
                </form>
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

    let versionsData = [];
    async function loadVersions() {
        const r = await fetch(base + '/api/versions');
        versionsData = await r.json();
        const editSel = document.getElementById('editVersionSelect');
        editSel.innerHTML = '<option value="">Choose version to edit...</option>' +
            versionsData.map(v => {
                const label = 'v' + escapeHtml(v.version) + ' – ' + escapeHtml(v.brew || '') + ((v.is_current || 0) ? ' (current)' : '');
                return `<option value="${escapeHtml(v.version)}">${label}</option>`;
            }).join('');
    }

    document.getElementById('addVersionForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const r = await fetch(base + '/api/versions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                version: fd.get('version'),
                brew: fd.get('brew'),
                url: fd.get('url') || '',
                description: fd.get('description') || ''
            })
        });
        if (r.ok) {
            e.target.reset();
            loadVersions();
            alert('Version added. You can start recording.');
        } else {
            const err = await r.json();
            alert(err.error || 'Failed');
        }
    });

    document.getElementById('editVersionSelect').addEventListener('change', () => {
        const version = document.getElementById('editVersionSelect').value;
        const wrap = document.getElementById('editVersionFormWrap');
        if (!version) {
            wrap.style.display = 'none';
            return;
        }
        const v = versionsData.find(x => x.version === version);
        if (!v) return;
        document.getElementById('editVersion').value = v.version;
        document.getElementById('editBrew').value = v.brew || '';
        document.getElementById('editUrl').value = v.url || '';
        document.getElementById('editDescription').value = v.description || '';
        wrap.style.display = 'block';
    });

    document.getElementById('btnSaveEditVersion').addEventListener('click', async () => {
        const version = document.getElementById('editVersion').value;
        const brew = document.getElementById('editBrew').value;
        const url = document.getElementById('editUrl').value;
        const description = document.getElementById('editDescription').value;
        const r = await fetch(base + '/api/versions/' + encodeURIComponent(version), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ brew, url, description })
        });
        if (r.ok) {
            document.getElementById('editVersionFormWrap').style.display = 'none';
            document.getElementById('editVersionSelect').value = '';
            loadVersions();
            alert('Saved.');
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
