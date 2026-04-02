<?php $title = 'Panel Editor'; ?>

<div class="panel-editor-header">
    <div class="panel-controls">
        <span class="panel-ctrl-label">Apply to all devices:</span>
        <label class="panel-ctrl-label" for="ctrl-rows">Rows</label>
        <input id="ctrl-rows" type="number" class="field-input panel-ctrl-input" min="1" max="10" value="2">
        <label class="panel-ctrl-label" for="ctrl-rear-rows">Rear Rows</label>
        <input id="ctrl-rear-rows" type="number" class="field-input panel-ctrl-input" min="0" max="10" value="0">
        <label class="panel-ctrl-label" for="ctrl-cols">Columns</label>
        <input id="ctrl-cols" type="number" class="field-input panel-ctrl-input" min="1" max="50" value="28">
        <button id="btn-apply-all" class="btn btn-secondary btn-sm">Apply All</button>
    </div>
    <div class="panel-controls-right">
        <a href="/ports" class="btn btn-secondary btn-sm">List View</a>
    </div>
</div>

<div id="panel-editor-container"></div>

<div class="port-legend">
    <div class="legend-item"><span class="legend-dot legend-connected"></span> Connected</div>
    <div class="legend-item"><span class="legend-dot legend-wan"></span> WAN</div>
    <div class="legend-item"><span class="legend-dot legend-mgmt"></span> MGMT</div>
    <div class="legend-item"><span class="legend-dot legend-disabled"></span> Disabled</div>
    <div class="legend-item"><span class="legend-dot legend-empty"></span> Empty</div>
</div>

<?php require __DIR__ . '/partials/port_modal.php'; ?>
