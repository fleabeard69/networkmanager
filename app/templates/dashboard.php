<?php
$title = 'Dashboard';

// Group ports by device_id for per-section rendering
$portsByDevice = [];
foreach ($ports as $p) {
    $portsByDevice[(int) $p['device_id']][] = $p;
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

<?php if (empty($devices)): ?>
    <div class="panel">
        <div class="empty-state">
            <p>No devices configured yet.</p>
            <a href="/devices/new" class="btn btn-primary">Add a Device</a>
        </div>
    </div>
<?php else: ?>
    <div id="dashboard-devices">
    <?php foreach ($devices as $device): ?>
        <?php
            $deviceId    = (int) $device['id'];
            $devicePorts = $portsByDevice[$deviceId] ?? [];
            $rows        = max(1, (int) $device['panel_rows']);
            $maxCol      = 1;
            foreach ($devicePorts as $dp) {
                $maxCol = max($maxCol, (int) $dp['port_col']);
            }
            $cols = $maxCol;
        ?>
        <section class="device-panel-section" data-device-id="<?= h($deviceId) ?>">
            <div class="device-panel-section-header">
                <span class="drag-handle" title="Drag to reorder">&#8942;&#8942;</span>
                <a href="/devices/<?= h($device['id']) ?>" class="device-section-label link">
                    <?= h($device['hostname']) ?>
                </a>
                <a href="/devices/<?= h($device['id']) ?>/ports/panel" class="btn btn-secondary btn-xs">
                    Edit Ports
                </a>
            </div>

            <?php if (empty($devicePorts)): ?>
                <div class="empty-state-sm">
                    No ports configured.
                    <a href="/devices/<?= h($device['id']) ?>/ports/panel" class="link">Open panel editor</a>
                </div>
            <?php else: ?>
                <div class="port-grid-wrap">
                    <div class="port-grid"
                         data-rows="<?= $rows ?>"
                         data-cols="<?= $cols ?>">
                        <?php foreach ($devicePorts as $port): ?>
                            <?php
                                $cssClass = 'port-card';
                                if ($port['status'] === 'disabled') {
                                    $cssClass .= ' port-disabled';
                                } elseif ($port['port_type'] === 'wan') {
                                    $cssClass .= ' port-wan';
                                } elseif ($port['port_type'] === 'mgmt') {
                                    $cssClass .= ' port-mgmt';
                                } else {
                                    $cssClass .= ' port-connected';
                                }
                                $row = max(1, (int) $port['port_row']);
                                $col = max(1, (int) $port['port_col']);
                            ?>
                            <div class="<?= $cssClass ?> gr-<?= $row ?> gc-<?= $col ?>"
                                 data-href="/ports/<?= h($port['id']) ?>/edit"
                                 title="Port <?= h($port['port_number']) ?><?= $port['label'] ? ' — ' . h($port['label']) : '' ?>">
                                <div class="port-number"><?= h($port['port_number']) ?></div>
                                <div class="port-type-badge"><?= h(strtoupper($port['port_type'])) ?></div>
                                <div class="port-device">
                                    <?php if ($port['label']): ?>
                                        <?= h($port['label']) ?>
                                    <?php elseif ($port['status'] === 'disabled'): ?>
                                        <span class="port-empty-label">Disabled</span>
                                    <?php else: ?>
                                        <span class="port-empty-label">&nbsp;</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($port['vlan_id']): ?>
                                    <div class="port-vlan">VLAN <?= h($port['vlan_id']) ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    </div><!-- #dashboard-devices -->

    <div class="port-legend">
        <span class="legend-item"><span class="legend-dot legend-connected"></span> Active</span>
        <span class="legend-item"><span class="legend-dot legend-wan"></span> WAN</span>
        <span class="legend-item"><span class="legend-dot legend-mgmt"></span> Mgmt</span>
        <span class="legend-item"><span class="legend-dot legend-disabled"></span> Disabled</span>
    </div>
<?php endif; ?>
