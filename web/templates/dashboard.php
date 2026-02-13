<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d6efd">
    <meta http-equiv="cache-control" content="no-cache">
    <meta http-equiv="expires" content="Tue, 01 Jan 1980 1:00:00 GMT">
    <meta http-equiv="pragma" content="no-cache">
    <title>Brew (Fermentor) Monitor</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="manifest" href="/manifest.json">
    <script>if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js');</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root { --bs-body-font-family: 'Segoe UI', system-ui, sans-serif; }
        .chart-container { position: relative; height: 280px; margin-bottom: 2rem; }
        .chart-container canvas { max-height: 280px; }
        @media (min-width: 768px) { .chart-container { height: 360px; } .chart-container canvas { max-height: 360px; } }
        #loadingOverlay { position: fixed; inset: 0; background: rgba(255,255,255,0.9); display: flex; align-items: center; justify-content: center; z-index: 9999; }
        #loadingOverlay.hidden { display: none !important; }
        #loadingOverlay img { width: 80px; height: 80px; animation: pulse 1.2s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 0.7; } 50% { opacity: 1; } }
    </style>
</head>
<body>
<div id="loadingOverlay"><img src="/fermmon-logo.png" alt="Loading"></div>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="container my-4">
    <?php if (!empty($versions)): ?>
    <div class="row mb-3">
        <div class="col">
            <select class="form-select fw-bold" id="versionFilter">
                <?php foreach ($versions as $v): ?>
                <option value="<?= htmlspecialchars($v['version']) ?>" <?= ($v['version'] === ($currentVersion ?? '')) ? 'selected' : '' ?>>
                    <?= htmlspecialchars('v' . $v['version'] . ' – ' . $v['brew']) ?><?= ($v['is_current'] ?? 0) ? ' (current)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="row mb-2">
        <div class="col">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="hideOutliers" checked>
                <label class="form-check-label" for="hideOutliers">Hide outliers (CO2/tVOC &gt; 6000)</label>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="row"><div class="col"><hr/></div></div>

    <div class="row">
        <div class="col"><b>Latest Reading:</b></div>
        <div class="col text-end" id="dateTime"><?= htmlspecialchars($latest['date_time'] ?? '—') ?></div>
    </div>
    <div class="row"><div class="col"><hr/></div></div>

    <div class="row">
        <div class="col"><b>Int. Temperature:</b></div>
        <div class="col text-end" id="intTemp"><?= $latest ? number_format($latest['temp'], 1) . ' °C' : '—' ?></div>
    </div>
    <div class="row"><div class="col"><hr/></div></div>

    <div class="row">
        <div class="col"><b>Heat Belt:</b></div>
        <div class="col text-end" id="heatBelt"><?= $latest ? ($latest['relay'] ? 'On' : 'Off') : '—' ?></div>
    </div>
    <div class="row">
        <div class="col"><b>Env. Temperature:</b></div>
        <div class="col text-end" id="envTemp"><?= $latest ? number_format($latest['rtemp'], 1) . ' °C' : '—' ?></div>
    </div>
    <div class="row">
        <div class="col"><b>Env. Humidity:</b></div>
        <div class="col text-end" id="envHumi"><?= $latest ? number_format($latest['rhumi'], 1) . ' %' : '—' ?></div>
    </div>
    <div class="row"><div class="col"><hr/></div></div>

    <div class="row">
        <div class="col"><b>CO2:</b></div>
        <div class="col text-end" id="co2"><?= $latest ? number_format($latest['co2'], 0) . ' ppm' : '—' ?></div>
    </div>
    <div class="row">
        <div class="col"><b>tVOC:</b></div>
        <div class="col text-end" id="tVOC"><?= $latest ? number_format($latest['tvoc'], 0) . ' ppb' : '—' ?></div>
    </div>
    <div class="row"><div class="col"><hr/></div></div>

    <div class="chart-container">
        <canvas id="chartCO2"></canvas>
    </div>
    <div class="chart-container">
        <canvas id="chartTemp"></canvas>
    </div>
</div>

