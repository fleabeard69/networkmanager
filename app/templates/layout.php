<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title ?? 'Network Manager') ?> — Network Manager</title>
    <link rel="stylesheet" href="/css/app.css">
</head>
<body>

<?php if (Session::get('user_id')): ?>
<div class="app-shell">

    <nav class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-dot"></span>
            <span class="brand-name">NetManager</span>
        </div>

        <ul class="sidebar-nav">
            <li>
                <a href="/" class="nav-link <?= ($navActive ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="/ports" class="nav-link <?= ($navActive ?? '') === 'ports' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="10" rx="2"/><circle cx="7" cy="12" r="1.5" fill="currentColor"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/><circle cx="17" cy="12" r="1.5" fill="currentColor"/></svg>
                    Switch Ports
                </a>
            </li>
            <li>
                <a href="/devices" class="nav-link <?= ($navActive ?? '') === 'devices' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    Devices
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <span class="sidebar-user"><?= h(Session::get('username', '')) ?></span>
            <a href="/logout" class="btn-logout">Logout</a>
        </div>
    </nav>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-title"><?= h($title ?? '') ?></div>
        </header>

        <main class="main-content">
            <?php $flash = Session::getFlash('success'); if ($flash): ?>
                <div class="flash flash-success"><?= h($flash) ?></div>
            <?php endif; ?>
            <?php $flash = Session::getFlash('error'); if ($flash): ?>
                <div class="flash flash-error"><?= h($flash) ?></div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>

</div>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>

<script src="/js/app.js"></script>
</body>
</html>
