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
    <script>if ('serviceWorker' in navigator) { fetch(window.location.origin + '/api/config?t=' + Date.now(), { cache: 'no-store' }).then(r => r.ok ? r.json() : {}).then(cfg => { const cacheApis = (cfg && cfg.cache_apis === '1') ? '1' : '0'; navigator.serviceWorker.register('/sw.js?cacheApis=' + cacheApis); }).catch(() => navigator.serviceWorker.register('/sw.js?cacheApis=0')); }</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root { --bs-body-font-family: 'Segoe UI', system-ui, sans-serif; }
        .chart-container { position: relative; height: 280px; margin-bottom: 2rem; }
        .chart-container canvas { max-height: 280px; }
        @media (min-width: 768px) { .chart-container { height: 360px; } .chart-container canvas { max-height: 360px; } }
        .chart-loading { position: absolute; inset: 0; background: rgba(255,255,255,0.85); display: flex; align-items: center; justify-content: center; z-index: 10; }
        .chart-loading.hidden { display: none !important; }
        .chart-loading-anim { width: 80px; height: 80px; }
        .chart-loading-anim img { width: 100%; height: 100%; animation: pulse 1.2s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 0.7; } 50% { opacity: 1; } }
        .chart-tooltip { position: fixed; padding: 8px 12px; background: rgba(0,0,0,0.85); color: #fff; border-radius: 6px; font-size: 12px; pointer-events: auto; z-index: 100; max-width: 280px; box-shadow: 0 2px 8px rgba(0,0,0,0.3); display: none; }
        .chart-tooltip .tt-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .chart-tooltip .tt-close { background: transparent; border: none; color: #fff; cursor: pointer; padding: 4px 8px; font-size: 18px; line-height: 1; opacity: 0.8; min-width: 36px; min-height: 36px; -webkit-tap-highlight-color: transparent; }
        .chart-tooltip .tt-close:hover { opacity: 1; }
        .brew-link { color: #374151; text-decoration: none; }
        .brew-link:hover { color: #111827; text-decoration: underline; }
    </style>
</head>
<body>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="container my-4">
    <?php if (!empty($versions)): ?>
    <div class="row mb-2">
        <div class="col">
            <select class="form-select fw-bold" id="versionFilter">
                <?php foreach ($versions as $v): ?>
                <option value="<?= htmlspecialchars($v['version']) ?>" data-brew="<?= htmlspecialchars($v['brew'] ?? '') ?>" data-url="<?= htmlspecialchars($v['url'] ?? '') ?>" <?= ($v['version'] === ($currentVersion ?? '')) ? 'selected' : '' ?>>
                    <?= htmlspecialchars('v' . $v['version'] . ' – ' . $v['brew']) ?><?= ($v['is_current'] ?? 0) ? ' (current)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>
    <div id="cachedBanner" class="alert alert-secondary py-2 small mb-2" style="display:none" role="status"></div>
    <div id="notificationPrompt" class="alert alert-light py-2 small mb-2" style="display:none">
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnEnableAlerts">Enable temperature alerts</button>
        <span class="ms-2 text-muted">Get notified when temp is ±3°C from target</span>
    </div>
    <div class="row"><div class="col"><hr/></div></div>

    <?php if (!empty($versions)): ?>
    <div class="row">
        <div class="col-12" id="brewInfo"></div>
    </div>
    <?php endif; ?>
    <div class="row">
        <div class="col"><b>Latest Reading:</b></div>
        <div class="col text-end" id="dateTime"><?= htmlspecialchars($latest['date_time'] ?? '—') ?></div>
    </div>
    <div class="row"><div class="col"><hr/></div></div>

    <div class="row">
        <div class="col"><b>Int. Temperature:</b></div>
        <div class="col text-end d-flex align-items-center justify-content-end gap-1">
            <span id="intTemp"><?= $latest ? number_format($latest['temp'], 1) . ' °C' : '—' ?></span>
            <?php
            $tempColor = '#94a3b8';
            if (isset($latest['temp'])) {
                $t = (float)$latest['temp'];
                $target = (float)(($config ?? [])['target_temp'] ?? 19.5);
                $th = (float)(($config ?? [])['temp_warning_threshold'] ?? 3);
                $d = $t - $target;
                $t1 = $th / 3; $t2 = $th * 2 / 3; $t3 = $th;
                if ($d >= $t3) $tempColor = '#dc2626';
                elseif ($d >= $t2) $tempColor = '#f97316';
                elseif ($d >= $t1) $tempColor = '#eab308';
                elseif ($d >= -$t1) $tempColor = '#22c55e';
                elseif ($d >= -$t2) $tempColor = '#38bdf8';
                elseif ($d >= -$t3) $tempColor = '#1d4ed8';
                else $tempColor = '#1e293b';
            }
            ?>
            <svg id="intTempIcon" width="18" height="26" viewBox="0 0 18 26" fill="none" xmlns="http://www.w3.org/2000/svg" class="flex-shrink-0" style="color:<?= $tempColor ?>" title="Temperature">
                <rect x="6" y="0" width="6" height="16" rx="2" fill="currentColor" opacity="0.3"/>
                <rect x="6" y="0" width="6" height="16" rx="2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                <circle cx="9" cy="22" r="4" fill="currentColor"/>
            </svg>
        </div>
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

    <div class="row mb-2 align-items-center">
        <div class="col-auto">
            <label class="form-label mb-0 me-2">Chart range:</label>
        </div>
        <div class="col-auto">
            <select class="form-select form-select-sm" id="chartRange" style="width: auto">
                <option value="24h" selected>Last 24 hours</option>
                <option value="5d">Last 5 days</option>
                <option value="all">All</option>
            </select>
        </div>
    </div>
    <div class="chart-container position-relative">
        <div class="chart-loading" id="chartCO2Loading">
            <div class="chart-loading-anim" aria-hidden="true"><img src="/fermmon-logo.png" alt="Loading"></div>
        </div>
        <canvas id="chartCO2"></canvas>
    </div>
    <div class="chart-container position-relative">
        <div class="chart-loading" id="chartTempLoading">
            <div class="chart-loading-anim" aria-hidden="true"><img src="/fermmon-logo.png" alt="Loading"></div>
        </div>
        <canvas id="chartTemp"></canvas>
    </div>
    <div id="chartTooltip" class="chart-tooltip" role="tooltip"></div>
</div>

<script>
(function() {
    const baseUrl = window.location.origin;
    let chartCO2, chartTemp;
    let summaryRefreshMs = 30000, chartUpdateMs = 300000;
    let targetTemp = 19.5, tempWarningThreshold = 3, hideOutliers = true;
    let lastTempWarningNotify = 0;
    const TEMP_NOTIFY_COOLDOWN = 5 * 60 * 1000;  // 5 min between notifications

    function tempColor(temp) {
        if (temp == null) return '#94a3b8';
        const d = temp - targetTemp;
        const t1 = tempWarningThreshold / 3, t2 = tempWarningThreshold * 2 / 3, t3 = tempWarningThreshold;
        if (d >= t3) return '#dc2626';      // red
        if (d >= t2) return '#f97316';     // orange
        if (d >= t1) return '#eab308';      // yellow
        if (d >= -t1) return '#22c55e';     // green (just right)
        if (d >= -t2) return '#38bdf8';    // light blue
        if (d >= -t3) return '#1d4ed8';     // dark blue
        return '#1e293b';                   // black/dark
    }

    function showCachedBanner(cachedDate) {
        const el = document.getElementById('cachedBanner');
        if (!el) return;
        const when = cachedDate ? new Date(cachedDate).toLocaleString() : 'previously';
        el.textContent = 'Offline – showing cached data (last updated ' + when + ')';
        el.style.display = 'block';
    }
    function hideCachedBanner() {
        const el = document.getElementById('cachedBanner');
        if (el) el.style.display = 'none';
    }

    async function fetchLatest(version) {
        let url = baseUrl + '/api/latest?t=' + Date.now();
        if (version) url += '&version=' + encodeURIComponent(version);
        const r = await fetch(url);
        if (!r.ok) return;
        if (r.headers.get('X-Served-From-Cache')) {
            showCachedBanner(r.headers.get('X-Cached-Date'));
        } else {
            hideCachedBanner();
        }
        const d = await r.json();
        document.getElementById('dateTime').textContent = d.date_time ? new Date(d.date_time + ' UTC').toLocaleString() : '—';
        document.getElementById('intTemp').textContent = d.temp != null ? d.temp.toFixed(1) + ' °C' : '—';
        const intTempIcon = document.getElementById('intTempIcon');
        if (intTempIcon) intTempIcon.style.color = tempColor(d.temp);
        const tempOutOfRange = d.temp != null && (d.temp < targetTemp - tempWarningThreshold || d.temp > targetTemp + tempWarningThreshold);
        if (tempOutOfRange && 'Notification' in window && Notification.permission === 'granted') {
            const now = Date.now();
            if (now - lastTempWarningNotify > TEMP_NOTIFY_COOLDOWN) {
                lastTempWarningNotify = now;
                new Notification('Fermmon: Temperature alert', {
                    body: 'Internal temp ' + d.temp.toFixed(1) + ' °C is outside target ' + targetTemp + ' ±' + tempWarningThreshold + ' °C',
                    icon: '/fermmon-32.png'
                });
            }
        }
        document.getElementById('heatBelt').textContent = d.relay ? 'On' : 'Off';
        document.getElementById('envTemp').textContent = d.rtemp != null ? d.rtemp.toFixed(1) + ' °C' : '—';
        document.getElementById('envHumi').textContent = d.rhumi != null ? d.rhumi.toFixed(1) + ' %' : '—';
        document.getElementById('co2').textContent = d.co2 != null ? Math.round(d.co2) + ' ppm' : '—';
        document.getElementById('tVOC').textContent = d.tvoc != null ? Math.round(d.tvoc) + ' ppb' : '—';
    }

    function getChartHours() {
        const sel = document.getElementById('chartRange');
        if (!sel) return null;
        if (sel.value === '24h') return 24;
        if (sel.value === '5d') return 120;
        return null;
    }

    async function fetchReadings(version, since, hours) {
        let url = baseUrl + '/api/readings?limit=0';
        if (version) url += '&version=' + encodeURIComponent(version);
        if (since) url += '&since=' + encodeURIComponent(since);
        if (hours) url += '&hours=' + hours;
        if (hideOutliers) url += '&max_co2=6000&max_tvoc=6000';
        const r = await fetch(url);
        if (!r.ok) return [];
        if (r.headers.get('X-Served-From-Cache')) {
            showCachedBanner(r.headers.get('X-Cached-Date'));
        }
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
        const hoursRange = getChartHours();

        const startLabel = readings.length ? new Date(readings[0].date_time.replace(' ', 'T') + 'Z').toLocaleDateString() : '';

        const xTick = (v) => {
            if (hoursRange === 24) return ((v - maxDay) * 24).toFixed(0) + 'h';  // -24h to 0 (last 24h)
            if (hoursRange === 120) return (v % 1 === 0 ? (v - maxDay).toString() : '');  // -5 to 0 (last 5d)
            return v % 1 === 0 ? 'Day ' + v : '';  // 0 to X (all)
        };

        let tooltipAutoCloseTimer = null;
        function hideTooltip() {
            const el = document.getElementById('chartTooltip');
            if (el) el.style.display = 'none';
            if (tooltipAutoCloseTimer) { clearTimeout(tooltipAutoCloseTimer); tooltipAutoCloseTimer = null; }
        }
        function externalTooltip(context) {
            const { chart, tooltip } = context;
            const el = document.getElementById('chartTooltip');
            if (!el) return;
            if (tooltip.opacity === 0) {
                hideTooltip();
                return;
            }
            if (tooltipAutoCloseTimer) { clearTimeout(tooltipAutoCloseTimer); tooltipAutoCloseTimer = null; }
            const title = tooltip.title?.length ? tooltip.title[0] : '';
            let bodyHtml = '';
            (tooltip.body || []).forEach((b, i) => {
                const c = tooltip.labelColors?.[i];
                const color = c?.backgroundColor || '#fff';
                (b.lines || []).forEach(line => {
                    bodyHtml += '<div style="display:flex;align-items:center;gap:6px;margin:2px 0"><span style="width:10px;height:10px;border-radius:2px;background:' + color + ';flex-shrink:0"></span>' + line + '</div>';
                });
            });
            el.innerHTML = '<div class="tt-header"><span>' + title + '</span><button type="button" class="tt-close" aria-label="Close">×</button></div><div>' + bodyHtml + '</div>';
            el.style.display = 'block';
            const rect = chart.canvas.getBoundingClientRect();
            const scaleX = rect.width / chart.width;
            const scaleY = rect.height / chart.height;
            const x = rect.left + (tooltip.caretX || tooltip.x) * scaleX;
            const y = rect.top + (tooltip.caretY || tooltip.y) * scaleY;
            el.style.left = Math.max(12, Math.min(x, window.innerWidth - 12)) + 'px';
            el.style.top = (y - 12) + 'px';
            el.style.transform = 'translate(-50%, -100%)';
            const closeBtn = el.querySelector('.tt-close');
            if (closeBtn) {
                closeBtn.onclick = (e) => { e.stopPropagation(); hideTooltip(); };
            }
            tooltipAutoCloseTimer = setTimeout(hideTooltip, 30000);
        }

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

        hideTooltip();
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
                    x: { type: 'linear', min: 0, max: Math.max(1, Math.ceil(maxDay)),
                         ticks: { stepSize: 1, callback: (v) => xTick(v) },
                         title: { display: true, text: 'From ' + startLabel } },
                    y: { type: 'linear', position: 'left', min: 0, suggestedMax: Math.max(4000, maxCo2 * 1.1), title: { display: true, text: 'CO2 (ppm)' } },
                    y1: { type: 'linear', position: 'right', min: 0, suggestedMax: Math.max(4000, maxTvoc * 1.1), grid: { drawOnChartArea: false }, title: { display: true, text: 'tVOC (ppb)' } }
                },
                plugins: { title: { display: true, text: 'CO2 and tVOC over Time' },
                    tooltip: { enabled: false, external: externalTooltip,
                        callbacks: { title: (items) => {
                            const d = items[0]?.raw?.x;
                            let label;
                            if (hoursRange === 24) label = ((d - maxDay) * 24).toFixed(0) + 'h';
                            else if (hoursRange === 120) label = (d - maxDay).toFixed(1);
                            else label = 'Day ' + d.toFixed(1);
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
                    x: { type: 'linear', min: 0, max: Math.max(1, Math.ceil(maxDay)),
                         ticks: { stepSize: 1, callback: (v) => xTick(v) },
                         title: { display: true, text: 'From ' + startLabel } },
                    y: { type: 'linear', position: 'left', min: 0, max: 50, title: { display: true, text: '°C' } },
                    y1: { type: 'linear', position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: '% Humidity' } },
                    y2: { type: 'linear', position: 'right', min: 0, max: 1, grid: { drawOnChartArea: false }, ticks: { stepSize: 1 } }
                },
                plugins: { title: { display: true, text: 'Temperature and Humidity over Time' },
                    tooltip: { enabled: false, external: externalTooltip,
                        callbacks: { title: (items) => {
                            const d = items[0]?.raw?.x;
                            let label;
                            if (hoursRange === 24) label = ((d - maxDay) * 24).toFixed(0) + 'h';
                            else if (hoursRange === 120) label = (d - maxDay).toFixed(1);
                            else label = 'Day ' + d.toFixed(1);
                            const idx = items[0]?.dataIndex;
                            const dt = readings[idx]?.date_time;
                            return dt ? label + ' (' + dt + ')' : label;
                        } } } }
            }
        });
    }

    let cachedReadings = [];
    let summaryIntervalId, chartIntervalId;

    function showChartLoading(show) {
        ['chartCO2Loading', 'chartTempLoading'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', !show);
        });
    }

    async function refreshView() {
        showChartLoading(true);
        try {
            const cfgRes = await fetch(baseUrl + '/api/config?t=' + Date.now(), { cache: 'no-store' });
            const cfg = await cfgRes.json();
            summaryRefreshMs = parseInt(cfg.summary_refresh_interval || 30, 10) * 1000;
            chartUpdateMs = parseInt(cfg.chart_update_interval || 300, 10) * 1000;
            targetTemp = parseFloat(cfg.target_temp || 19.5);
            tempWarningThreshold = parseFloat(cfg.temp_warning_threshold || 3);
            hideOutliers = (cfg.hide_outliers || '1') === '1';
            if (window.updateNavTimers) window.updateNavTimers(cfg);
            if (summaryIntervalId) clearInterval(summaryIntervalId);
            if (chartIntervalId) clearInterval(chartIntervalId);
            const sel = document.getElementById('versionFilter');
            const version = sel ? sel.value : null;
            const hours = getChartHours();
            cachedReadings = await fetchReadings(version, null, hours);
            initCharts(cachedReadings);
            await fetchLatest(version);
            showChartLoading(false);
            summaryIntervalId = setInterval(() => {
                const s = document.getElementById('versionFilter');
                if (s) fetchLatest(s.value);
            }, summaryRefreshMs);
            chartIntervalId = setInterval(incrementalChartUpdate, chartUpdateMs);
        } catch (e) {
            showChartLoading(false);
            throw e;
        }
    }

    async function incrementalChartUpdate() {
        const sel = document.getElementById('versionFilter');
        const version = sel ? sel.value : null;
        if (!cachedReadings.length || !chartCO2) return;
        const lastDt = cachedReadings[cachedReadings.length - 1].date_time;
        const hours = getChartHours();
        const newReadings = await fetchReadings(version, lastDt, hours);
        if (newReadings.length) {
            let merged = cachedReadings.concat(newReadings);
            if (hours) {
                const parseDt = (dt) => new Date(dt.replace(' ', 'T') + 'Z').getTime();
                const newest = parseDt(merged[merged.length - 1].date_time);
                const cutoff = newest - hours * 60 * 60 * 1000;
                merged = merged.filter(r => parseDt(r.date_time) >= cutoff);
            }
            cachedReadings = merged;
            initCharts(cachedReadings);
        }
    }

    function updateBrewInfo() {
        const sel = document.getElementById('versionFilter');
        const info = document.getElementById('brewInfo');
        if (!sel || !info) return;
        const opt = sel.options[sel.selectedIndex];
        const brew = opt?.dataset?.brew || '';
        const url = opt?.dataset?.url || '';
        if (!brew) {
            info.innerHTML = '—';
            return;
        }
        if (url) {
            info.innerHTML = '<a href="' + escapeHtml(url) + '" target="_blank" rel="noopener" class="brew-link">' + escapeHtml(brew) + '</a>';
        } else {
            info.textContent = brew;
        }
    }
    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    const versionFilter = document.getElementById('versionFilter');
    if (versionFilter) {
        versionFilter.addEventListener('change', () => { updateBrewInfo(); refreshView(); });
    }
    updateBrewInfo();
    const chartRange = document.getElementById('chartRange');
    if (chartRange) chartRange.addEventListener('change', refreshView);

    const notificationPrompt = document.getElementById('notificationPrompt');
    const btnEnableAlerts = document.getElementById('btnEnableAlerts');
    if (notificationPrompt && btnEnableAlerts && 'Notification' in window && Notification.permission === 'default') {
        notificationPrompt.style.display = 'block';
        btnEnableAlerts.addEventListener('click', () => {
            Notification.requestPermission().then(() => {
                notificationPrompt.style.display = Notification.permission === 'granted' ? 'none' : 'block';
            });
        });
    }

    refreshView();
})();
</script>
</body>
</html>
