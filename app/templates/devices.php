<?php $title = 'Devices'; ?>

<div class="page-actions">
    <a href="/devices/new" class="btn btn-primary">+ Add Device</a>
</div>

<?php if (empty($devices)): ?>
    <div class="panel">
        <div class="empty-state">
            <p>No devices tracked yet.</p>
            <a href="/devices/new" class="btn btn-primary">Add Your First Device</a>
        </div>
    </div>
<?php else: ?>
<div class="table-filter-bar">
    <input type="search" id="device-filter" class="filter-input"
           placeholder="Filter by hostname, type, MAC…" autocomplete="off" spellcheck="false">
</div>
<div class="panel">
    <table class="data-table">
        <thead>
            <tr>
                <th>Hostname</th>
                <th>Type</th>
                <th>MAC Address</th>
                <th>Primary IP</th>
                <th>Ports</th>
                <th class="col-actions">Actions</th>
            </tr>
        </thead>
        <tbody id="devices-tbody">
            <?php foreach ($devices as $d): ?>
            <tr data-id="<?= h($d['id']) ?>"
                data-hostname="<?= h($d['hostname']) ?>"
                data-type="<?= h($d['device_type']) ?>"
                data-mac="<?= h($d['mac_address'] ?? '') ?>"
                data-notes="<?= h($d['notes'] ?? '') ?>"
                data-rear-rows="<?= h($d['panel_rear_rows'] ?? 0) ?>">
                <td class="cell-hostname">
                    <a href="/devices/<?= h($d['id']) ?>" class="link link-strong"><?= h($d['hostname']) ?></a>
                </td>
                <td class="cell-type">
                    <span class="badge badge-type"><?= h(str_replace('-', ' ', ucfirst($d['device_type']))) ?></span>
                </td>
                <td class="mono cell-mac"><?= $d['mac_address'] ? h($d['mac_address']) : '<span class="text-muted">—</span>' ?></td>
                <td class="mono">
                    <?= $d['primary_ip'] ? h($d['primary_ip']) : '<span class="text-muted">—</span>' ?>
                </td>
                <td>
                    <?php $pc = (int)($d['port_count'] ?? 0); ?>
                    <?php if ($pc > 0): ?>
                        <a href="/devices/<?= h($d['id']) ?>/ports/panel" class="link mono">
                            <?= $pc ?> port<?= $pc !== 1 ? 's' : '' ?>
                        </a>
                    <?php else: ?>
                        <a href="/devices/<?= h($d['id']) ?>/ports/panel" class="text-muted link">None — manage</a>
                    <?php endif; ?>
                </td>
                <td class="actions-cell">
                    <a href="/devices/<?= h($d['id']) ?>" class="btn btn-secondary btn-xs">View</a>
                    <a href="/devices/<?= h($d['id']) ?>/edit" class="btn btn-secondary btn-xs" data-inline-edit>Edit</a>
                    <form method="post" action="/devices/<?= h($d['id']) ?>/delete" class="inline-form">
                        <?= Csrf::field() ?>
                        <button type="submit" class="btn btn-danger btn-xs"
                                data-confirm="Delete <?= h($d['hostname']) ?>? All IPs and service ports will also be removed."
                                        data-confirm-ok="Delete">
                            Delete
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr id="devices-no-results" class="filter-no-results hidden">
                <td colspan="6">No devices match your filter.</td>
            </tr>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Inline device edit modal -->
<div id="idm-overlay" class="modal-overlay hidden" role="dialog" aria-modal="true" aria-labelledby="idm-title">
    <div class="modal">
        <div class="modal-header">
            <h2 id="idm-title" class="panel-title">Edit Device</h2>
            <button id="idm-close" class="modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="idm-error" class="modal-error hidden"></div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="idm-hostname">Hostname <span class="required">*</span></label>
                    <input id="idm-hostname" type="text" class="field-input" maxlength="128" placeholder="e.g. switch-core">
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="idm-type">Device Type</label>
                    <select id="idm-type" class="field-input">
                        <option value="server">Server</option>
                        <option value="workstation">Workstation</option>
                        <option value="laptop">Laptop</option>
                        <option value="router">Router</option>
                        <option value="switch">Switch</option>
                        <option value="access-point">Access Point</option>
                        <option value="nas">NAS</option>
                        <option value="iot">IoT</option>
                        <option value="printer">Printer</option>
                        <option value="camera">Camera</option>
                        <option value="phone">Phone</option>
                        <option value="tv">TV</option>
                        <option value="game-console">Game Console</option>
                        <option value="other">Other</option>
                        <option value="unknown">Unknown</option>
                    </select>
                </div>
                <div class="field-group">
                    <label class="field-label" for="idm-mac">MAC Address</label>
                    <input id="idm-mac" type="text" class="field-input" maxlength="17" placeholder="AA:BB:CC:DD:EE:FF">
                </div>
            </div>
            <div class="form-row">
                <div class="field-group">
                    <label class="field-label" for="idm-notes">Notes</label>
                    <textarea id="idm-notes" class="field-input" rows="2" placeholder="Optional notes"></textarea>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <div>
                <a id="idm-full-edit" href="#" class="btn btn-secondary btn-sm">Full Edit →</a>
            </div>
            <div class="modal-footer-right">
                <button id="idm-cancel" class="btn btn-secondary btn-sm">Cancel</button>
                <button id="idm-save" class="btn btn-primary btn-sm">Save</button>
            </div>
        </div>
    </div>
</div>
