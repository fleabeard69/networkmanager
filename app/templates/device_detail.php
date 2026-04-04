<?php $title = $device['hostname']; ?>

<div class="panel">
    <div class="panel-header">
        <div class="device-title-block">
            <h2 class="panel-title"><?= h($device['hostname']) ?></h2>
            <span class="badge badge-type"><?= device_type_icon($device['device_type']) ?><?= h(str_replace('-', ' ', ucfirst($device['device_type']))) ?></span>
        </div>
        <div class="header-actions">
            <a href="/devices/<?= h($device['id']) ?>/edit" class="btn btn-secondary btn-sm">Edit</a>
            <form method="post" action="/devices/<?= h($device['id']) ?>/delete" class="inline-form">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-danger btn-sm" disabled
                        data-confirm="Delete <?= h($device['hostname']) ?>? All IPs and service ports will also be removed."
                        data-confirm-ok="Delete">
                    Delete
                </button>
            </form>
        </div>
    </div>

    <div class="device-meta-grid">
        <div class="meta-item">
            <span class="meta-label">MAC Address</span>
            <span class="meta-value mono"><?= $device['mac_address'] ? h($device['mac_address']) : '—' ?></span>
        </div>
        <?php if ($device['primary_ip']): ?>
        <div class="meta-item">
            <span class="meta-label">Primary IP</span>
            <span class="meta-value mono"><?= h($device['primary_ip']) ?> <button class="copy-btn" data-copy="<?= h($device['primary_ip']) ?>" aria-label="Copy IP address" title="Copy to clipboard"><svg viewBox="0 0 14 14" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="4" y="1" width="8" height="9" rx="1"/><rect x="1" y="4" width="8" height="9" rx="1"/></svg></button></span>
        </div>
        <?php endif; ?>
        <div class="meta-item">
            <span class="meta-label">Switch Ports</span>
            <span class="meta-value">
                <?php $portCount = count($switchPorts); ?>
                <a href="/devices/<?= h($device['id']) ?>/ports/panel" class="link">
                    <?= $portCount ?> port<?= $portCount !== 1 ? 's' : '' ?> assigned — manage
                </a>
            </span>
        </div>
        <?php if ($device['notes']): ?>
        <div class="meta-item meta-item-full">
            <span class="meta-label">Notes</span>
            <span class="meta-value"><?= h($device['notes']) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── IP Addresses ────────────────────────────────────────────────────────── -->
