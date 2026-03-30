<?php
$title = 'Dashboard';

// Compute grid dimensions from port data
$maxRow = 1;
$maxCol = 1;
foreach ($ports as $p) {
    if ($p['port_row'] > $maxRow) $maxRow = (int) $p['port_row'];
    if ($p['port_col'] > $maxCol) $maxCol = (int) $p['port_col'];
}
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= h($portStats['total']) ?></div>
        <div class="stat-label">Switch Ports</div>
    </div>
    <div class="stat-card stat-card-green">
        <div class="stat-value"><?= h($portStats['in_use']) ?></div>
        <div class="stat-label">In Use</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h($deviceCount) ?></div>
        <div class="stat-label">Devices</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= h($ipCount) ?></div>
        <div class="stat-label">IP Addresses</div>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <h2 class="panel-title">Switch Panel</h2>
        <a href="/ports/new" class="btn btn-primary btn-sm">+ Add Port</a>
    </div>

    <?php if (empty($ports)): ?>
        <div class="empty-state">
            <p>No switch ports configured yet.</p>
            <a href="/ports/new" class="btn btn-primary">Add Your First Port</a>
        </div>
    <?php else: ?>
        <div class="port-grid-wrap">
            <div class="port-grid"
                 data-rows="<?= $maxRow ?>"
                 data-cols="<?= $maxCol ?>">
                <?php foreach ($ports as $port): ?>
                    <?php
                        $cssClass = 'port-card';
                        if ($port['port_type'] === 'wan') {
                            $cssClass .= ' port-wan';
                        } elseif ($port['port_type'] === 'mgmt') {
                            $cssClass .= ' port-mgmt';
                        } elseif ($port['status'] === 'disabled') {
                            $cssClass .= ' port-disabled';
                        } elseif (!empty($port['device_id'])) {
                            $cssClass .= ' port-connected';
                        } else {
                            $cssClass .= ' port-empty';
                        }

                        $row = max(1, (int) $port['port_row']);
                        $col = max(1, (int) $port['port_col']);
                    ?>
                    <div class="<?= $cssClass ?> gr-<?= $row ?> gc-<?= $col ?>"
                         data-href="/ports/<?= h($port['id']) ?>/edit"
                         title="Port <?= h($port['port_number']) ?><?= $port['label'] ? ' — ' . h($port['label']) : '' ?><?= $port['device_hostname'] ? ' · ' . h($port['device_hostname']) : '' ?>">
                        <div class="port-number"><?= h($port['port_number']) ?></div>
                        <div class="port-type-badge"><?= h(strtoupper($port['port_type'])) ?></div>
                        <div class="port-device">
                            <?php if ($port['device_hostname']): ?>
                                <?= h($port['device_hostname']) ?>
                            <?php else: ?>
                                <span class="port-empty-label"><?= $port['status'] === 'disabled' ? 'Disabled' : 'Empty' ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($port['vlan_id']): ?>
                            <div class="port-vlan">VLAN <?= h($port['vlan_id']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="port-legend">
            <span class="legend-item"><span class="legend-dot legend-connected"></span> Connected</span>
            <span class="legend-item"><span class="legend-dot legend-empty"></span> Empty</span>
            <span class="legend-item"><span class="legend-dot legend-wan"></span> WAN</span>
            <span class="legend-item"><span class="legend-dot legend-mgmt"></span> Mgmt</span>
            <span class="legend-item"><span class="legend-dot legend-disabled"></span> Disabled</span>
        </div>
    <?php endif; ?>
</section>
