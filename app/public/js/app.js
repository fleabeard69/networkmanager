'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // ── Delete / dangerous action confirmations ───────────────────────────
    // Any submit button with data-confirm will prompt before the form submits.
    document.querySelectorAll('button[data-confirm]').forEach(btn => {
        btn.addEventListener('click', e => {
            if (!window.confirm(btn.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });

    // ── Collapsible toggle sections ───────────────────────────────────────
    // Toggles visibility of an element by ID.
    // data-toggle="element-id"
    // data-show-text="+ Add ..."   (label when section is hidden)
    // data-hide-text="− Cancel"    (label when section is visible)
    document.querySelectorAll('[data-toggle]').forEach(btn => {
        const targetId = btn.dataset.toggle;
        const target   = document.getElementById(targetId);
        if (!target) return;

        const showText = btn.dataset.showText || btn.textContent;
        const hideText = btn.dataset.hideText || 'Cancel';

        btn.addEventListener('click', () => {
            const isHidden = target.classList.contains('hidden');
            target.classList.toggle('hidden', !isHidden);
            btn.textContent = isHidden ? hideText : showText;

            // If opening, focus the first input inside
            if (isHidden) {
                const first = target.querySelector('input, select, textarea');
                if (first) first.focus();
            }
        });
    });

    // ── Switch panel grid sizing ──────────────────────────────────────────
    // Reads data-rows / data-cols from each grid container and sets
    // grid-template-columns / grid-template-rows. Uses querySelectorAll
    // so every device section on the dashboard gets sized correctly.
    document.querySelectorAll('.port-grid[data-cols]').forEach(grid => {
        const cols = parseInt(grid.dataset.cols, 10) || 1;
        const rows = parseInt(grid.dataset.rows, 10) || 1;
        grid.style.gridTemplateColumns = `repeat(${cols}, 90px)`;
        grid.style.gridTemplateRows    = `repeat(${rows}, auto)`;
    });

    // ── Port card navigation ──────────────────────────────────────────────
    // Clicking a port card navigates to its edit page.
    // data-href="/ports/{id}/edit"
    document.querySelectorAll('.port-card[data-href]').forEach(card => {
        card.addEventListener('click', () => {
            window.location.href = card.dataset.href;
        });

        // Keyboard accessibility: treat Enter/Space as a click
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'button');
        card.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                window.location.href = card.dataset.href;
            }
        });
    });

    // ── Auto-dismiss flash messages ───────────────────────────────────────
    document.querySelectorAll('.flash').forEach(flash => {
        setTimeout(() => {
            flash.classList.add('flash-hiding');
            setTimeout(() => flash.remove(), 400);
        }, 5000);
    });

    // ── Panel Editor ──────────────────────────────────────────────────────
    const panelContainer = document.getElementById('panel-editor-container');
    const panelGrid      = document.getElementById('panel-grid');

    if (panelContainer) {
        initGlobalPanelEditor();          // global multi-device view
    } else if (panelGrid) {
        initPanelEditor();                // per-device scoped view
    }

    // ── Dashboard device reorder ──────────────────────────────────────────
    initDashboardReorder();

    // ── Dashboard port connection lines ───────────────────────────────────
    initDashboardConnections();

});

