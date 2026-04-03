<?php $title = 'Backup & Restore'; ?>

<div class="backup-page">

    <div class="panel backup-panel">
        <h2 class="backup-section-title">Export</h2>
        <p class="backup-desc">
            Download a full JSON snapshot of all devices, ports, connections, IP assignments,
            and services. Store this file somewhere safe — you can use it to restore everything
            if the database is ever lost.
        </p>
        <a href="/backup/export" class="btn btn-primary">Download Backup</a>
        <?php if ($lastExportedAt && ($lastExportTs = strtotime($lastExportedAt))): ?>
        <p class="backup-meta">Last export: <?= h(gmdate('Y-m-d H:i', $lastExportTs)) ?> UTC</p>
        <?php endif; ?>
    </div>

    <div class="panel backup-panel">
        <h2 class="backup-section-title">Restore</h2>
        <p class="backup-desc">
            Upload a previously exported backup file. This will
            <strong>permanently replace all current data</strong> — every device, port,
            and connection will be deleted and rebuilt from the backup.
        </p>
        <form method="POST" action="/backup/import" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <div class="backup-upload-row">
                <input type="file" name="backup_file" id="backup_file"
                       accept=".json,application/json" required class="backup-file-input">
                <button type="submit" class="btn btn-danger" disabled
                        data-confirm="This will erase ALL current data and replace it with the backup. Are you sure?"
                        data-confirm-ok="Restore">Restore from Backup</button>
            </div>
        </form>
        <?php if ($lastImportedAt && ($lastImportTs = strtotime($lastImportedAt))): ?>
        <p class="backup-meta">Last restore: <?= h(gmdate('Y-m-d H:i', $lastImportTs)) ?> UTC</p>
        <?php endif; ?>
    </div>

</div>
