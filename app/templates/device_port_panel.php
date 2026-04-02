<?php $title ??= $device['hostname'] . ' — Switch Ports'; ?>

<div class="panel-editor-header">
    <div class="panel-controls">
        <span class="panel-device-name"><?= h($device['hostname']) ?></span>
        <label class="panel-ctrl-label" for="ctrl-rows">Front Rows</label>
        <input id="ctrl-rows" type="number" class="field-input panel-ctrl-input" min="1" max="10" value="<?= (int)$device['panel_rows'] ?>">
        <label class="panel-ctrl-label" for="ctrl-rear-rows">Rear Rows</label>
        <input id="ctrl-rear-rows" type="number" class="field-input panel-ctrl-input" min="0" max="10" value="<?= (int)$device['panel_rear_rows'] ?>">
        <label class="panel-ctrl-label" for="ctrl-cols">Columns</label>
        <input id="ctrl-cols" type="number" class="field-input panel-ctrl-input" min="1" max="50" value="<?= (int)$device['panel_cols'] ?>">
        <button id="btn-apply-dims" class="btn btn-secondary btn-sm">Apply</button>
    </div>
    <div class="panel-controls-right">
        <a href="/devices/<?= h($device['id']) ?>" class="btn btn-secondary btn-sm">← Back to Device</a>
    </div>
</div>

<div class="panel">
    <div class="port-grid-wrap">
        <div id="panel-grid"
             class="port-grid"
             data-device-id="<?= h($device['id']) ?>"
             data-rear-rows="<?= (int)$device['panel_rear_rows'] ?>"></div>
    </div>
    <div class="port-legend">
        <div class="legend-item"><span class="legend-dot legend-connected"></span> Connected</div>
        <div class="legend-item"><span class="legend-dot legend-wan"></span> WAN</div>
        <div class="legend-item"><span class="legend-dot legend-mgmt"></span> MGMT</div>
        <div class="legend-item"><span class="legend-dot legend-disabled"></span> Disabled</div>
        <div class="legend-item"><span class="legend-dot legend-empty"></span> Empty</div>
    </div>
</div>

<?php require __DIR__ . '/partials/port_modal.php'; ?>