// ── Panel Editor Module ───────────────────────────────────────────────────
function initPanelEditor() {

    // ── Scope detection ───────────────────────────────────────────────────
    const grid           = document.getElementById('panel-grid');
    const scopedDeviceId = grid.dataset.deviceId
                           ? parseInt(grid.dataset.deviceId, 10)
                           : null;
    const isDeviceScoped = scopedDeviceId !== null;

    // ── State ─────────────────────────────────────────────────────────────
    let ports      = [];
    let devices    = [];
    let rows       = parseInt(document.getElementById('ctrl-rows').value, 10) || 2;
    let rearRows   = parseInt(document.getElementById('ctrl-rear-rows')?.value ?? '0', 10) || 0;
    let cols       = parseInt(document.getElementById('ctrl-cols').value, 10) || 28;
    let editId     = null;   // null = create mode, number = edit mode
    let createRow  = 1;
    let createCol  = 1;
    let dragPortId = null;

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── DOM refs ──────────────────────────────────────────────────────────
    const overlay       = document.getElementById('port-modal-overlay');
    const modalTitle    = document.getElementById('modal-title');
    const modalError    = document.getElementById('modal-error');
    const modalSave     = document.getElementById('modal-save');
    const modalDelete   = document.getElementById('modal-delete');
    const modalUnassign = document.getElementById('modal-unassign');   // device panel only
    const modalCancel   = document.getElementById('modal-cancel');
    const modalClose    = document.getElementById('modal-close');
    const mPortNumber   = document.getElementById('m-port-number');
    const mLabel        = document.getElementById('m-label');
    const mPortType     = document.getElementById('m-port-type');
    const mSpeed        = document.getElementById('m-speed');
    const mStatus       = document.getElementById('m-status');
    const mDevice       = document.getElementById('m-device');         // global panel only
    const mVlan         = document.getElementById('m-vlan');
    const mPoe          = document.getElementById('m-poe');
    const mNotes        = document.getElementById('m-notes');
    const ctrlRows      = document.getElementById('ctrl-rows');
    const ctrlRearRows  = document.getElementById('ctrl-rear-rows');
    const ctrlCols      = document.getElementById('ctrl-cols');
    const btnApply      = document.getElementById('btn-apply-dims');

    // ── API helpers ───────────────────────────────────────────────────────
    async function apiFetch(url, options = {}) {
        const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
        if (options.method && options.method !== 'GET') {
            headers['X-CSRF-Token'] = csrfToken();
        }
        const res  = await fetch(url, { ...options, headers });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        return data;
    }

    // ── Load data ─────────────────────────────────────────────────────────
    async function loadData() {
        const portsUrl = isDeviceScoped
            ? `/api/devices/${scopedDeviceId}/ports`
            : '/api/ports';

        if (isDeviceScoped) {
            ports = await apiFetch(portsUrl);
        } else {
            const [portsData, devicesData] = await Promise.all([
                apiFetch(portsUrl),
                apiFetch('/api/devices'),
            ]);
            ports   = portsData;
            devices = devicesData;
            if (mDevice) populateDeviceSelect();
        }
        renderGrid();
    }

    // ── Build device <select> options (global panel only) ─────────────────
    function populateDeviceSelect() {
        while (mDevice.options.length > 1) mDevice.remove(1);
        devices.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = d.hostname;
            mDevice.appendChild(opt);
        });
    }

    // ── Render grid ───────────────────────────────────────────────────────
    function renderGrid() {
        const map = {};
        ports.forEach(p => {
            if (!map[p.port_row]) map[p.port_row] = {};
            map[p.port_row][p.port_col] = p;
        });

        // Front grid
        grid.style.gridTemplateColumns = `repeat(${cols}, 90px)`;
        grid.style.gridTemplateRows    = `repeat(${rows}, auto)`;
        grid.innerHTML = '';

        for (let r = 1; r <= rows; r++) {
            for (let c = 1; c <= cols; c++) {
                const port = map[r]?.[c];
                grid.appendChild(port ? makePortCard(port) : makeEmptyCell(r, c));
            }
        }

        // Rear panel — rebuild labels and rear grid
        const gridWrap = grid.parentElement;
        gridWrap.querySelectorAll('.panel-face-label, .rear-panel-section').forEach(el => el.remove());

        if (rearRows > 0) {
            const frontLabel = document.createElement('div');
            frontLabel.className = 'panel-face-label';
            frontLabel.textContent = 'Front';
            gridWrap.insertBefore(frontLabel, grid);

            const rearSection = document.createElement('div');
            rearSection.className = 'rear-panel-section';

            const rearLabel = document.createElement('div');
            rearLabel.className = 'panel-face-label panel-face-label-rear';
            rearLabel.textContent = 'Rear';

            const rearGrid = document.createElement('div');
            rearGrid.className = 'port-grid';
            rearGrid.style.gridTemplateColumns = `repeat(${cols}, 90px)`;
            rearGrid.style.gridTemplateRows    = `repeat(${rearRows}, auto)`;

            for (let r = rows + 1; r <= rows + rearRows; r++) {
                for (let c = 1; c <= cols; c++) {
                    const port = map[r]?.[c];
                    rearGrid.appendChild(port
                        ? makePortCard(port, rows)
                        : makeEmptyCell(r, c, rows));
                }
            }

            rearSection.appendChild(rearLabel);
            rearSection.appendChild(rearGrid);
            gridWrap.appendChild(rearSection);
        }
    }

    // ── Build a port card element ─────────────────────────────────────────
    function makePortCard(port, rowOffset = 0) {
        const el = document.createElement('div');
        el.className  = 'port-card ' + portColorClass(port);
        el.style.gridRow    = port.port_row - rowOffset;
        el.style.gridColumn = port.port_col;
        el.draggable  = true;
        el.dataset.portId = port.id;

        const numEl = document.createElement('span');
        numEl.className   = 'port-number';
        numEl.textContent = port.port_number;

        const typeEl = document.createElement('span');
        typeEl.className   = 'port-type-badge';
        typeEl.textContent = port.port_type.toUpperCase();

        const deviceEl = document.createElement('span');
        deviceEl.className   = 'port-device';
        // In device scope show label/interface; globally show device name
        deviceEl.textContent = isDeviceScoped
            ? (port.label || '')
            : (port.device_hostname || '');

        el.appendChild(numEl);
        el.appendChild(typeEl);

        const isPoe = port.poe_enabled === true || port.poe_enabled === 't' || port.poe_enabled === '1';
        if (isPoe) {
            const poeEl = document.createElement('span');
            poeEl.className = 'port-poe-badge';
            poeEl.textContent = '⚡';
            poeEl.title = 'PoE Enabled';
            el.appendChild(poeEl);
        }

        if (port.port_type === 'sfp' || port.port_type === 'sfp+') {
            const sfpEl = document.createElement('span');
            sfpEl.className = 'port-sfp-badge';
            sfpEl.textContent = '◈';
            sfpEl.title = port.port_type.toUpperCase() + ' Module';
            el.appendChild(sfpEl);
        }

        el.appendChild(deviceEl);

        el.addEventListener('click', () => openEditModal(port));

        el.addEventListener('dragstart', e => {
            dragPortId = port.id;
            e.dataTransfer.effectAllowed = 'move';
            requestAnimationFrame(() => el.classList.add('dragging'));
        });
        el.addEventListener('dragend', () => {
            dragPortId = null;
            el.classList.remove('dragging');
            grid.querySelectorAll('.drag-over').forEach(x => x.classList.remove('drag-over'));
        });
        el.addEventListener('dragover', e => {
            e.preventDefault();
            if (dragPortId && dragPortId !== port.id) el.classList.add('drag-over');
        });
        el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
        el.addEventListener('drop', e => {
            e.preventDefault();
            el.classList.remove('drag-over');
            if (dragPortId && dragPortId !== port.id) {
                swapPorts(dragPortId, port.port_row, port.port_col, port.id, port.port_row, port.port_col);
            }
        });

        return el;
    }

    // ── Build an empty cell element ───────────────────────────────────────
    function makeEmptyCell(r, c, rowOffset = 0) {
        const el = document.createElement('div');
        el.className = 'port-cell-empty';
        el.style.gridRow    = r - rowOffset;
        el.style.gridColumn = c;

        const icon = document.createElement('span');
        icon.className   = 'cell-add-icon';
        icon.textContent = '+';
        el.appendChild(icon);

        el.addEventListener('click', () => openCreateModal(r, c));

        el.addEventListener('dragover',  e => { e.preventDefault(); el.classList.add('drag-over'); });
        el.addEventListener('dragleave', ()  => el.classList.remove('drag-over'));
        el.addEventListener('drop', e => {
            e.preventDefault();
            el.classList.remove('drag-over');
            if (dragPortId) movePort(dragPortId, r, c);
        });

        return el;
    }

    // ── Port color class ──────────────────────────────────────────────────
    function portColorClass(port) {
        if (port.status === 'disabled') return 'port-disabled';
        if (port.port_type === 'wan')   return 'port-wan';
        if (port.port_type === 'mgmt')  return 'port-mgmt';
        if (port.device_id)             return 'port-connected';
        return '';
    }

    // ── Modal open/close ──────────────────────────────────────────────────
    function openCreateModal(r, c) {
        editId    = null;
        createRow = r;
        createCol = c;
        modalTitle.textContent = 'Add Port';
        clearModal();
        modalDelete?.classList.add('hidden');
        modalUnassign?.classList.add('hidden');
        overlay.classList.remove('hidden');
        mPortNumber.focus();
    }

    function openEditModal(port) {
        editId = port.id;
        modalTitle.textContent = `Edit Port ${port.port_number}`;
        clearModal();
        mPortNumber.value = port.port_number;
        mLabel.value      = port.label      ?? '';
        mPortType.value   = port.port_type;
        mSpeed.value      = port.speed;
        mStatus.value     = port.status;
        if (mDevice) mDevice.value = port.device_id ?? '';
        mVlan.value  = port.vlan_id ?? '';
        mPoe.checked = port.poe_enabled === true || port.poe_enabled === 't' || port.poe_enabled === '1';
        mNotes.value = port.notes ?? '';

        // Show delete always; show unassign only in device-scoped mode
        modalDelete?.classList.remove('hidden');
        if (modalUnassign) {
            modalUnassign.classList.toggle('hidden', !isDeviceScoped);
        }

        overlay.classList.remove('hidden');
        mPortNumber.focus();
    }

    function closeModal() {
        overlay.classList.add('hidden');
        hideError();
    }

    function clearModal() {
        mPortNumber.value = '';
        mLabel.value      = '';
        mPortType.value   = 'rj45';
        mSpeed.value      = '1G';
        mStatus.value     = 'active';
        if (mDevice) mDevice.value = '';
        mVlan.value  = '';
        mPoe.checked = false;
        mNotes.value = '';
        hideError();
    }

    function showError(msg) {
        modalError.textContent = msg;
        modalError.classList.remove('hidden');
    }

    function hideError() {
        modalError.textContent = '';
        modalError.classList.add('hidden');
    }

    // ── Build payload from modal fields ───────────────────────────────────
    function buildPayload(r, c) {
        // In device-scoped mode the device is always the scoped device;
        // in global mode it comes from the device <select>.
        const deviceId = isDeviceScoped
            ? scopedDeviceId
            : (mDevice?.value ? parseInt(mDevice.value, 10) : null);

        return {
            port_number: parseInt(mPortNumber.value, 10) || null,
            label:       mLabel.value.trim(),
            port_type:   mPortType.value,
            speed:       mSpeed.value,
            status:      mStatus.value,
            device_id:   deviceId,
            vlan_id:     mVlan.value ? parseInt(mVlan.value, 10) : null,
            poe_enabled: mPoe.checked,
            notes:       mNotes.value.trim(),
            port_row:    r,
            port_col:    c,
        };
    }

    // ── Save ──────────────────────────────────────────────────────────────
    async function savePort() {
        hideError();
        modalSave.disabled = true;
        try {
            if (editId === null) {
                const created = await apiFetch('/api/ports', {
                    method: 'POST',
                    body:   JSON.stringify(buildPayload(createRow, createCol)),
                });
                ports.push(created);
            } else {
                const existing = ports.find(p => p.id === editId);
                const updated  = await apiFetch(`/api/ports/${editId}`, {
                    method: 'PATCH',
                    body:   JSON.stringify(buildPayload(existing?.port_row ?? 1, existing?.port_col ?? 1)),
                });
                const idx = ports.findIndex(p => p.id === editId);
                if (idx !== -1) ports[idx] = updated;
            }
            closeModal();
            renderGrid();
        } catch (err) {
            showError(err.message);
        } finally {
            modalSave.disabled = false;
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────
    async function deletePort() {
        if (!editId) return;
        if (!window.confirm('Delete this port? This cannot be undone.')) return;
        if (modalDelete) modalDelete.disabled = true;
        try {
            await apiFetch(`/api/ports/${editId}`, { method: 'DELETE' });
            ports = ports.filter(p => p.id !== editId);
            closeModal();
            renderGrid();
        } catch (err) {
            showError(err.message);
        } finally {
            if (modalDelete) modalDelete.disabled = false;
        }
    }

    // ── Unassign (device panel only) ──────────────────────────────────────
    async function unassignPort() {
        if (!editId) return;
        if (!window.confirm('Unassign this port from the device? The port record will be kept.')) return;
        if (modalUnassign) modalUnassign.disabled = true;
        try {
            await apiFetch(`/api/ports/${editId}/assign`, {
                method: 'PATCH',
                body:   JSON.stringify({ device_id: null }),
            });
            // Remove from device-scoped view since it's no longer assigned here
            ports = ports.filter(p => p.id !== editId);
            closeModal();
            renderGrid();
        } catch (err) {
            showError(err.message);
        } finally {
            if (modalUnassign) modalUnassign.disabled = false;
        }
    }

    // ── Move port to empty cell ───────────────────────────────────────────
    async function movePort(id, toRow, toCol) {
        try {
            const updated = await apiFetch(`/api/ports/${id}/position`, {
                method: 'PATCH',
                body:   JSON.stringify({ port_row: toRow, port_col: toCol }),
            });
            const idx = ports.findIndex(p => p.id === id);
            if (idx !== -1) ports[idx] = updated;
            renderGrid();
        } catch (err) {
            alert('Move failed: ' + err.message);
        }
    }

    // ── Swap two ports' positions ─────────────────────────────────────────
    async function swapPorts(idA, rowA, colA, idB, rowB, colB) {
        try {
            const [updatedA, updatedB] = await Promise.all([
                apiFetch(`/api/ports/${idA}/position`, {
                    method: 'PATCH',
                    body:   JSON.stringify({ port_row: rowB, port_col: colB }),
                }),
                apiFetch(`/api/ports/${idB}/position`, {
                    method: 'PATCH',
                    body:   JSON.stringify({ port_row: rowA, port_col: colA }),
                }),
            ]);
            const idxA = ports.findIndex(p => p.id === idA);
            const idxB = ports.findIndex(p => p.id === idB);
            if (idxA !== -1) ports[idxA] = updatedA;
            if (idxB !== -1) ports[idxB] = updatedB;
            renderGrid();
        } catch (err) {
            await loadData();
            alert('Swap failed: ' + err.message);
        }
    }

    // ── Grid dimension controls ───────────────────────────────────────────
    btnApply.addEventListener('click', async () => {
        const r    = parseInt(ctrlRows.value, 10);
        const rear = parseInt(ctrlRearRows?.value ?? '0', 10);
        const c    = parseInt(ctrlCols.value, 10);
        if (r < 1 || r > 10 || rear < 0 || rear > 10 || c < 1 || c > 50) return;
        if (isDeviceScoped) {
            try {
                await apiFetch(`/api/devices/${scopedDeviceId}/panel`, {
                    method: 'PATCH',
                    body:   JSON.stringify({ panel_rows: r, panel_rear_rows: rear, panel_cols: c }),
                });
            } catch (err) {
                alert('Failed to save dimensions: ' + err.message);
                return;
            }
        }
        rows     = r;
        rearRows = rear;
        cols     = c;
        renderGrid();
    });

    // ── Modal button events ───────────────────────────────────────────────
    modalSave.addEventListener('click', savePort);
    modalDelete?.addEventListener('click', deletePort);
    modalUnassign?.addEventListener('click', unassignPort);
    modalCancel.addEventListener('click', closeModal);
    modalClose.addEventListener('click',  closeModal);

    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal();
    });

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) {
            closeModal();
        }
    });

    // ── Bootstrap ─────────────────────────────────────────────────────────
    loadData().catch(err => {
        const errEl = document.createElement('p');
        errEl.className = 'error-message';
        errEl.textContent = `Failed to load ports: ${err.message}`;
        grid.replaceChildren(errEl);
    });
}

