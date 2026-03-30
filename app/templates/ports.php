<?php $title = 'Switch Ports'; ?>

<div class="page-actions">
    <a href="/ports/new" class="btn btn-primary">+ Add Port</a>
</div>

<?php if (empty($ports)): ?>
    <div class="panel">
        <div class="empty-state">
            <p>No switch ports configured yet.</p>
            <a href="/ports/new" class="btn btn-primary">Add Your First Port</a>
        </div>
    </div>
<?php else: ?>
<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Label</th>
                <th>Type</th>
                <th>Speed</th>
                <th>PoE</th>
                <th>VLAN</th>
                <th>Status</th>
                <th>Device</th>
                <th>Notes</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ports as $p): ?>
            <tr>
                <td class="mono"><?= h($p['port_number']) ?></td>
                <td><?= h($p['label']) ?></td>
                <td><span class="badge badge-type"><?= h(strtoupper($p['port_type'])) ?></span></td>
                <td class="mono"><?= h($p['speed']) ?></td>
                <td><?= $p['poe_enabled'] ? '<span class="badge badge-success">Yes</span>' : '<span class="text-muted">—</span>' ?></td>
                <td class="mono"><?= $p['vlan_id'] ? h($p['vlan_id']) : '<span class="text-muted">—</span>' ?></td>
                <td>
                    <?php
                        $statusClass = match($p['status']) {
                            'active'   => 'badge-success',
                            'disabled' => 'badge-danger',
                            default    => 'badge-neutral',
                        };
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= h(ucfirst($p['status'])) ?></span>
                </td>
                <td>
                    <?php if ($p['device_hostname']): ?>
                        <a href="/devices/<?= h($p['device_id']) ?>" class="link"><?= h($p['device_hostname']) ?></a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="notes-cell"><?= h($p['notes']) ?></td>
                <td class="actions-cell">
                    <a href="/ports/<?= h($p['id']) ?>/edit" class="btn btn-secondary btn-xs">Edit</a>
                    <form method="post" action="/ports/<?= h($p['id']) ?>/delete" class="inline-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-danger btn-xs"
                                data-confirm="Remove port <?= h($p['port_number']) ?>? This cannot be undone.">
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
