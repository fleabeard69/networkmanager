<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title ?? 'Network Manager') ?> — Network Manager</title>
    <link rel="stylesheet" href="/css/app.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/app.css') ?>">
    <meta name="csrf-token" content="<?= Csrf::token() ?>">
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
                <a href="/ports/panel" class="nav-link <?= ($navActive ?? '') === 'ports' ? 'active' : '' ?>">
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
            <li>
                <a href="/backup" class="nav-link <?= ($navActive ?? '') === 'backup' ? 'active' : '' ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Backup
                </a>
            </li>
        </ul>

        <div class="sidebar-footer">
            <span class="sidebar-user"><?= h(Session::get('username', '')) ?></span>
            <form method="POST" action="/logout" class="logout-form">
                <?= Csrf::field() ?>
                <button type="submit" class="btn-logout">Logout</button>
            </form>
        </div>
    </nav>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-title"><?= h($title ?? '') ?></div>
        </header>

        <main class="main-content">
            <?php $flash = Session::getFlash('success'); if ($flash): ?>
                <div class="flash flash-success">
                    <span><?= h($flash) ?></span>
                    <button type="button" class="flash-close" aria-label="Dismiss">&times;</button>
                </div>
            <?php endif; ?>
            <?php $flash = Session::getFlash('error'); if ($flash): ?>
                <div class="flash flash-error">
                    <span><?= h($flash) ?></span>
                    <button type="button" class="flash-close" aria-label="Dismiss">&times;</button>
                </div>
            <?php endif; ?>

            <?= $content ?>
        </main>
    </div>

    <!-- Shared confirm modal (used by all delete/destructive actions) -->
    <div id="confirm-overlay" class="modal-overlay hidden" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
        <div class="modal" style="max-width:400px;">
            <div class="modal-header">
                <h2 id="confirm-title" class="panel-title">Confirm</h2>
                <button id="confirm-x" type="button" class="modal-close" aria-label="Cancel">&times;</button>
            </div>
            <div class="modal-body">
                <div id="confirm-message" style="margin:0; line-height:1.6;"></div>
            </div>
            <div class="modal-footer">
                <div class="modal-footer-right">
                    <button id="confirm-cancel" type="button" class="btn btn-secondary btn-sm">Cancel</button>
                    <button id="confirm-ok"     type="button" class="btn btn-danger btn-sm">Confirm</button>
                </div>
            </div>
        </div>
    </div>

</div>
<?php else: ?>
    <?= $content ?>
<?php endif; ?>

<script src="/js/app.js?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/js/app.js') ?>"></script>
</body>
</html>
