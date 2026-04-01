<?php
$title = 'Dashboard';

// Group ports by device_id for per-section rendering
$portsByDevice = [];
foreach ($ports as $p) {
    $portsByDevice[(int) $p['device_id']][] = $p;
}
?>

<div class="stats-grid">
    <a href="/ports" class="stat-card" title="View all ports">
        <div class="stat-value"><?= h($portStats['total']) ?></div>
        <div class="stat-label">Switch Ports</div>
    </a>
    <a href="/ports?status=active" class="stat-card stat-card-green" title="View active ports">
        <div class="stat-value"><?= h($portStats['in_use']) ?></div>
        <div class="stat-label">In Use</div>
    </a>
    <a href="/devices" class="stat-card" title="View all devices">
        <div class="stat-value"><?= h($deviceCount) ?></div>
        <div class="stat-label">Devices</div>
    </a>
    <a href="/devices" class="stat-card" title="Manage IP addresses on each device page">
        <div class="stat-value"><?= h($ipCount) ?></div>
        <div class="stat-label">IP Addresses</div>
    </a>
</div>

<?php if (empty($devices)): ?>
    <div class="panel">
        <div class="empty-state">
            <p>No devices configured yet.</p>
            <a href="/devices/new" class="btn btn-primary">Add a Device</a>
        </div>
    </div>
<?php else: ?>
    <div class="dashboard-toolbar">
        <button id="btn-connect-ports" class="btn btn-secondary btn-sm">Connect Ports</button>
        <div id="connect-color-picker" class="connect-color-picker hidden">
            <?php
            $connColors = [
                '#388bfd' => 'Blue',    '#2ea043' => 'Green',    '#d29922' => 'Amber',
                '#da3633' => 'Red',     '#bc8cff' => 'Purple',   '#ff7b72' => 'Coral',
                '#ffa657' => 'Orange',  '#39d353' => 'Lime',     '#79c0ff' => 'Sky',
                '#d2a8ff' => 'Lavender','#e3b341' => 'Gold',     '#f08bb4' => 'Pink',
                '#58a6ff' => 'Lt Blue', '#7ee787' => 'Mint',     '#c9d1d9' => 'Silver',
                '#8b949e' => 'Gray',
            ];
            $first = true;
            foreach ($connColors as $hex => $name): ?>
                <span class="color-swatch<?= $first ? ' color-swatch-active' : '' ?>"
                      data-color="<?= h($hex) ?>"
                      title="<?= h($name) ?>"></span>
            <?php $first = false; endforeach; ?>
            <span class="connect-color-hint">Click a port, then another to connect. Esc to cancel.</span>
        </div>
    </div>

    <div id="dashboard-devices">
    <svg id="connections-svg" aria-hidden="true"></svg>
    <?php foreach ($devices as $device): ?>
        <?php
            $deviceId    = (int) $device['id'];
            $devicePorts = $portsByDevice[$deviceId] ?? [];
            $rows        = max(1, (int) $device['panel_rows']);
            $rearRows    = max(0, (int) $device['panel_rear_rows']);
            $maxCol      = 1;
            foreach ($devicePorts as $dp) {
                $maxCol = max($maxCol, (int) $dp['port_col']);
            }
            $cols      = $maxCol;
            $frontPorts = array_values(array_filter($devicePorts, fn($p) => (int)$p['port_row'] <= $rows));
            $rearPorts  = array_values(array_filter($devicePorts, fn($p) => (int)$p['port_row'] > $rows));
        ?>
        <section class="device-panel-section" data-device-id="<?= h($deviceId) ?>">
            <div class="device-panel-section-header">
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
                <?php
                // Reusable helper to render a grid of ports
                $renderPortGrid = function(array $gridPorts, int $gridRows, int $gridCols, int $rowOffset): void {
                    ?>
                    <div class="port-grid" data-rows="<?= $gridRows ?>" data-cols="<?= $gridCols ?>">
                        <?php foreach ($gridPorts as $port): ?>
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
                                $row = max(1, (int)$port['port_row'] - $rowOffset);
                                $col = max(1, (int)$port['port_col']);
                            ?>
                            <?php
                                $isPoe = ($port['poe_enabled'] === true || $port['poe_enabled'] === 't' || $port['poe_enabled'] === '1');
                            ?>
                            <div class="<?= $cssClass ?> gr-<?= $row ?> gc-<?= $col ?>"
                                 data-port-id="<?= h($port['id']) ?>"
                                 data-port-number="<?= h($port['port_number']) ?>"
                                 data-label="<?= h($port['label'] ?? '') ?>"
                                 data-port-type="<?= h($port['port_type']) ?>"
                                 data-speed="<?= h($port['speed']) ?>"
                                 data-status="<?= h($port['status']) ?>"
                                 data-poe="<?= $isPoe ? '1' : '0' ?>"
                                 data-vlan="<?= h($port['vlan_id'] ?? '') ?>"
                                 data-device-id="<?= h($port['device_id'] ?? '') ?>"
                                 data-notes="<?= h($port['notes'] ?? '') ?>"
                                 data-row="<?= h($port['port_row']) ?>"
                                 data-col="<?= h($port['port_col']) ?>"
                                 title="Port <?= h($port['port_number']) ?><?= $port['label'] ? ' — ' . h($port['label']) : '' ?>">
                                <div class="port-number"><?= h($port['port_number']) ?></div>
                                <div class="port-type-badge"><?= h(strtoupper($port['port_type'])) ?></div>
                                <?php if ($isPoe): ?>
                                    <span class="port-poe-badge" title="PoE Enabled">⚡</span>
                                <?php endif; ?>
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
                    <?php
                };
                ?>
                <?php if ($rearRows > 0): ?>
                <div class="port-grid-wrap port-grid-wrap-dual">
                    <div class="panel-face-col">
                        <div class="panel-face-label">Front</div>
                        <?php $renderPortGrid($frontPorts, $rows, $cols, 0); ?>
                    </div>
                    <div class="panel-face-col">
                        <div class="panel-face-label panel-face-label-rear">Rear</div>
                        <?php $renderPortGrid($rearPorts, $rearRows, $cols, $rows); ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="port-grid-wrap">
                    <?php $renderPortGrid($frontPorts, $rows, $cols, 0); ?>
                </div>
                <?php endif; ?>
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

