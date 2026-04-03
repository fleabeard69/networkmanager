<!-- Port modal (shared by panel_editor and device_port_panel) -->
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
                    <label class="field-label" for="m-client-label">Connected Client</label>
                    <input id="m-client-label" type="text" class="field-input" maxlength="128" placeholder="e.g. Bob's Laptop — 192.168.0.201">
                    <p class="field-hint">Shown on hover in the dashboard.</p>
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
                <button id="modal-unassign" class="btn btn-warning btn-sm hidden">Unassign from Device</button>
                <button id="modal-clone" class="btn btn-secondary btn-sm hidden">Clone</button>
            </div>
            <div class="modal-footer-right">
                <button id="modal-cancel" class="btn btn-secondary btn-sm">Cancel</button>
                <button id="modal-save" class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>
    </div>
</div>
