<?php
$isEdit = $port !== null;
$title  = $isEdit ? 'Edit Port ' . $port['port_number'] : 'Add Switch Port';
$action = $isEdit ? '/ports/' . $port['id'] . '/edit' : '/ports';
$val    = fn(string $key, mixed $default = '') => h($port !== null ? ($port[$key] ?? $default) : $default);
?>

<div class="panel panel-form">
    <div class="panel-header">
        <h2 class="panel-title"><?= h($title) ?></h2>
        <a href="/ports" class="btn btn-secondary btn-sm">Back to Ports</a>
    </div>

    <form method="post" action="<?= h($action) ?>" class="form">
        <?= Csrf::field() ?>

        <div class="form-row">
            <div class="field-group">
                <label class="field-label" for="port_number">Port Number <span class="required">*</span></label>
                <input class="field-input mono" type="number" id="port_number" name="port_number"
                       min="1" max="999" required
                       value="<?= $val('port_number') ?>">
            </div>

            <div class="field-group">
                <label class="field-label" for="label">Label / Name</label>
                <input class="field-input" type="text" id="label" name="label"
                       maxlength="64" placeholder="e.g. Server rack"
                       value="<?= $val('label') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="field-group">
                <label class="field-label" for="port_type">Port Type <span class="required">*</span></label>
                <select class="field-input" id="port_type" name="port_type" required>
                    <?php foreach (['rj45' => 'RJ45', 'sfp' => 'SFP', 'sfp+' => 'SFP+', 'wan' => 'WAN', 'mgmt' => 'Management'] as $v => $l): ?>
                        <option value="<?= h($v) ?>" <?= ($port !== null ? $port['port_type'] : 'rj45') === $v ? 'selected' : '' ?>><?= h($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-group">
                <label class="field-label" for="speed">Link Speed</label>
                <select class="field-input" id="speed" name="speed">
                    <?php foreach (['10M', '100M', '1G', '2.5G', '5G', '10G'] as $s): ?>
                        <option value="<?= h($s) ?>" <?= ($port !== null ? $port['speed'] : '1G') === $s ? 'selected' : '' ?>><?= h($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field-group">
                <label class="field-label" for="status">Status <span class="required">*</span></label>
                <select class="field-input" id="status" name="status" required>
                    <?php foreach (['active' => 'Active', 'disabled' => 'Disabled', 'unknown' => 'Unknown'] as $v => $l): ?>
                        <option value="<?= h($v) ?>" <?= ($port !== null ? $port['status'] : 'active') === $v ? 'selected' : '' ?>><?= h($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="field-group">
                <label class="field-label" for="vlan_id">VLAN ID</label>
                <input class="field-input mono" type="number" id="vlan_id" name="vlan_id"
                       min="1" max="4094" placeholder="1–4094"
                       value="<?= $val('vlan_id') ?>">
            </div>

            <div class="field-group">
                <label class="field-label" for="device_id">Connected Device</label>
                <select class="field-input" id="device_id" name="device_id">
                    <option value="">— None —</option>
                    <?php foreach ($devices as $d): ?>
                        <option value="<?= h($d['id']) ?>" <?= ($port !== null ? $port['device_id'] : null) == $d['id'] ? 'selected' : '' ?>>
                            <?= h($d['hostname']) ?><?= $d['primary_ip'] ? ' (' . h($d['primary_ip']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field-group">
            <label class="checkbox-label">
                <input type="checkbox" name="poe_enabled" value="1"
                       <?= ($port !== null && !empty($port['poe_enabled'])) ? 'checked' : '' ?>>
                <span>PoE Enabled</span>
            </label>
        </div>

        <div class="field-group">
            <label class="field-label" for="notes">Notes</label>
            <textarea class="field-input" id="notes" name="notes" rows="3"
                      placeholder="Any additional notes…"><?= $val('notes') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? 'Update Port' : 'Add Port' ?>
            </button>
            <a href="/ports" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
