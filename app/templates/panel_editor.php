<?php $title = 'Panel Editor'; ?>

<div class="panel-editor-header">
    <div class="panel-controls">
        <span class="panel-ctrl-label">Apply to all devices:</span>
        <label class="panel-ctrl-label" for="ctrl-rows">Rows</label>
        <input id="ctrl-rows" type="number" class="field-input panel-ctrl-input" min="1" max="10" value="2">
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

<!-- Port modal -->
<div id="port-modal-overlay" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal">
        <div class="modal-header">
            <h2 id="modal-title" class="panel-title">Port</h2>
            <button id="modal-close" class="modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modal-error" class="modal-error hidden"></div>
            <div class="form-row">
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="m-port-number">Port # <span class="required">*</span></label>
                    <input id="m-port-number" type="number" class="field-input" min="1" placeholder="e.g. 1">
                </div>
                <div class="field-group">
                    <label class="field-label" for="m-label">Label / Interface</label>
                    <input id="m-label" type="text" class="field-input" maxlength="64" placeholder="e.g. eth0">
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="m-port-type">Type <span class="required">*</span></label>
                    <select id="m-port-type" class="field-input">
                        <option value="rj45">RJ45</option>
                        <option value="sfp">SFP</option>
                        <option value="sfp+">SFP+</option>
                        <option value="wan">WAN</option>
                        <option value="mgmt">MGMT</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="m-speed">Speed</label>
                    <select id="m-speed" class="field-input">
                        <option value="10M">10M</option>
                        <option value="100M">100M</option>
                        <option value="1G" selected>1G</option>
                        <option value="2.5G">2.5G</option>
                        <option value="5G">5G</option>
                        <option value="10G">10G</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="m-status">Status</label>
                    <select id="m-status" class="field-input">
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="m-vlan">VLAN ID</label>
                    <input id="m-vlan" type="number" class="field-input" min="1" max="4094" placeholder="1–4094">
                </div>
                <div class="field-group field-group-check">
                    <label class="checkbox-label" for="m-poe">
                        <input id="m-poe" type="checkbox"> PoE
                    </label>
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="m-notes">Notes</label>
                    <textarea id="m-notes" class="field-input" rows="2" placeholder="Optional notes"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div>
                <button id="modal-delete" class="btn btn-danger btn-sm hidden">Delete Port</button>
            </div>
            <div class="modal-footer-right">
                <button id="modal-cancel" class="btn btn-secondary btn-sm">Cancel</button>
                <button id="modal-save" class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>
    </div>
</div>