// ── Global Multi-Device Panel Editor ─────────────────────────────────────────
function initGlobalPanelEditor() {

    // ── State ─────────────────────────────────────────────────────────────
    let devices        = [];
    let ports          = [];
    let editId         = null;
    let createDeviceId = null;
    let createRow      = 1;
    let createCol      = 1;
    let dragPortId     = null;
    let dragDeviceId   = null;

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    // ── DOM refs ──────────────────────────────────────────────────────────
    const container   = document.getElementById('panel-editor-container');
    const overlay     = document.getElementById('port-modal-overlay');
    const modalTitle  = document.getElementById('modal-title');
    const modalError  = document.getElementById('modal-error');
    const modalSave   = document.getElementById('modal-save');
    const modalDelete = document.getElementById('modal-delete');
    const modalCancel = document.getElementById('modal-cancel');
    const modalClose  = document.getElementById('modal-close');
    const mPortNumber = document.getElementById('m-port-number');
    const mLabel      = document.getElementById('m-label');
    const mPortType   = document.getElementById('m-port-type');
    const mSpeed      = document.getElementById('m-speed');
    const mStatus     = document.getElementById('m-status');
    const mVlan       = document.getElementById('m-vlan');
    const mPoe        = document.getElementById('m-poe');
    const mNotes      = document.getElementById('m-notes');
    const ctrlRows    = document.getElementById('ctrl-rows');
    const ctrlCols    = document.getElementById('ctrl-cols');
    const btnApplyAll = document.getElementById('btn-apply-all');

    // ── API helper ────────────────────────────────────────────────────────
    async function apiFetch(url, options = {}) {
        const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
        if (options.method && options.method !== 'GET') {
            headers['X-CSRF-Token'] = csrfToken();
        }
        const res  = await fetch(url, { ...options, headers });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
        return data;
    }

    // ── Load ──────────────────────────────────────────────────────────────
    async function loadData() {
        const [portsData, devicesData] = await Promise.all([
            apiFetch('/api/ports'),
            apiFetch('/api/devices'),
        ]);
        ports   = portsData;
        devices = devicesData;
        renderAll();
    }

    function portsForDevice(deviceId) {
        return ports.filter(p => String(p.device_id) === String(deviceId));
    }

    // ── Render all device sections ────────────────────────────────────────
    function renderAll() {
        container.innerHTML = '';
        if (devices.length === 0) {
            container.innerHTML =
                '<div class="panel"><div class="empty-state">' +
                '<p>No devices configured yet.</p>' +
                '<a href="/devices/new" class="btn btn-primary">Add a Device</a>' +
                '</div></div>';
            return;
        }
        devices.forEach(device => container.appendChild(makeDeviceSection(device)));
    }

    // ── Build one device section ──────────────────────────────────────────
    function makeDeviceSection(device) {
        const rows     = device.panel_rows      || 2;
        const rearRows = device.panel_rear_rows || 0;
        const cols     = device.panel_cols      || 28;
        const dPorts   = portsForDevice(device.id);

        const section = document.createElement('div');
        section.className    = 'device-panel-section';
        section.dataset.deviceId = device.id;

        // ── Section header ────────────────────────────────────────────────
        const header = document.createElement('div');
        header.className = 'device-panel-section-header';

        const nameEl = document.createElement('a');
        nameEl.className   = 'device-section-label link';
        nameEl.href        = `/devices/${device.id}`;
        nameEl.textContent = device.hostname;

        // Inline resize controls
        const resizeWrap = document.createElement('div');
        resizeWrap.className = 'device-panel-resize';

        const rowLabel = document.createElement('label');
        rowLabel.className   = 'panel-ctrl-label';
        rowLabel.textContent = 'Front Rows';

        const rowInput = document.createElement('input');
        rowInput.type      = 'number';
        rowInput.className = 'field-input panel-ctrl-input';
        rowInput.min       = '1';
        rowInput.max       = '10';
        rowInput.value     = rows;

        const rearRowLabel = document.createElement('label');
        rearRowLabel.className   = 'panel-ctrl-label';
        rearRowLabel.textContent = 'Rear Rows';

        const rearRowInput = document.createElement('input');
        rearRowInput.type      = 'number';
        rearRowInput.className = 'field-input panel-ctrl-input';
        rearRowInput.min       = '0';
        rearRowInput.max       = '10';
        rearRowInput.value     = rearRows;

        const colLabel = document.createElement('label');
        colLabel.className   = 'panel-ctrl-label';
        colLabel.textContent = 'Cols';

        const colInput = document.createElement('input');
        colInput.type      = 'number';
        colInput.className = 'field-input panel-ctrl-input';
        colInput.min       = '1';
        colInput.max       = '50';
        colInput.value     = cols;

        const applyBtn = document.createElement('button');
        applyBtn.className   = 'btn btn-secondary btn-xs';
        applyBtn.textContent = 'Apply';
        applyBtn.addEventListener('click', async () => {
            const r    = parseInt(rowInput.value, 10);
            const rear = parseInt(rearRowInput.value, 10);
            const c    = parseInt(colInput.value, 10);
            if (r < 1 || r > 10 || rear < 0 || rear > 10 || c < 1 || c > 50) return;
            try {
                await apiFetch(`/api/devices/${device.id}/panel`, {
                    method: 'PATCH',
                    body:   JSON.stringify({ panel_rows: r, panel_rear_rows: rear, panel_cols: c }),
                });
                const d = devices.find(x => String(x.id) === String(device.id));
                if (d) { d.panel_rows = r; d.panel_rear_rows = rear; d.panel_cols = c; }
                const newSection = makeDeviceSection({ ...device, panel_rows: r, panel_rear_rows: rear, panel_cols: c });
                section.replaceWith(newSection);
            } catch (err) {
                alert('Failed to save: ' + err.message);
            }
        });

        resizeWrap.appendChild(rowLabel);
        resizeWrap.appendChild(rowInput);
        resizeWrap.appendChild(rearRowLabel);
        resizeWrap.appendChild(rearRowInput);
        resizeWrap.appendChild(colLabel);
        resizeWrap.appendChild(colInput);
        resizeWrap.appendChild(applyBtn);

        header.appendChild(nameEl);
        header.appendChild(resizeWrap);

        // ── Port grid ─────────────────────────────────────────────────────
        const gridWrap = document.createElement('div');
        gridWrap.className = 'port-grid-wrap';

        const map = {};
        dPorts.forEach(p => {
            if (!map[p.port_row]) map[p.port_row] = {};
            map[p.port_row][p.port_col] = p;
        });

        const buildGrid = (fromRow, toRow, rowOffset) => {
            const g = document.createElement('div');
            g.className = 'port-grid';
            g.style.gridTemplateColumns = `repeat(${cols}, 90px)`;
            g.style.gridTemplateRows    = `repeat(${toRow - fromRow + 1}, auto)`;
            for (let r = fromRow; r <= toRow; r++) {
                for (let c = 1; c <= cols; c++) {
                    const port = map[r]?.[c];
                    g.appendChild(port
                        ? makePortCard(port, device.id, rowOffset)
                        : makeEmptyCell(device.id, r, c, rowOffset));
                }
            }
            return g;
        };

        if (rearRows > 0) {
            const frontLabel = document.createElement('div');
            frontLabel.className = 'panel-face-label';
            frontLabel.textContent = 'Front';
            gridWrap.appendChild(frontLabel);
        }

        gridWrap.appendChild(buildGrid(1, rows, 0));

        if (rearRows > 0) {
            const rearLabel = document.createElement('div');
            rearLabel.className = 'panel-face-label panel-face-label-rear';
            rearLabel.textContent = 'Rear';
            gridWrap.appendChild(rearLabel);
            gridWrap.appendChild(buildGrid(rows + 1, rows + rearRows, rows));
        }

        section.appendChild(header);
        section.appendChild(gridWrap);
        return section;
    }

    // ── Port card ─────────────────────────────────────────────────────────
    function makePortCard(port, deviceId, rowOffset = 0) {
        const el = document.createElement('div');
        el.className        = 'port-card ' + portColorClass(port);
        el.style.gridRow    = port.port_row - rowOffset;
        el.style.gridColumn = port.port_col;
        el.draggable        = true;
        el.dataset.portId   = port.id;
        el.dataset.deviceId = deviceId;

        const numEl = document.createElement('span');
        numEl.className   = 'port-number';
        numEl.textContent = port.port_number;

        const typeEl = document.createElement('span');
        typeEl.className   = 'port-type-badge';
        typeEl.textContent = port.port_type.toUpperCase();

        const labelEl = document.createElement('span');
        labelEl.className   = 'port-device';
        labelEl.textContent = port.label || '';

        el.appendChild(numEl);
        el.appendChild(typeEl);

        const isPoe = port.poe_enabled === true || port.poe_enabled === 't' || port.poe_enabled === '1';
        if (isPoe) {
            const poeEl = document.createElement('span');
            poeEl.className = 'port-poe-badge';
            poeEl.textContent = '⚡';
            poeEl.title = 'PoE Enabled';
            el.appendChild(poeEl);
        }

        if (port.port_type === 'sfp' || port.port_type === 'sfp+') {
            const sfpEl = document.createElement('span');
            sfpEl.className = 'port-sfp-badge';
            sfpEl.textContent = '◈';
            sfpEl.title = port.port_type.toUpperCase() + ' Module';
            el.appendChild(sfpEl);
        }

        el.appendChild(labelEl);

        el.addEventListener('click', () => openEditModal(port));

        el.addEventListener('dragstart', e => {
            dragPortId   = port.id;
            dragDeviceId = String(deviceId);
            e.dataTransfer.effectAllowed = 'move';
            requestAnimationFrame(() => el.classList.add('dragging'));
        });
        el.addEventListener('dragend', () => {
            dragPortId   = null;
            dragDeviceId = null;
            el.classList.remove('dragging');
            document.querySelectorAll('.drag-over').forEach(x => x.classList.remove('drag-over'));
        });
        el.addEventListener('dragover', e => {
            e.preventDefault();
            // Only highlight swap targets within the same device
            if (dragPortId && dragPortId !== port.id && dragDeviceId === String(deviceId)) {
                el.classList.add('drag-over');
            }
        });
        el.addEventListener('dragleave', () => el.classList.remove('drag-over'));
        el.addEventListener('drop', e => {
            e.preventDefault();
            el.classList.remove('drag-over');
            if (!dragPortId || dragPortId === port.id) return;
            if (dragDeviceId === String(deviceId)) {
                swapPorts(dragPortId, port.port_row, port.port_col, port.id, port.port_row, port.port_col);
            }
            // Cross-device drop on a card is not supported (drop on empty cell instead)
        });

        return el;
    }

    // ── Empty cell ────────────────────────────────────────────────────────
    function makeEmptyCell(deviceId, r, c, rowOffset = 0) {
        const el = document.createElement('div');
        el.className        = 'port-cell-empty';
        el.style.gridRow    = r - rowOffset;
        el.style.gridColumn = c;
        el.dataset.deviceId = deviceId;

        const icon = document.createElement('span');
        icon.className   = 'cell-add-icon';
        icon.textContent = '+';
        el.appendChild(icon);

        el.addEventListener('click', () => openCreateModal(deviceId, r, c));

        el.addEventListener('dragover',  e => { e.preventDefault(); el.classList.add('drag-over'); });
        el.addEventListener('dragleave', ()  => el.classList.remove('drag-over'));
        el.addEventListener('drop', e => {
            e.preventDefault();
            el.classList.remove('drag-over');
            if (!dragPortId) return;
            if (dragDeviceId === String(deviceId)) {
                movePort(dragPortId, r, c);
            } else {
                movePortToDevice(dragPortId, deviceId, r, c);
            }
        });

        return el;
    }

    // ── Port color class ──────────────────────────────────────────────────
    function portColorClass(port) {
        if (port.status === 'disabled') return 'port-disabled';
        if (port.port_type === 'wan')   return 'port-wan';
        if (port.port_type === 'mgmt')  return 'port-mgmt';
        if (port.device_id)             return 'port-connected';
        return '';
    }

    // ── Modal ─────────────────────────────────────────────────────────────
    function openCreateModal(deviceId, r, c) {
        editId         = null;
        createDeviceId = deviceId;
        createRow      = r;
        createCol      = c;
        const device   = devices.find(d => String(d.id) === String(deviceId));
        modalTitle.textContent = `Add Port — ${device?.hostname ?? ''}`;
        clearModal();
        modalDelete?.classList.add('hidden');
        overlay.classList.remove('hidden');
        mPortNumber.focus();
    }

    function openEditModal(port) {
        editId = port.id;
        const device = devices.find(d => String(d.id) === String(port.device_id));
        modalTitle.textContent = `Edit Port ${port.port_number} — ${device?.hostname ?? ''}`;
        clearModal();
        mPortNumber.value = port.port_number;
        mLabel.value      = port.label      ?? '';
        mPortType.value   = port.port_type;
        mSpeed.value      = port.speed;
        mStatus.value     = port.status;
        mVlan.value       = port.vlan_id    ?? '';
        mPoe.checked      = port.poe_enabled === true || port.poe_enabled === 't' || port.poe_enabled === '1';
        mNotes.value      = port.notes      ?? '';
        modalDelete?.classList.remove('hidden');
        overlay.classList.remove('hidden');
        mPortNumber.focus();
    }

    function closeModal() {
        overlay.classList.add('hidden');
        hideError();
    }

    function clearModal() {
        mPortNumber.value = '';
        mLabel.value      = '';
        mPortType.value   = 'rj45';
        mSpeed.value      = '1G';
        mStatus.value     = 'active';
        mVlan.value       = '';
        mPoe.checked      = false;
        mNotes.value      = '';
        hideError();
    }

    function showError(msg) {
        modalError.textContent = msg;
        modalError.classList.remove('hidden');
    }
    function hideError() {
        modalError.textContent = '';
        modalError.classList.add('hidden');
    }

    function buildPayload(deviceId, r, c) {
        return {
            port_number: parseInt(mPortNumber.value, 10) || null,
            label:       mLabel.value.trim(),
            port_type:   mPortType.value,
            speed:       mSpeed.value,
            status:      mStatus.value,
            device_id:   deviceId !== null ? parseInt(deviceId, 10) : null,
            vlan_id:     mVlan.value ? parseInt(mVlan.value, 10) : null,
            poe_enabled: mPoe.checked,
            notes:       mNotes.value.trim(),
            port_row:    r,
            port_col:    c,
        };
    }

    // ── Save ──────────────────────────────────────────────────────────────
    async function savePort() {
        hideError();
        modalSave.disabled = true;
        try {
            if (editId === null) {
                const created = await apiFetch('/api/ports', {
                    method: 'POST',
                    body:   JSON.stringify(buildPayload(createDeviceId, createRow, createCol)),
                });
                ports.push(created);
            } else {
                const existing = ports.find(p => p.id === editId);
                const updated  = await apiFetch(`/api/ports/${editId}`, {
                    method: 'PATCH',
                    body:   JSON.stringify(buildPayload(
                        existing?.device_id,
                        existing?.port_row ?? 1,
                        existing?.port_col ?? 1
                    )),
                });
                const idx = ports.findIndex(p => p.id === editId);
                if (idx !== -1) ports[idx] = updated;
            }
            closeModal();
            renderAll();
        } catch (err) {
            showError(err.message);
        } finally {
            modalSave.disabled = false;
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────
    async function deletePort() {
        if (!editId) return;
        if (!window.confirm('Delete this port? This cannot be undone.')) return;
        if (modalDelete) modalDelete.disabled = true;
        try {
            await apiFetch(`/api/ports/${editId}`, { method: 'DELETE' });
            ports = ports.filter(p => p.id !== editId);
            closeModal();
            renderAll();
        } catch (err) {
            showError(err.message);
        } finally {
            if (modalDelete) modalDelete.disabled = false;
        }
    }

    // ── Move within same device ───────────────────────────────────────────
    async function movePort(id, toRow, toCol) {
        try {
            const updated = await apiFetch(`/api/ports/${id}/position`, {
                method: 'PATCH',
                body:   JSON.stringify({ port_row: toRow, port_col: toCol }),
            });
            const idx = ports.findIndex(p => p.id === id);
            if (idx !== -1) ports[idx] = updated;
            renderAll();
        } catch (err) {
            alert('Move failed: ' + err.message);
        }
    }

    // ── Move to different device ──────────────────────────────────────────
    async function movePortToDevice(id, toDeviceId, toRow, toCol) {
        const port = ports.find(p => p.id === id);
        if (!port) return;
        try {
            const updated = await apiFetch(`/api/ports/${id}`, {
                method: 'PATCH',
                body:   JSON.stringify({
                    port_number: port.port_number,
                    label:       port.label       ?? '',
                    port_type:   port.port_type,
                    speed:       port.speed,
                    status:      port.status,
                    device_id:   parseInt(toDeviceId, 10),
                    vlan_id:     port.vlan_id     ?? null,
                    poe_enabled: port.poe_enabled === true || port.poe_enabled === 't',
                    notes:       port.notes       ?? '',
                    port_row:    toRow,
                    port_col:    toCol,
                }),
            });
            const idx = ports.findIndex(p => p.id === id);
            if (idx !== -1) ports[idx] = updated;
            renderAll();
        } catch (err) {
            alert('Move failed: ' + err.message);
        }
    }

    // ── Swap within same device ───────────────────────────────────────────
    async function swapPorts(idA, rowA, colA, idB, rowB, colB) {
        try {
            const [updA, updB] = await Promise.all([
                apiFetch(`/api/ports/${idA}/position`, {
                    method: 'PATCH',
                    body:   JSON.stringify({ port_row: rowB, port_col: colB }),
                }),
                apiFetch(`/api/ports/${idB}/position`, {
                    method: 'PATCH',
                    body:   JSON.stringify({ port_row: rowA, port_col: colA }),
                }),
            ]);
            [idA, idB].forEach((id, i) => {
                const upd = i === 0 ? updA : updB;
                const idx = ports.findIndex(p => p.id === id);
                if (idx !== -1) ports[idx] = upd;
            });
            renderAll();
        } catch (err) {
            await loadData();
            alert('Swap failed: ' + err.message);
        }
    }

    // ── "Apply to all devices" ────────────────────────────────────────────
    btnApplyAll?.addEventListener('click', async () => {
        const r = parseInt(ctrlRows.value, 10);
        const c = parseInt(ctrlCols.value, 10);
        if (r < 1 || r > 10 || c < 1 || c > 50) return;
        try {
            await Promise.all(devices.map(d =>
                apiFetch(`/api/devices/${d.id}/panel`, {
                    method: 'PATCH',
                    body:   JSON.stringify({ panel_rows: r, panel_cols: c }),
                })
            ));
            devices.forEach(d => { d.panel_rows = r; d.panel_cols = c; });
            renderAll();
        } catch (err) {
            alert('Failed to apply: ' + err.message);
        }
    });

    // ── Modal events ──────────────────────────────────────────────────────
    modalSave.addEventListener('click',   savePort);
    modalDelete?.addEventListener('click', deletePort);
    modalCancel.addEventListener('click', closeModal);
    modalClose.addEventListener('click',  closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeModal();
    });

    // ── Bootstrap ─────────────────────────────────────────────────────────
    loadData().catch(err => {
        const errEl = document.createElement('p');
        errEl.className = 'error-message';
        errEl.textContent = `Failed to load: ${err.message}`;
        container.replaceChildren(errEl);
    });
}

// ── Dashboard Port Connection Lines ──────────────────────────────────────────
function initDashboardConnections() {
    const container   = document.getElementById('dashboard-devices');
    const svg         = document.getElementById('connections-svg');
    const connectBtn  = document.getElementById('btn-connect-ports');
    const colorPicker = document.getElementById('connect-color-picker');
    if (!container || !svg || !connectBtn) return;

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let connections     = [];
    let occupiedPortIds = new Set();
    let connectMode     = false;
    let selectedPortId  = null;
    let selectedColor   = '#388bfd';

    // ── Color swatch setup ────────────────────────────────────────────────
    colorPicker?.querySelectorAll('.color-swatch').forEach(swatch => {
        // Apply background via JS (avoids CSP restrictions on HTML inline styles)
        swatch.style.backgroundColor = swatch.dataset.color;

        swatch.addEventListener('click', () => {
            colorPicker.querySelectorAll('.color-swatch').forEach(s =>
                s.classList.remove('color-swatch-active')
            );
            swatch.classList.add('color-swatch-active');
            selectedColor = swatch.dataset.color;
        });
    });

    // ── Port card center coords relative to #dashboard-devices ───────────
    function portAnchor(portId) {
        const el = container.querySelector(`[data-port-id="${portId}"]`);
        if (!el) return null;
        const pr = el.getBoundingClientRect();
        const cr = container.getBoundingClientRect();
        return {
            cx:  pr.left - cr.left + pr.width  / 2,
            top: pr.top  - cr.top,
            bot: pr.top  - cr.top + pr.height,
            mid: pr.top  - cr.top + pr.height  / 2,
        };
    }

    // ── Draw all connection lines ─────────────────────────────────────────
    function drawConnections() {
        svg.innerHTML = '';
        svg.setAttribute('height', container.scrollHeight);

        connections.forEach(conn => {
            const a = portAnchor(conn.port_a);
            const b = portAnchor(conn.port_b);
            if (!a || !b) return;

            const color = conn.color || '#388bfd';

            // Always draw top→bottom
            const [top, bot] = a.mid <= b.mid ? [a, b] : [b, a];
            const x1 = top.cx, y1 = top.bot;
            const x2 = bot.cx, y2 = bot.top;
            const cp = Math.max(30, Math.abs(y2 - y1) * 0.45);
            const d  = `M ${x1},${y1} C ${x1},${y1+cp} ${x2},${y2-cp} ${x2},${y2}`;

            // Invisible wide hit-test path
            const hit = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            hit.setAttribute('class', 'conn-hit');
            hit.setAttribute('d', d);
            hit.setAttribute('stroke', 'transparent');
            hit.setAttribute('stroke-width', '18');
            hit.setAttribute('fill', 'none');
            hit.title = 'Click to remove this connection';
            hit.addEventListener('click', () => removeConnection(conn));
            svg.appendChild(hit);

            // Visible dashed line
            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('class', 'conn-line');
            path.setAttribute('d', d);
            path.setAttribute('stroke', color);
            path.setAttribute('stroke-width', '3');
            path.setAttribute('stroke-dasharray', '7 4');
            path.setAttribute('fill', 'none');
            path.setAttribute('opacity', '0.85');
            svg.appendChild(path);

            // Endpoint dots
            [{ x: x1, y: y1 }, { x: x2, y: y2 }].forEach(({ x, y }) => {
                const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                dot.setAttribute('class', 'conn-dot');
                dot.setAttribute('cx', x);
                dot.setAttribute('cy', y);
                dot.setAttribute('r', '4');
                dot.setAttribute('fill', color);
                svg.appendChild(dot);
            });
        });
    }

    // ── Remove a connection ───────────────────────────────────────────────
    async function removeConnection(conn) {
        const labelA = conn.port_a_label || `Port ${conn.port_a_number}`;
        const labelB = conn.port_b_label || `Port ${conn.port_b_number}`;
        if (!window.confirm(`Remove connection between ${labelA} and ${labelB}?`)) return;
        try {
            const res = await fetch(`/api/connections/${conn.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-Token': csrfToken() },
            });
            if (res.ok) {
                connections = connections.filter(c => c.id !== conn.id);
                occupiedPortIds.delete(Number(conn.port_a));
                occupiedPortIds.delete(Number(conn.port_b));
                drawConnections();
            }
        } catch (err) {
            alert('Failed to remove connection: ' + err.message);
        }
    }

    // ── Connect mode ──────────────────────────────────────────────────────
    function enterConnectMode() {
        connectMode    = true;
        selectedPortId = null;
        connectBtn.textContent = 'Cancel';
        connectBtn.classList.replace('btn-secondary', 'btn-warning');
        colorPicker?.classList.remove('hidden');
        container.querySelectorAll('.port-card[data-port-id]').forEach(card => {
            const pid = parseInt(card.dataset.portId, 10);
            if (occupiedPortIds.has(pid)) {
                card.classList.add('conn-occupied');
            } else {
                card.classList.add('connectable');
            }
        });
    }

    function exitConnectMode() {
        connectMode    = false;
        selectedPortId = null;
        connectBtn.textContent = 'Connect Ports';
        connectBtn.classList.replace('btn-warning', 'btn-secondary');
        colorPicker?.classList.add('hidden');
        container.querySelectorAll('.port-card').forEach(c =>
            c.classList.remove('connectable', 'conn-selected', 'conn-occupied')
        );
    }

    connectBtn.addEventListener('click', () => {
        connectMode ? exitConnectMode() : enterConnectMode();
    });

    // ── Port click in connect mode (capture phase to block navigation) ────
    container.addEventListener('click', async e => {
        if (!connectMode) return;
        const card = e.target.closest('.port-card[data-port-id]');
        if (!card) return;
        e.stopPropagation();
        e.preventDefault();

        const portId = parseInt(card.dataset.portId, 10);

        // Silently ignore already-connected ports
        if (occupiedPortIds.has(portId)) return;

        if (selectedPortId === null) {
            selectedPortId = portId;
            card.classList.add('conn-selected');
        } else if (selectedPortId === portId) {
            selectedPortId = null;
            card.classList.remove('conn-selected');
        } else {
            const portA = selectedPortId;
            const portB = portId;
            const color = selectedColor;
            exitConnectMode();
            try {
                const res = await fetch('/api/connections', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken(),
                    },
                    body: JSON.stringify({ port_a: portA, port_b: portB, color }),
                });
                const data = await res.json();
                if (!res.ok) {
                    alert(data.error || 'Failed to create connection.');
                } else {
                    connections.push(data);
                    occupiedPortIds.add(portA);
                    occupiedPortIds.add(portB);
                    drawConnections();
                }
            } catch (err) {
                alert('Failed to create connection: ' + err.message);
            }
        }
    }, true); // capture phase

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && connectMode) exitConnectMode();
    });

    window.addEventListener('resize', drawConnections);

    fetch('/api/connections')
        .then(r => r.json())
        .then(data => {
            connections     = data.connections     ?? data;
            occupiedPortIds = new Set((data.occupied_port_ids ?? []).map(Number));
            drawConnections();
        })
        .catch(err => console.error('Failed to load connections:', err));
}

// ── Dashboard Device Reorder ──────────────────────────────────────────────────
function initDashboardReorder() {
    const container = document.getElementById('dashboard-devices');
    if (!container) return;

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let dragSrc = null;

    function getSections() {
        return [...container.querySelectorAll('.device-panel-section')];
    }

    function clearStates() {
        getSections().forEach(s =>
            s.classList.remove('drag-over-top', 'drag-over-bottom')
        );
    }

    getSections().forEach(section => {
        const handle = section.querySelector('.drag-handle');
        if (!handle) return;

        // Only allow drag when initiated from the handle
        handle.addEventListener('mousedown', () => {
            section.draggable = true;
        });

        section.addEventListener('dragstart', e => {
            dragSrc = section;
            e.dataTransfer.effectAllowed = 'move';
            requestAnimationFrame(() => section.classList.add('dragging'));
        });

        section.addEventListener('dragend', () => {
            section.draggable = false;
            section.classList.remove('dragging');
            clearStates();
            dragSrc = null;
        });

        section.addEventListener('dragover', e => {
            if (!dragSrc || dragSrc === section) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            clearStates();
            const rect = section.getBoundingClientRect();
            const mid  = rect.top + rect.height / 2;
            section.classList.add(e.clientY < mid ? 'drag-over-top' : 'drag-over-bottom');
        });

        section.addEventListener('dragleave', e => {
            if (!section.contains(e.relatedTarget)) {
                section.classList.remove('drag-over-top', 'drag-over-bottom');
            }
        });

        section.addEventListener('drop', async e => {
            e.preventDefault();
            if (!dragSrc || dragSrc === section) return;

            clearStates();

            const rect = section.getBoundingClientRect();
            const mid  = rect.top + rect.height / 2;
            if (e.clientY < mid) {
                container.insertBefore(dragSrc, section);
            } else {
                section.after(dragSrc);
            }

            const newOrder = getSections().map(s => parseInt(s.dataset.deviceId, 10));

            try {
                const res = await fetch('/api/devices/reorder', {
                    method:  'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken(),
                    },
                    body: JSON.stringify({ ids: newOrder }),
                });
                if (!res.ok) console.error('Failed to save device order');
            } catch (err) {
                console.error('Failed to save device order:', err);
            }
        });
    });
}
