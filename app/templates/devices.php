<?php $title = 'Devices'; ?>

<div class="page-actions">
    <a href="/devices/new" class="btn btn-primary">+ Add Device</a>
</div>

<?php if (empty($devices)): ?>
    <div class="panel">
        <div class="empty-state">
            <p>No devices tracked yet.</p>
            <a href="/devices/new" class="btn btn-primary">Add Your First Device</a>
        </div>
    </div>
<?php else: ?>
<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Hostname</th>
                <th>Type</th>
                <th>MAC Address</th>
                <th>Primary IP</th>
                <th>Ports</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($devices as $d): ?>
            <tr>
                <td>
                    <a href="/devices/<?= h($d['id']) ?>" class="link link-strong"><?= h($d['hostname']) ?></a>
                </td>
                <td>
                    <span class="badge badge-type"><?= h(str_replace('-', ' ', ucfirst($d['device_type']))) ?></span>
                </td>
                <td class="mono"><?= $d['mac_address'] ? h($d['mac_address']) : '<span class="text-muted">—</span>' ?></td>
                <td class="mono">
                    <?= $d['primary_ip'] ? h($d['primary_ip']) : '<span class="text-muted">—</span>' ?>
                </td>
                <td>
                    <?php $pc = (int)($d['port_count'] ?? 0); ?>
                    <?php if ($pc > 0): ?>
                        <a href="/devices/<?= h($d['id']) ?>/ports/panel" class="link mono">
                            <?= $pc ?> port<?= $pc !== 1 ? 's' : '' ?>
                        </a>
                    <?php else: ?>
                        <a href="/devices/<?= h($d['id']) ?>/ports/panel" class="text-muted link">None — manage</a>
                    <?php endif; ?>
                </td>
                <td class="actions-cell">
                    <a href="/devices/<?= h($d['id']) ?>" class="btn btn-secondary btn-xs">View</a>
                    <a href="/devices/<?= h($d['id']) ?>/edit" class="btn btn-secondary btn-xs">Edit</a>
                    <form method="post" action="/devices/<?= h($d['id']) ?>/delete" class="inline-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-danger btn-xs"
                                data-confirm="Delete <?= h($d['hostname']) ?>? All IPs and service ports will also be removed.">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
