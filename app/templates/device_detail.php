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
                <button type="submit" class="btn btn-danger btn-sm"
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
                <tr>
                    <td class="mono"><?= h($ip['ip_str']) ?></td>
                    <td class="mono"><?= $ip['subnet_str'] ? h($ip['subnet_str']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="mono"><?= $ip['gateway_str'] ? h($ip['gateway_str']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="mono"><?= $ip['interface'] ? h($ip['interface']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $ip['is_primary'] ? '<span class="badge badge-success">Yes</span>' : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $ip['notes'] ? h($ip['notes']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="actions-cell">
                        <form method="post" action="/devices/<?= h($device['id']) ?>/ips/<?= h($ip['id']) ?>/delete" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-xs"
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
                    <td class="notes-cell"><?= $sp['notes'] ? h($sp['notes']) : '<span class="text-muted">—</span>' ?></td>
                    <td class="actions-cell">
                        <a href="/ports/<?= h($sp['id']) ?>/edit" class="btn btn-secondary btn-xs">Edit</a>
                        <form method="post" action="/ports/<?= h($sp['id']) ?>/unassign" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-warning btn-xs"
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
                <tr>
                    <td><span class="badge badge-proto-<?= h($svc['protocol']) ?>"><?= h(strtoupper($svc['protocol'])) ?></span></td>
                    <td class="mono"><?= h($svc['port_number']) ?></td>
                    <td><?= $svc['service'] ? h($svc['service']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $svc['description'] ? h($svc['description']) : '<span class="text-muted">—</span>' ?></td>
                    <td><?= $svc['is_external'] ? '<span class="badge badge-warning">Yes</span>' : '<span class="text-muted">—</span>' ?></td>
                    <td class="actions-cell">
                        <form method="post" action="/devices/<?= h($device['id']) ?>/services/<?= h($svc['id']) ?>/delete" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-xs"
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