<script>
(function() {
    const baseUrl = window.location.origin;
    let chartCO2, chartTemp;

    async function fetchLatest(version) {
        let url = baseUrl + '/api/latest?t=' + Date.now();
        if (version) url += '&version=' + encodeURIComponent(version);
        const r = await fetch(url);
        if (!r.ok) return;
        const d = await r.json();
        document.getElementById('dateTime').textContent = d.date_time ? new Date(d.date_time + ' UTC').toLocaleString() : '—';
        document.getElementById('intTemp').textContent = d.temp != null ? d.temp.toFixed(1) + ' °C' : '—';
        document.getElementById('heatBelt').textContent = d.relay ? 'On' : 'Off';
        document.getElementById('envTemp').textContent = d.rtemp != null ? d.rtemp.toFixed(1) + ' °C' : '—';
        document.getElementById('envHumi').textContent = d.rhumi != null ? d.rhumi.toFixed(1) + ' %' : '—';
        document.getElementById('co2').textContent = d.co2 != null ? Math.round(d.co2) + ' ppm' : '—';
        document.getElementById('tVOC').textContent = d.tvoc != null ? Math.round(d.tvoc) + ' ppb' : '—';
    }

    async function fetchReadings(version) {
        let url = baseUrl + '/api/readings?limit=0';
        if (version) url += '&version=' + encodeURIComponent(version);
        const hide = document.getElementById('hideOutliers');
        if (hide && hide.checked) {
            url += '&max_co2=6000&max_tvoc=6000';
        }
        const r = await fetch(url);
        if (!r.ok) return [];
        return r.json();
    }

    function initCharts(readings) {
        const parseDt = (dt) => new Date(dt.replace(' ', 'T') + 'Z').getTime();
        const t0 = readings.length ? parseDt(readings[0].date_time) : 0;
        const day = (dt) => (parseDt(dt) - t0) / (24 * 60 * 60 * 1000);
        const co2 = readings.map(r => ({ x: day(r.date_time), y: r.co2 }));
        const tvoc = readings.map(r => ({ x: day(r.date_time), y: r.tvoc }));
        const temp = readings.map(r => ({ x: day(r.date_time), y: r.temp }));
        const rtemp = readings.map(r => ({ x: day(r.date_time), y: r.rtemp }));
        const rhumi = readings.map(r => ({ x: day(r.date_time), y: r.rhumi }));
        const relay = readings.map(r => ({ x: day(r.date_time), y: r.relay }));

        const maxCo2 = co2.length ? Math.max(...co2.map(p => p.y)) : 0;
        const maxTvoc = tvoc.length ? Math.max(...tvoc.map(p => p.y)) : 0;
        const maxDay = readings.length ? day(readings[readings.length - 1].date_time) : 0;

        const startLabel = readings.length ? new Date(readings[0].date_time.replace(' ', 'T') + 'Z').toLocaleDateString() : '';

        const xTick = (v) => v === 0 ? 'Start' : (v % 1 === 0 ? 'Day ' + v : '');

        const NORMAL_CO2 = 1000;
        const NORMAL_TVOC = 200;
        const co2Ref = co2.map(p => ({ x: p.x, y: NORMAL_CO2 }));
        const tvocRef = tvoc.map(p => ({ x: p.x, y: NORMAL_TVOC }));

        const colors = {
            co2: '#0d9488',      // teal - CO2/air common
            tvoc: '#d97706',     // amber - VOC/organic
            tempInt: '#dc2626',  // red - temp (thermometer)
            tempExt: '#ea580c',  // orange - external temp
            humidity: '#0891b2', // cyan - water
            relay: '#f5c842'     // sunshine yellow - heat belt
        };

        if (chartCO2) chartCO2.destroy();
        chartCO2 = new Chart(document.getElementById('chartCO2'), {
            type: 'line',
            data: {
                datasets: [
                    { label: 'CO2 (ppm)', data: co2, borderColor: colors.co2, backgroundColor: colors.co2 + '20', fill: true, pointRadius: 1, yAxisID: 'y' },
                    { label: 'tVOC (ppb)', data: tvoc, borderColor: colors.tvoc, backgroundColor: colors.tvoc + '20', fill: true, pointRadius: 1, yAxisID: 'y1' },
                    { label: 'Normal air (CO2)', data: co2Ref, borderColor: colors.co2 + '80', borderDash: [5, 5], fill: false, pointRadius: 0, yAxisID: 'y' },
                    { label: 'Normal air (tVOC)', data: tvocRef, borderColor: colors.tvoc + '80', borderDash: [5, 5], fill: false, pointRadius: 0, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { type: 'linear', min: 0, max: Math.max(1, maxDay),
                         ticks: { stepSize: 1, callback: (v) => xTick(v) },
                         title: { display: true, text: 'From ' + startLabel } },
                    y: { type: 'linear', position: 'left', min: 0, suggestedMax: Math.max(4000, maxCo2 * 1.1), title: { display: true, text: 'CO2 (ppm)' } },
                    y1: { type: 'linear', position: 'right', min: 0, suggestedMax: Math.max(4000, maxTvoc * 1.1), grid: { drawOnChartArea: false }, title: { display: true, text: 'tVOC (ppb)' } }
                },
                plugins: { title: { display: true, text: 'CO2 and tVOC over Time' },
                    tooltip: { callbacks: { title: (items) => {
                        const d = items[0]?.raw?.x;
                        const label = d === 0 ? 'Start' : 'Day ' + d.toFixed(1);
                        const idx = items[0]?.dataIndex;
                        const dt = readings[idx]?.date_time;
                        return dt ? label + ' (' + dt + ')' : label;
                    } } } }
            }
        });

        if (chartTemp) chartTemp.destroy();
        chartTemp = new Chart(document.getElementById('chartTemp'), {
            type: 'line',
            data: {
                datasets: [
                    { label: 'Int. Temp (°C)', data: temp, borderColor: colors.tempInt, backgroundColor: colors.tempInt + '20', fill: true, pointRadius: 1, yAxisID: 'y' },
                    { label: 'Ext. Temp (°C)', data: rtemp, borderColor: colors.tempExt, backgroundColor: colors.tempExt + '20', fill: true, pointRadius: 1, yAxisID: 'y' },
                    { label: 'Humidity (%)', data: rhumi, borderColor: colors.humidity, backgroundColor: colors.humidity + '20', fill: true, pointRadius: 1, yAxisID: 'y1' },
                    { label: 'Heat Belt (on/off)', data: relay, borderColor: colors.relay, backgroundColor: colors.relay + '30', fill: true, pointRadius: 1, yAxisID: 'y2', stepped: true }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { type: 'linear', min: 0, max: Math.max(1, maxDay),
                         ticks: { stepSize: 1, callback: (v) => xTick(v) },
                         title: { display: true, text: 'From ' + startLabel } },
                    y: { type: 'linear', position: 'left', min: 0, max: 50, title: { display: true, text: '°C' } },
                    y1: { type: 'linear', position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: '% Humidity' } },
                    y2: { type: 'linear', position: 'right', min: 0, max: 1, grid: { drawOnChartArea: false }, ticks: { stepSize: 1 } }
                },
                plugins: { title: { display: true, text: 'Temperature and Humidity over Time' },
                    tooltip: { callbacks: { title: (items) => {
                        const d = items[0]?.raw?.x;
                        const label = d === 0 ? 'Start' : 'Day ' + d.toFixed(1);
                        const idx = items[0]?.dataIndex;
                        const dt = readings[idx]?.date_time;
                        return dt ? label + ' (' + dt + ')' : label;
                    } } } }
            }
        });
    }

    async function refreshView() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.classList.remove('hidden');
        try {
            const sel = document.getElementById('versionFilter');
            const version = sel ? sel.value : null;
            const readings = await fetchReadings(version);
            if (readings.length) initCharts(readings);
            await fetchLatest(version);
        } finally {
            if (overlay) overlay.classList.add('hidden');
        }
    }

    const versionFilter = document.getElementById('versionFilter');
    if (versionFilter) versionFilter.addEventListener('change', refreshView);
    const hideOutliers = document.getElementById('hideOutliers');
    if (hideOutliers) hideOutliers.addEventListener('change', refreshView);

    refreshView();
    setInterval(() => {
        const sel = document.getElementById('versionFilter');
        if (sel) fetchLatest(sel.value);
    }, 30000);
})();
</script>
</body>
</html>