<!-- Dashboard port edit modal -->
<div id="dpm-overlay" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="dpm-title">
    <div class="modal">
        <div class="modal-header">
            <h2 id="dpm-title" class="panel-title">Edit Port</h2>
            <button id="dpm-close" class="modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="dpm-error" class="modal-error hidden"></div>
            <div class="form-row">
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="dpm-port-number">Port # <span class="required">*</span></label>
                    <input id="dpm-port-number" type="number" class="field-input" min="1" max="9999" placeholder="e.g. 1">
                </div>
                <div class="field-group">
                    <label class="field-label" for="dpm-label">Label / Interface</label>
                    <input id="dpm-label" type="text" class="field-input" maxlength="64" placeholder="e.g. eth0">
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="dpm-port-type">Type <span class="required">*</span></label>
                    <select id="dpm-port-type" class="field-input">
                        <option value="rj45">RJ45</option>
                        <option value="sfp">SFP</option>
                        <option value="sfp+">SFP+</option>
                        <option value="wan">WAN</option>
                        <option value="mgmt">MGMT</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="dpm-speed">Speed</label>
                    <select id="dpm-speed" class="field-input">
                        <option value="10M">10M</option>
                        <option value="100M">100M</option>
                        <option value="1G">1G</option>
                        <option value="2.5G">2.5G</option>
                        <option value="5G">5G</option>
                        <option value="10G">10G</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="dpm-status">Status <span class="required">*</span></label>
                    <select id="dpm-status" class="field-input">
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="dpm-device">Device</label>
                    <select id="dpm-device" class="field-input">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($devices as $dv): ?>
                        <option value="<?= h($dv['id']) ?>"><?= h($dv['hostname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="dpm-vlan">VLAN ID</label>
                    <input id="dpm-vlan" type="number" class="field-input" min="1" max="4094" placeholder="1–4094">
                </div>
                <div class="field-group field-group-check">
                    <label class="checkbox-label" for="dpm-poe">
                        <input id="dpm-poe" type="checkbox"> PoE
                    </label>
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="dpm-notes">Notes</label>
                    <textarea id="dpm-notes" class="field-input" rows="2" placeholder="Optional notes"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div>
                <a id="dpm-full-edit" href="#" class="btn btn-secondary btn-sm">Full Edit →</a>
            </div>
            <div class="modal-footer-right">
                <button id="dpm-cancel" class="btn btn-secondary btn-sm">Cancel</button>
                <button id="dpm-save" class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>
    </div>
</div>
