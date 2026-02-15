<?php $navActive = $navActive ?? 'dashboard'; ?>
<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <img src="/fermmon-logo.png" alt="" width="64" height="64" class="d-inline-block">
            Brew (Fermentor) Monitor
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link<?= $navActive === 'dashboard' ? ' active' : '' ?>" href="/">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link<?= $navActive === 'brews' ? ' active' : '' ?>" href="/brews">Brews</a></li>
                <li class="nav-item"><a class="nav-link<?= $navActive === 'log' ? ' active' : '' ?>" href="/log">Log</a></li>
                <li class="nav-item"><a class="nav-link<?= $navActive === 'control' ? ' active' : '' ?>" href="/control">Control</a></li>
            </ul>
            <span id="navRefreshTimers" class="navbar-text ms-auto small" title="Timing (recording): sample / write"></span>
        </div>
    </div>
</nav>
<script>
(function() {
    window.updateNavTimers = function(cfg) {
        var el = document.getElementById('navRefreshTimers');
        if (!el || !cfg) return;
        var s = parseInt(cfg.sample_interval || 10, 10);
        var w = parseInt(cfg.write_interval || 300, 10);
        el.textContent = '\u23F1 ' + s + 's / ' + (w >= 60 ? (w/60) + 'm' : w + 's');
        el.title = 'Timing (recording): sample / write – Sample ' + s + 's, Write ' + (w >= 60 ? (w/60) + ' min' : w + 's');
    };
})();
</script>
