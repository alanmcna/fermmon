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
                <li class="nav-item"><a class="nav-link<?= $navActive === 'control' ? ' active' : '' ?>" href="/control">Control</a></li>
            </ul>
        </div>
    </div>
</nav>