<section class="panel" id="ips">
    <div class="panel-header">
        <h3 class="panel-title">IP Addresses</h3>
        <button class="btn btn-primary btn-sm"
                data-toggle="ip-form"
                data-show-text="+ Add IP"
                data-hide-text="− Cancel">
            <?= empty($ips) ? '− Cancel' : '+ Add IP' ?>
        </button>
    </div>

    <div id="ip-form" class="collapsible<?= empty($ips) ? '' : ' hidden' ?>">
        <form method="post" action="/devices/<?= h($device['id']) ?>/ips" class="form form-inline-section">
            <?= Csrf::field() ?>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="ip_address">IP Address <span class="required">*</span></label>
                    <input class="field-input mono" type="text" id="ip_address" name="ip_address"
                           placeholder="192.168.1.100" required data-validate="ip">
                </div>
                <div class="field-group">
                    <label class="field-label" for="subnet">Subnet Mask</label>
                    <input class="field-input mono" type="text" id="subnet" name="subnet"
                           placeholder="255.255.255.0" data-validate="subnet-mask">
                </div>
                <div class="field-group">
                    <label class="field-label" for="gateway">Gateway</label>
                    <input class="field-input mono" type="text" id="gateway" name="gateway"
                           placeholder="192.168.1.1" data-validate="ip">
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="interface">Interface</label>
                    <input class="field-input mono" type="text" id="interface" name="interface"
                           maxlength="32" placeholder="eth0">
                </div>
                <div class="field-group">
                    <label class="field-label" for="ip_notes">Notes</label>
                    <input class="field-input" type="text" id="ip_notes" name="notes" maxlength="200">
                </div>
                <div class="field-group field-group-check">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_primary" value="1">
                        <span>Primary IP</span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-sm">Add IP Address</button>
            </div>
        </form>
    </div>

    <?php if (empty($ips)): ?>
        <div class="empty-state-sm">No IP addresses recorded.</div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Subnet</th>
                    <th>Gateway</th>
                    <th>Interface</th>
                    <th>Primary</th>
                    <th>Notes</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ips as $ip): ?>
                <?php $isPrimary = $ip['is_primary'] && $ip['is_primary'] !== 'f'; ?>
                <tr<?= $isPrimary ? ' class="ip-primary"' : '' ?>>
                    <td class="mono cell-ip"><?= h($ip['ip_str']) ?> <button class="copy-btn" data-copy="<?= h($ip['ip_str']) ?>" aria-label="Copy IP address" title="Copy to clipboard"><svg viewBox="0 0 14 14" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="4" y="1" width="8" height="9" rx="1"/><rect x="1" y="4" width="8" height="9" rx="1"/></svg></button></td>
                    <td class="mono cell-subnet"><?= $ip['subnet_str'] ? h($ip['subnet_str']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="mono cell-gateway"><?= $ip['gateway_str'] ? h($ip['gateway_str']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="mono cell-iface"><?= $ip['interface'] ? h($ip['interface']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $isPrimary ? '<span class="badge badge-success">Yes</span>' : '<span class="text-muted">—</span>' ?></td>
                    <td class="cell-notes"><?= $ip['notes'] ? h($ip['notes']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="actions-cell">
                        <button class="btn btn-secondary btn-xs" data-ip-edit
                                data-id="<?= h($ip['id']) ?>"
                                data-device-id="<?= h($device['id']) ?>"
                                data-ip="<?= h($ip['ip_str']) ?>"
                                data-subnet="<?= h($ip['subnet_str'] ?? '') ?>"
                                data-gateway="<?= h($ip['gateway_str'] ?? '') ?>"
                                data-interface="<?= h($ip['interface'] ?? '') ?>"
                                data-notes="<?= h($ip['notes'] ?? '') ?>"
                                data-is-primary="<?= $isPrimary ? '1' : '0' ?>">
                            Edit
                        </button>
                        <?php if (!$isPrimary): ?>
                        <button class="btn btn-secondary btn-xs" data-set-primary
                                data-id="<?= h($ip['id']) ?>"
                                data-device-id="<?= h($device['id']) ?>"
                                data-ip="<?= h($ip['ip_str']) ?>">
                            Set Primary
                        </button>
                        <?php endif; ?>
                        <form method="post" action="/devices/<?= h($device['id']) ?>/ips/<?= h($ip['id']) ?>/delete" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-xs" disabled
                                    data-confirm="Remove <?= h($ip['ip_str']) ?>?"
                                    data-confirm-ok="Remove">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- ── Switch Ports ────────────────────────────────────────────────────────── -->
<section class="panel" id="switch-ports">
    <div class="panel-header">
        <h3 class="panel-title">Switch Ports</h3>
        <div class="header-actions">
            <?php if (!empty($unassignedPorts)): ?>
            <button class="btn btn-secondary btn-sm"
                    data-toggle="assign-port-form"
                    data-show-text="+ Assign Existing"
                    data-hide-text="− Cancel">
                + Assign Existing
            </button>
            <?php endif; ?>
            <a href="/devices/<?= h($device['id']) ?>/ports/panel" class="btn btn-primary btn-sm">
                Open Panel Editor
            </a>
        </div>
    </div>

    <?php if (!empty($unassignedPorts)): ?>
    <div id="assign-port-form" class="collapsible hidden">
        <form method="post" action="/devices/<?= h($device['id']) ?>/ports/assign" class="form form-inline-section">
            <?= Csrf::field() ?>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="port_id">Unassigned Port</label>
                    <select class="field-input" id="port_id" name="port_id" required>
                        <option value="">— Select a port —</option>
                        <?php foreach ($unassignedPorts as $up): ?>
                            <option value="<?= h($up['id']) ?>">
                                Port <?= h($up['port_number']) ?>
                                <?= $up['label'] ? ' — ' . h($up['label']) : '' ?>
                                (<?= h(strtoupper($up['port_type'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-sm">Assign to Device</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php if (empty($switchPorts)): ?>
        <div class="empty-state-sm">
            No switch ports assigned.
            <a href="/devices/<?= h($device['id']) ?>/ports/panel" class="link">Open the panel editor</a>
            to add and arrange ports.
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Port #</th>
                    <th>Label</th>
                    <th>Type</th>
                    <th>Speed</th>
                    <th>Status</th>
                    <th>VLAN</th>
                    <th>PoE</th>
                    <th>Notes</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($switchPorts as $sp): ?>
                <tr>
                    <td class="mono"><?= h($sp['port_number']) ?></td>
                    <td><?= $sp['label'] ? h($sp['label']) : '<span class="text-muted">—</span>' ?></td>
                    <td><span class="badge badge-type"><?= h(strtoupper($sp['port_type'])) ?></span></td>
                    <td class="mono"><?= h($sp['speed']) ?></td>
                    <td>
                        <span class="badge badge-status-<?= h($sp['status']) ?>">
                            <?= h(ucfirst($sp['status'])) ?>
                        </span>
                    </td>
                    <td class="mono"><?= $sp['vlan_id'] ? h($sp['vlan_id']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $sp['poe_enabled'] && $sp['poe_enabled'] !== 'f' ? '<span class="badge badge-success">Yes</span>' : '<span class="text-muted">—</span>' ?></td>
                    <td class="notes-cell"<?= $sp['notes'] ? ' title="' . h($sp['notes']) . '"' : '' ?>><?= $sp['notes'] ? h($sp['notes']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="actions-cell">
                        <a href="/ports/<?= h($sp['id']) ?>/edit" class="btn btn-secondary btn-xs">Edit</a>
                        <form method="post" action="/ports/<?= h($sp['id']) ?>/unassign" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-warning btn-xs" disabled
                                    data-confirm="Unassign Port <?= h($sp['port_number']) ?> from this device?"
                                    data-confirm-ok="Unassign">
                                Unassign
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- ── Service Ports ───────────────────────────────────────────────────────── -->
<section class="panel" id="services">
    <div class="panel-header">
        <h3 class="panel-title">Open Service Ports</h3>
        <button class="btn btn-primary btn-sm"
                data-toggle="service-form"
                data-show-text="+ Add Service Port"
                data-hide-text="− Cancel">
            <?= empty($services) ? '− Cancel' : '+ Add Service Port' ?>
        </button>
    </div>

    <div id="service-form" class="collapsible<?= empty($services) ? '' : ' hidden' ?>">
        <form method="post" action="/devices/<?= h($device['id']) ?>/services" class="form form-inline-section">
            <?= Csrf::field() ?>
            <div class="form-row">
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="protocol">Protocol</label>
                    <select class="field-input" id="protocol" name="protocol">
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="port_number">Port # <span class="required">*</span></label>
                    <input class="field-input mono" type="number" id="port_number" name="port_number"
                           min="1" max="65535" required placeholder="e.g. 443">
                </div>
                <div class="field-group">
                    <label class="field-label" for="service">Service Name</label>
                    <input class="field-input" type="text" id="service" name="service"
                           maxlength="64" placeholder="e.g. HTTPS">
                </div>
                <div class="field-group field-group-wide">
                    <label class="field-label" for="description">Description</label>
                    <input class="field-input" type="text" id="description" name="description"
                           placeholder="e.g. Reverse proxy web traffic">
                </div>
                <div class="field-group field-group-check">
                    <label class="checkbox-label">
                        <input type="checkbox" name="is_external" value="1">
                        <span>Externally accessible</span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-sm">Save Service Port</button>
            </div>
        </form>
    </div>

    <?php if (empty($services)): ?>
        <div class="empty-state-sm">No service ports recorded.</div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Protocol</th>
                    <th>Port</th>
                    <th>Service</th>
                    <th>Description</th>
                    <th>External</th>
                    <th class="col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $svc): ?>
                <?php $isExternal = $svc['is_external'] && $svc['is_external'] !== 'f'; ?>
                <tr>
                    <td class="cell-svc-proto"><span class="badge badge-proto-<?= h($svc['protocol']) ?>"><?= h(strtoupper($svc['protocol'])) ?></span></td>
                    <td class="mono cell-svc-port"><?= h($svc['port_number']) ?></td>
                    <td class="cell-svc-name"><?= $svc['service'] ? h($svc['service']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="cell-svc-desc"><?= $svc['description'] ? h($svc['description']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="cell-svc-external"><?= $isExternal ? '<span class="badge badge-warning">Yes</span>' : '<span class="text-muted">—</span>' ?></td>
                    <td class="actions-cell">
                        <button class="btn btn-secondary btn-xs" data-svc-edit
                                data-id="<?= h($svc['id']) ?>"
                                data-device-id="<?= h($device['id']) ?>"
                                data-protocol="<?= h($svc['protocol']) ?>"
                                data-port="<?= h($svc['port_number']) ?>"
                                data-service="<?= h($svc['service'] ?? '') ?>"
                                data-description="<?= h($svc['description'] ?? '') ?>"
                                data-external="<?= $isExternal ? '1' : '0' ?>">
                            Edit
                        </button>
                        <form method="post" action="/devices/<?= h($device['id']) ?>/services/<?= h($svc['id']) ?>/delete" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-xs" disabled
                                    data-confirm="Remove <?= h(strtoupper($svc['protocol'])) ?>/<?= h($svc['port_number']) ?>?"
                                    data-confirm-ok="Remove">
                                Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<!-- IP address inline edit modal -->
<div id="ip-edit-overlay" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="ip-edit-title">
    <div class="modal">
        <div class="modal-header">
            <h2 id="ip-edit-title" class="panel-title">Edit IP Address</h2>
            <button id="ip-edit-close" class="modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="ip-edit-error" class="modal-error hidden"></div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="ip-edit-ip">IP Address <span class="required">*</span></label>
                    <input id="ip-edit-ip" type="text" class="field-input mono"
                           placeholder="192.168.1.100" required data-validate="ip">
                </div>
                <div class="field-group">
                    <label class="field-label" for="ip-edit-subnet">Subnet Mask</label>
                    <input id="ip-edit-subnet" type="text" class="field-input mono"
                           placeholder="255.255.255.0" data-validate="subnet-mask">
                </div>
                <div class="field-group">
                    <label class="field-label" for="ip-edit-gateway">Gateway</label>
                    <input id="ip-edit-gateway" type="text" class="field-input mono"
                           placeholder="192.168.1.1" data-validate="ip">
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="ip-edit-interface">Interface</label>
                    <input id="ip-edit-interface" type="text" class="field-input mono"
                           maxlength="32" placeholder="eth0">
                </div>
                <div class="field-group field-group-wide">
                    <label class="field-label" for="ip-edit-notes">Notes</label>
                    <input id="ip-edit-notes" type="text" class="field-input" maxlength="200">
                </div>
                <div class="field-group field-group-check">
                    <label class="checkbox-label" for="ip-edit-primary">
                        <input type="checkbox" id="ip-edit-primary">
                        <span>Primary IP</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="modal-footer-right">
                <button id="ip-edit-cancel" class="btn btn-secondary btn-sm">Cancel</button>
                <button id="ip-edit-save"   class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Service port inline edit modal -->
<div id="svc-edit-overlay" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="svc-edit-title">
    <div class="modal">
        <div class="modal-header">
            <h2 id="svc-edit-title" class="panel-title">Edit Service Port</h2>
            <button id="svc-edit-close" class="modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="svc-edit-error" class="modal-error hidden"></div>
            <div class="form-row">
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="svc-edit-protocol">Protocol</label>
                    <select id="svc-edit-protocol" class="field-input">
                        <option value="tcp">TCP</option>
                        <option value="udp">UDP</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                <div class="field-group field-group-narrow">
                    <label class="field-label" for="svc-edit-port">Port # <span class="required">*</span></label>
                    <input id="svc-edit-port" type="number" class="field-input mono" min="1" max="65535" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="svc-edit-service">Service Name</label>
                    <input id="svc-edit-service" type="text" class="field-input" maxlength="64">
                </div>
            </div>
            <div class="form-row">
                <div class="field-group field-group-wide">
                    <label class="field-label" for="svc-edit-description">Description</label>
                    <input id="svc-edit-description" type="text" class="field-input">
                </div>
                <div class="field-group field-group-check">
                    <label class="checkbox-label">
                        <input type="checkbox" id="svc-edit-external">
                        <span>Externally accessible</span>
                    </label>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div class="modal-footer-right">
                <button id="svc-edit-cancel" class="btn btn-secondary btn-sm">Cancel</button>
                <button id="svc-edit-save"   class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>
    </div>
</div>
