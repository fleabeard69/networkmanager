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
    </div>

    <div class="panel backup-panel">
        <h2 class="backup-section-title">Restore</h2>
        <p class="backup-desc">
            Upload a previously exported backup file. This will
            <strong>permanently replace all current data</strong> — every device, port,
            and connection will be deleted and rebuilt from the backup.
        </p>
        <form method="POST" action="/backup/import" enctype="multipart/form-data"
              onsubmit="return confirm('This will erase ALL current data and replace it with the backup. Are you sure?')">
            <?= Csrf::field() ?>
            <div class="backup-upload-row">
                <input type="file" name="backup_file" id="backup_file"
                       accept=".json,application/json" required class="backup-file-input">
                <button type="submit" class="btn btn-danger">Restore from Backup</button>
            </div>
        </form>
    </div>

</div>
