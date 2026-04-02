<?php
$isEdit = $device !== null;
$title  = $isEdit ? 'Edit ' . $device['hostname'] : 'Add Device';
$action = $isEdit ? '/devices/' . $device['id'] . '/edit' : '/devices';
$old = Session::getFlashInput();
$raw = fn(string $key, mixed $default = '') =>
    $old[$key] ?? ($device !== null ? ($device[$key] ?? $default) : $default);
$val = fn(string $key, mixed $default = '') => h($raw($key, $default));

$deviceTypes = [
    'server'       => 'Server',
    'workstation'  => 'Workstation',
    'laptop'       => 'Laptop',
    'router'       => 'Router',
    'switch'       => 'Switch',
    'access-point' => 'Access Point',
    'nas'          => 'NAS',
    'iot'          => 'IoT Device',
    'printer'      => 'Printer',
    'camera'       => 'Camera',
    'phone'        => 'Phone',
    'tv'           => 'TV / Streaming',
    'game-console' => 'Game Console',
    'other'        => 'Other',
    'unknown'      => 'Unknown',
];
?>

<div class="panel panel-form">
    <div class="panel-header">
        <h2 class="panel-title"><?= h($title) ?></h2>
        <a href="/devices" class="btn btn-secondary btn-sm">Back to Devices</a>
    </div>

    <form method="post" action="<?= h($action) ?>" class="form" data-guard-unsaved>
        <?= Csrf::field() ?>

        <div class="form-row">
            <div class="field-group field-group-wide">
                <label class="field-label" for="hostname">Hostname <span class="required">*</span></label>
                <input class="field-input" type="text" id="hostname" name="hostname"
                       maxlength="128" required placeholder="e.g. homeserver01"
                       value="<?= $val('hostname') ?>">
            </div>

            <div class="field-group">
                <label class="field-label" for="device_type">Device Type</label>
                <select class="field-input" id="device_type" name="device_type">
                    <?php foreach ($deviceTypes as $v => $l): ?>
                        <option value="<?= h($v) ?>" <?= $raw('device_type', 'unknown') === $v ? 'selected' : '' ?>><?= h($l) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field-group">
            <label class="field-label" for="mac_address">MAC Address</label>
            <input class="field-input mono" type="text" id="mac_address" name="mac_address"
                   maxlength="17" placeholder="AA:BB:CC:DD:EE:FF"
                   pattern="^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$"
                   data-validate="mac"
                   value="<?= $val('mac_address') ?>">
            <p class="field-hint">Format: AA:BB:CC:DD:EE:FF</p>
        </div>

        <div class="field-group">
            <label class="field-label" for="panel_rear_rows">Rear Panel Rows</label>
            <input class="field-input" type="number" id="panel_rear_rows" name="panel_rear_rows"
                   min="0" max="10" value="<?= $val('panel_rear_rows', 0) ?>"
                   data-original="<?= h($isEdit ? (int)$device['panel_rear_rows'] : 0) ?>">
            <p class="field-hint">When above 0, a rear panel section appears on the dashboard alongside the front panel — useful for devices with ports on both sides. Leave at 0 if all ports are front-facing. Front rows are configured in the panel editor.</p>
        </div>

<?php if ($isEdit && ($rearPortCount ?? 0) > 0): ?>
        <div id="rear-port-warning" class="field-group" hidden>
            <div class="flash flash-warn">
                <?= (int)$rearPortCount ?> rear port<?= $rearPortCount === 1 ? '' : 's' ?> are currently positioned on the rear panel.
                Reducing Rear Panel Rows below their positions will make them inaccessible —
                check the box below to permanently delete them when saving.
            </div>
            <label class="checkbox-label">
                <input type="checkbox" name="delete_rear_ports" id="delete_rear_ports" value="1">
                Delete out-of-bounds rear ports when saving
            </label>
        </div>
        <script>
        (function () {
            const input   = document.getElementById('panel_rear_rows');
            const warning = document.getElementById('rear-port-warning');
            const orig    = parseInt(input.dataset.original, 10) || 0;
            function sync() {
                const cur = parseInt(input.value, 10);
                warning.hidden = !(Number.isFinite(cur) && cur < orig);
            }
            input.addEventListener('input', sync);
            sync();
        }());
        </script>
<?php endif; ?>

        <div class="field-group">
            <label class="field-label" for="notes">Notes</label>
            <textarea class="field-input" id="notes" name="notes" rows="3"
                      placeholder="Any additional notes about this device…"><?= $val('notes') ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <?= $isEdit ? 'Update Device' : 'Add Device' ?>
            </button>
            <a href="/devices" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
