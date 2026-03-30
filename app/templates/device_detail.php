<?php $title = $device['hostname']; ?>

<div class="panel">
    <div class="panel-header">
        <div class="device-title-block">
            <h2 class="panel-title"><?= h($device['hostname']) ?></h2>
            <span class="badge badge-type"><?= h(str_replace('-', ' ', ucfirst($device['device_type']))) ?></span>
        </div>
        <div class="header-actions">
            <a href="/devices/<?= h($device['id']) ?>/edit" class="btn btn-secondary btn-sm">Edit</a>
            <form method="post" action="/devices/<?= h($device['id']) ?>/delete" class="inline-form">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-danger btn-sm"
                        data-confirm="Delete <?= h($device['hostname']) ?>? All IPs and service ports will also be removed.">
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
            <span class="meta-label">Switch Port</span>
            <span class="meta-value">
                <?php if ($device['switch_port_number']): ?>
                    <a href="/ports/<?= h($device['port_id']) ?>/edit" class="link mono">
                        Port <?= h($device['switch_port_number']) ?>
                        <?= $device['switch_port_label'] ? ' — ' . h($device['switch_port_label']) : '' ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted">Not assigned to a port</span>
                <?php endif; ?>
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
            + Add IP
        </button>
    </div>

    <div id="ip-form" class="collapsible hidden">
        <form method="post" action="/devices/<?= h($device['id']) ?>/ips" class="form form-inline-section">
            <?= Csrf::field() ?>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="ip_address">IP Address <span class="required">*</span></label>
                    <input class="field-input mono" type="text" id="ip_address" name="ip_address"
                           placeholder="192.168.1.100" required>
                </div>
                <div class="field-group">
                    <label class="field-label" for="subnet">Subnet (CIDR)</label>
                    <input class="field-input mono" type="text" id="subnet" name="subnet"
                           placeholder="192.168.1.0/24">
                </div>
                <div class="field-group">
                    <label class="field-label" for="gateway">Gateway</label>
                    <input class="field-input mono" type="text" id="gateway" name="gateway"
                           placeholder="192.168.1.1">
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
                        <form method="post" action="/ips/<?= h($ip['id']) ?>/delete" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-xs"
                                    data-confirm="Remove <?= h($ip['ip_str']) ?>?">
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

<!-- ── Service Ports ───────────────────────────────────────────────────────── -->
<section class="panel" id="services">
    <div class="panel-header">
        <h3 class="panel-title">Open Service Ports</h3>
        <button class="btn btn-primary btn-sm"
                data-toggle="service-form"
                data-show-text="+ Add Service Port"
                data-hide-text="− Cancel">
            + Add Service Port
        </button>
    </div>

    <div id="service-form" class="collapsible hidden">
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
                        <form method="post" action="/services/<?= h($svc['id']) ?>/delete" class="inline-form">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-danger btn-xs"
                                    data-confirm="Remove <?= h(strtoupper($svc['protocol'])) ?>/<?= h($svc['port_number']) ?>?">
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
