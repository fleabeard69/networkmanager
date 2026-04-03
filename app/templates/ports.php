<?php $title = 'Switch Ports'; ?>

<div class="page-actions">
    <a href="/ports/panel" class="btn btn-secondary">Panel Editor</a>
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
<div class="table-filter-bar">
    <input type="search" id="ports-search" class="filter-input"
           placeholder="Filter by label, device, VLAN…" autocomplete="off" spellcheck="false">
    <select id="ports-status-filter" class="filter-select">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="disabled">Disabled</option>
        <option value="unknown">Unknown</option>
    </select>
    <select id="ports-type-filter" class="filter-select">
        <option value="">All Types</option>
        <option value="rj45">RJ45</option>
        <option value="sfp">SFP</option>
        <option value="sfp+">SFP+</option>
        <option value="wan">WAN</option>
        <option value="mgmt">MGMT</option>
    </select>
</div>
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
        <tbody id="ports-tbody">
            <?php foreach ($ports as $p): ?>
            <?php
                $isPoe = ($p['poe_enabled'] === true || $p['poe_enabled'] === 't' || $p['poe_enabled'] === '1');
            ?>
            <tr data-id="<?= h($p['id']) ?>"
                data-port-number="<?= h($p['port_number']) ?>"
                data-label="<?= h($p['label'] ?? '') ?>"
                data-port-type="<?= h($p['port_type']) ?>"
                data-speed="<?= h($p['speed']) ?>"
                data-status="<?= h($p['status']) ?>"
                data-poe="<?= $isPoe ? '1' : '0' ?>"
                data-vlan="<?= h($p['vlan_id'] ?? '') ?>"
                data-device-id="<?= h($p['device_id'] ?? '') ?>"
                data-device-hostname="<?= h($p['device_hostname'] ?? '') ?>"
                data-notes="<?= h($p['notes'] ?? '') ?>"
                data-client="<?= h($p['client_label'] ?? '') ?>"
                data-row="<?= h($p['port_row']) ?>"
                data-col="<?= h($p['port_col']) ?>">
                <td class="mono cell-port-number"><?= h($p['port_number']) ?></td>
                <td class="cell-label"><?= h($p['label']) ?></td>
                <td class="cell-type"><span class="badge badge-type"><?= h(strtoupper($p['port_type'])) ?></span></td>
                <td class="mono cell-speed"><?= h($p['speed']) ?></td>
                <td class="cell-poe"><?= $isPoe ? '<span class="badge badge-success">Yes</span>' : '<span class="text-muted">—</span>' ?></td>
                <td class="mono cell-vlan"><?= $p['vlan_id'] ? h($p['vlan_id']) : '<span class="text-muted">—</span>' ?></td>
                <td class="cell-status">
                    <?php
                        $statusClass = match($p['status']) {
                            'active'   => 'badge-success',
                            'disabled' => 'badge-danger',
                            default    => 'badge-neutral',
                        };
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= h(ucfirst($p['status'])) ?></span>
                </td>
                <td class="cell-device">
                    <?php if ($p['device_hostname']): ?>
                        <a href="/devices/<?= h($p['device_id']) ?>" class="link"><?= h($p['device_hostname']) ?></a>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="notes-cell"<?= $p['notes'] ? ' title="' . h($p['notes']) . '"' : '' ?>><?= h($p['notes']) ?></td>
                <td class="actions-cell">
                    <a href="/ports/<?= h($p['id']) ?>/edit" class="btn btn-secondary btn-xs" data-inline-edit>Edit</a>
                    <form method="post" action="/ports/<?= h($p['id']) ?>/delete" class="inline-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-danger btn-xs"
                                data-confirm="Remove port <?= h($p['port_number']) ?>? This cannot be undone."
                                data-confirm-ok="Delete">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="ports-no-results" class="filter-no-results hidden">
                <td colspan="10">No ports match your filter.</td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Inline port edit modal -->
<div id="ipm-overlay" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="ipm-title">
    <div class="modal">
        <div class="modal-header">
            <h2 id="ipm-title" class="panel-title">Edit Port</h2>
            <button id="ipm-close" class="modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="ipm-error" class="modal-error hidden"></div>
            <div class="form-row">
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="ipm-port-number">Port # <span class="required">*</span></label>
                    <input id="ipm-port-number" type="number" class="field-input" min="1" max="9999" placeholder="e.g. 1">
                </div>
                <div class="field-group">
                    <label class="field-label" for="ipm-label">Label / Interface</label>
                    <input id="ipm-label" type="text" class="field-input" maxlength="64" placeholder="e.g. eth0">
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="ipm-port-type">Type <span class="required">*</span></label>
                    <select id="ipm-port-type" class="field-input">
                        <option value="rj45">RJ45</option>
                        <option value="sfp">SFP</option>
                        <option value="sfp+">SFP+</option>
                        <option value="wan">WAN</option>
                        <option value="mgmt">MGMT</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="ipm-speed">Speed</label>
                    <select id="ipm-speed" class="field-input">
                        <option value="10M">10M</option>
                        <option value="100M">100M</option>
                        <option value="1G">1G</option>
                        <option value="2.5G">2.5G</option>
                        <option value="5G">5G</option>
                        <option value="10G">10G</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="ipm-status">Status <span class="required">*</span></label>
                    <select id="ipm-status" class="field-input">
                        <option value="active">Active</option>
                        <option value="disabled">Disabled</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="ipm-device">Device</label>
                    <select id="ipm-device" class="field-input">
                        <option value="">— Unassigned —</option>
                        <?php foreach ($devices as $dv): ?>
                        <option value="<?= h($dv['id']) ?>"><?= h($dv['hostname']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="ipm-vlan">VLAN ID</label>
                    <input id="ipm-vlan" type="number" class="field-input" min="1" max="4094" placeholder="1–4094">
                </div>
                <div class="field-group field-group-check">
                    <label class="checkbox-label" for="ipm-poe">
                        <input id="ipm-poe" type="checkbox"> PoE
                    </label>
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="ipm-client-label">Connected Client</label>
                    <input id="ipm-client-label" type="text" class="field-input" maxlength="128" placeholder="e.g. Bob's Laptop — 192.168.0.201">
                    <p class="field-hint">Shown on hover in the dashboard.</p>
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="ipm-notes">Notes</label>
                    <textarea id="ipm-notes" class="field-input" rows="2" placeholder="Optional notes"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div>
                <a id="ipm-full-edit" href="#" class="btn btn-secondary btn-sm">Full Edit →</a>
            </div>
            <div class="modal-footer-right">
                <button id="ipm-cancel" class="btn btn-secondary btn-sm">Cancel</button>
                <button id="ipm-save" class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>
    </div>
</div>
