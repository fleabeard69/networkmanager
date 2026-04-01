'use strict';

// ── Styled confirmation dialog ────────────────────────────────────────────────
// Returns a Promise<boolean>. Resolves true if the user confirms, false if they
// cancel (via button, overlay click, or Escape). The ESC handler is registered
// at the capture phase so it fires before any other modal's bubble-phase handler,
// and stopImmediatePropagation() ensures stacked modals don't also close.
function showConfirm(message, confirmText = 'Confirm') {
    return new Promise(resolve => {
        const overlay   = document.getElementById('confirm-overlay');
        const msgEl     = document.getElementById('confirm-message');
        const okBtn     = document.getElementById('confirm-ok');
        const cancelBtn = document.getElementById('confirm-cancel');
        const xBtn      = document.getElementById('confirm-x');

        // Fallback: if modal HTML is absent (e.g. login page), just resolve false
        if (!overlay) { resolve(false); return; }

        msgEl.textContent = message;
        okBtn.textContent = confirmText;
        overlay.classList.remove('hidden');
        cancelBtn.focus();

        function close(result) {
            overlay.classList.add('hidden');
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            xBtn.removeEventListener('click', onCancel);
            overlay.removeEventListener('click', onOverlay);
            document.removeEventListener('keydown', onKey, true);
            resolve(result);
        }

        const onOk      = () => close(true);
        const onCancel  = () => close(false);
        const onOverlay = e => { if (e.target === overlay) close(false); };
        // Capture phase: fires before any other modal's bubble-phase ESC handler
        const onKey = e => {
            if (e.key !== 'Escape') return;
            e.stopImmediatePropagation();
            close(false);
        };

        okBtn.addEventListener('click', onOk);
        cancelBtn.addEventListener('click', onCancel);
        xBtn.addEventListener('click', onCancel);
        overlay.addEventListener('click', onOverlay);
        document.addEventListener('keydown', onKey, true);
    });
}

document.addEventListener('DOMContentLoaded', () => {

    // ── Delete / dangerous action confirmations ───────────────────────────
    // Any submit button with data-confirm shows a styled modal before submitting.
    // data-confirm-ok sets the confirm button label (default "Confirm").
    document.querySelectorAll('button[data-confirm]').forEach(btn => {
        btn.addEventListener('click', async e => {
            e.preventDefault();
            btn.disabled = true;
            const form  = btn.closest('form');
            const label = btn.dataset.confirmOk || 'Confirm';
            try {
                if (await showConfirm(btn.dataset.confirm, label) && form) {
                    form.submit();
                }
            } finally {
                btn.disabled = false;
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
    // On non-dashboard pages (panel viewer), clicking a port card navigates
    // to its full edit page. On the dashboard the modal handles clicks instead.
    // data-href="/ports/{id}/edit" (non-dashboard server-rendered cards only)
    const hasDashboardModal = !!document.getElementById('dpm-overlay');
    document.querySelectorAll('.port-card[data-port-id]').forEach(card => {
        // Always set accessibility attrs so cards are keyboard-reachable
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'button');

        if (!hasDashboardModal && card.dataset.href) {
            card.addEventListener('click', () => {
                window.location.href = card.dataset.href;
            });
            card.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    window.location.href = card.dataset.href;
                }
            });
        }
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

    // ── Inline table editors ──────────────────────────────────────────────
    if (document.getElementById('ipm-overlay')) initPortsTableEdit();
    if (document.getElementById('idm-overlay')) initDevicesTableEdit();
    if (document.getElementById('dpm-overlay')) initDashboardPortEdit();

    // ── Table filters ─────────────────────────────────────────────────────
    if (document.getElementById('device-filter')) initDevicesFilter();
    if (document.getElementById('ports-search'))  initPortsFilter();

    // ── Dashboard port grid arrow-key navigation ──────────────────────────
    if (document.getElementById('dashboard-devices')) initPortGridArrowNav();

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
        if (!await showConfirm('Delete this port? This cannot be undone.', 'Delete')) return;
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
        if (!await showConfirm('Unassign this port from the device? The port record will be kept.', 'Unassign')) return;
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
        if (!await showConfirm('Delete this port? This cannot be undone.', 'Delete')) return;
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
        const r    = parseInt(ctrlRows.value, 10);
        const rear = parseInt(document.getElementById('ctrl-rear-rows')?.value ?? '0', 10);
        const c    = parseInt(ctrlCols.value, 10);
        if (r < 1 || r > 10 || rear < 0 || rear > 10 || c < 1 || c > 50) return;
        try {
            await Promise.all(devices.map(d =>
                apiFetch(`/api/devices/${d.id}/panel`, {
                    method: 'PATCH',
                    body:   JSON.stringify({ panel_rows: r, panel_rear_rows: rear, panel_cols: c }),
                })
            ));
            devices.forEach(d => { d.panel_rows = r; d.panel_rear_rows = rear; d.panel_cols = c; });
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

    const connectHint = colorPicker?.querySelector('.connect-color-hint');

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let connections     = [];
    let occupiedPortIds = new Set();
    let connectMode     = false;
    let selectedPortId  = null;
    let selectedAnchor  = null;
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

    // ── Port card coords (auto-routing: center-x, top/bot/mid edges) ─────
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

    // ── Explicit anchor point on a named edge ─────────────────────────────
    function portAnchorAt(portId, side) {
        const el = container.querySelector(`[data-port-id="${portId}"]`);
        if (!el) return null;
        const pr = el.getBoundingClientRect();
        const cr = container.getBoundingClientRect();
        const l = pr.left - cr.left, t = pr.top - cr.top;
        switch (side) {
            case 'top':    return { x: l + pr.width / 2, y: t };
            case 'bottom': return { x: l + pr.width / 2, y: t + pr.height };
            case 'left':   return { x: l,                y: t + pr.height / 2 };
            case 'right':  return { x: l + pr.width,     y: t + pr.height / 2 };
        }
        return null;
    }

    // ── Orthogonal polyline between two anchor points ─────────────────────
    function routePoints(ax, ay, sideA, bx, by, sideB) {
        const vA = sideA === 'top' || sideA === 'bottom';
        const vB = sideB === 'top' || sideB === 'bottom';
        if (vA && vB) {
            const midY = (ay + by) / 2;
            return [{ x: ax, y: ay }, { x: ax, y: midY }, { x: bx, y: midY }, { x: bx, y: by }];
        }
        if (!vA && !vB) {
            const midX = (ax + bx) / 2;
            return [{ x: ax, y: ay }, { x: midX, y: ay }, { x: midX, y: by }, { x: bx, y: by }];
        }
        // Mixed: single L-corner
        if (vA) return [{ x: ax, y: ay }, { x: ax, y: by }, { x: bx, y: by }];
        else     return [{ x: ax, y: ay }, { x: bx, y: ay }, { x: bx, y: by }];
    }

    // ── Which edge zone of a card was clicked ─────────────────────────────
    // Divides the card into 4 triangles using its diagonals.
    function getClickSide(card, e) {
        const rect = card.getBoundingClientRect();
        const rx = (e.clientX - rect.left) / rect.width;
        const ry = (e.clientY - rect.top)  / rect.height;
        if (rx + ry < 1) return rx > ry ? 'top'    : 'left';
        else             return rx > ry ? 'right'   : 'bottom';
    }

    // ── Draw all connection lines (with bridge arcs at crossings) ────────
    function drawConnections() {
        svg.innerHTML = '';
        svg.setAttribute('height', container.scrollHeight);

        // Reset all port card borders and wired indicator to CSS default
        container.querySelectorAll('.port-card[data-port-id]').forEach(card => {
            card.style.borderColor = '';
            card.classList.remove('port-wired');
        });

        const BRIDGE_R = 7;
        const f = v => v.toFixed(1);

        // Test two segments for intersection; returns t along segment A or null
        function segCross(ax, ay, bx, by, cx, cy, dx, dy) {
            const ex = bx-ax, ey = by-ay, fx = dx-cx, fy = dy-cy;
            const den = ex*fy - ey*fx;
            if (Math.abs(den) < 1e-8) return null;
            const gx = cx-ax, gy = cy-ay;
            const t = (gx*fy - gy*fx) / den;
            const u = (gx*ey - gy*ex) / den;
            return (t > 0.02 && t < 0.98 && u > 0.02 && u < 0.98) ? t : null;
        }

        // Find arc-length positions where polyline B crosses polyline A
        function findCrossings(ptsA, ptsB) {
            const hits = [];
            let dist = 0;
            for (let i = 0; i < ptsA.length - 1; i++) {
                const a0 = ptsA[i], a1 = ptsA[i+1];
                const slen = Math.hypot(a1.x-a0.x, a1.y-a0.y);
                for (let j = 0; j < ptsB.length - 1; j++) {
                    const b0 = ptsB[j], b1 = ptsB[j+1];
                    const t = segCross(a0.x,a0.y,a1.x,a1.y,b0.x,b0.y,b1.x,b1.y);
                    if (t !== null)
                        hits.push({ x: a0.x + t*(a1.x-a0.x), y: a0.y + t*(a1.y-a0.y), dist: dist + t*slen });
                }
                dist += slen;
            }
            return hits.sort((a, b) => a.dist - b.dist);
        }

        function arcLength(pts) {
            let len = 0;
            for (let i = 1; i < pts.length; i++)
                len += Math.hypot(pts[i].x-pts[i-1].x, pts[i].y-pts[i-1].y);
            return len;
        }

        // Build SVG path string with semicircular bridge arcs at each crossing
        function buildPath(pts, crossings) {
            const total = arcLength(pts);
            // Deduplicate and filter crossings too near endpoints or each other
            const brs = [];
            let lastDist = -Infinity;
            for (const c of crossings) {
                if (c.dist > BRIDGE_R * 2 && c.dist < total - BRIDGE_R * 2
                        && c.dist - lastDist > BRIDGE_R * 3) {
                    brs.push(c);
                    lastDist = c.dist;
                }
            }

            if (brs.length === 0)
                return pts.map((p, i) => `${i ? 'L' : 'M'}${f(p.x)},${f(p.y)}`).join(' ');

            let d = `M${f(pts[0].x)},${f(pts[0].y)}`;
            let arcDist = 0, bi = 0;

            for (let i = 1; i < pts.length; i++) {
                const p0 = pts[i-1], p1 = pts[i];
                const slen = Math.hypot(p1.x-p0.x, p1.y-p0.y);
                if (slen < 0.1) { arcDist += slen; continue; }
                const dx = (p1.x-p0.x)/slen, dy = (p1.y-p0.y)/slen;
                const segEnd = arcDist + slen;

                // Skip bridges already passed
                while (bi < brs.length && brs[bi].dist + BRIDGE_R < arcDist) bi++;

                // Insert bridges whose entry falls within this segment
                // Sweep direction: always arc toward screen top (smaller y)
                const sweep = Math.abs(dx) >= Math.abs(dy) ? (dx >= 0 ? 0 : 1) : (dy >= 0 ? 1 : 0);
                while (bi < brs.length && brs[bi].dist - BRIDGE_R < segEnd
                                       && brs[bi].dist - BRIDGE_R >= arcDist) {
                    const br = brs[bi++];
                    d += ` L${f(br.x - BRIDGE_R*dx)},${f(br.y - BRIDGE_R*dy)}`;
                    d += ` A${BRIDGE_R},${BRIDGE_R} 0 0,${sweep} ${f(br.x + BRIDGE_R*dx)},${f(br.y + BRIDGE_R*dy)}`;
                }

                d += ` L${f(p1.x)},${f(p1.y)}`;
                arcDist = segEnd;
            }
            return d;
        }

        // Gather path params + sampled points for every connection
        const pathInfos = [];
        for (const conn of connections) {
            const a = portAnchor(conn.port_a);
            const b = portAnchor(conn.port_b);
            if (!a || !b) continue;

            const color  = conn.color    || '#388bfd';
            const sideA  = conn.anchor_a || null;
            const sideB  = conn.anchor_b || null;

            let x1, y1, x2, y2, pts;

            if (!sideA && !sideB) {
                // Backward-compatible auto-routing: bottom of higher port → top of lower
                const [top, bot] = a.mid <= b.mid ? [a, b] : [b, a];
                x1 = top.cx; y1 = top.bot;
                x2 = bot.cx; y2 = bot.top;
                const midY = (y1 + y2) / 2;
                pts = [{ x: x1, y: y1 }, { x: x1, y: midY }, { x: x2, y: midY }, { x: x2, y: y2 }];
            } else {
                // Explicit anchors (fall back to auto side for any null half)
                const aSide = sideA || (a.mid <= b.mid ? 'bottom' : 'top');
                const bSide = sideB || (b.mid <= a.mid ? 'bottom' : 'top');
                const pa = portAnchorAt(conn.port_a, aSide);
                const pb = portAnchorAt(conn.port_b, bSide);
                if (!pa || !pb) continue;
                x1 = pa.x; y1 = pa.y;
                x2 = pb.x; y2 = pb.y;
                pts = routePoints(x1, y1, aSide, x2, y2, bSide);
            }

            pathInfos.push({ conn, color, x1, y1, x2, y2, pts });
        }

        // Draw each path; later paths arc over earlier ones at crossings
        pathInfos.forEach((pd, idx) => {
            const bridges = [];
            for (let j = 0; j < idx; j++)
                bridges.push(...findCrossings(pd.pts, pathInfos[j].pts));
            bridges.sort((a, b) => a.dist - b.dist);

            const d = buildPath(pd.pts, bridges);

            const hit = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            hit.setAttribute('class', 'conn-hit');
            hit.setAttribute('d', d);
            hit.setAttribute('stroke', 'transparent');
            hit.setAttribute('stroke-width', '18');
            hit.setAttribute('fill', 'none');
            hit.title = 'Click to remove this connection';
            hit.addEventListener('click', () => removeConnection(pd.conn));
            svg.appendChild(hit);

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('class', 'conn-line');
            path.setAttribute('d', d);
            path.setAttribute('stroke', pd.color);
            path.setAttribute('stroke-width', '3');
            path.setAttribute('stroke-dasharray', '7 4');
            path.setAttribute('fill', 'none');
            path.setAttribute('opacity', '0.85');
            svg.appendChild(path);

            [{ x: pd.x1, y: pd.y1 }, { x: pd.x2, y: pd.y2 }].forEach(({ x, y }) => {
                const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                dot.setAttribute('class', 'conn-dot');
                dot.setAttribute('cx', x);
                dot.setAttribute('cy', y);
                dot.setAttribute('r', '4');
                dot.setAttribute('fill', pd.color);
                svg.appendChild(dot);
            });
        });

        // Tint connected port card borders to match their line color and mark as wired
        connections.forEach(conn => {
            const color = conn.color || '#388bfd';
            [conn.port_a, conn.port_b].forEach(pid => {
                const card = container.querySelector(`[data-port-id="${pid}"]`);
                if (card) {
                    card.style.borderColor = color;
                    card.classList.add('port-wired');
                }
            });
        });
    }

    // ── Remove a connection ───────────────────────────────────────────────
    async function removeConnection(conn) {
        const labelA = conn.port_a_label || `Port ${conn.port_a_number}`;
        const labelB = conn.port_b_label || `Port ${conn.port_b_number}`;
        if (!await showConfirm(`Remove connection between ${labelA} and ${labelB}?`, 'Remove')) return;
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
                if (connectMode) {
                    container.querySelectorAll('.port-card[data-port-id]').forEach(card => {
                        const pid = parseInt(card.dataset.portId, 10);
                        card.classList.toggle('conn-occupied', occupiedPortIds.has(pid));
                        card.classList.toggle('connectable', !occupiedPortIds.has(pid));
                    });
                }
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
        if (connectHint) connectHint.textContent = 'Click a free port to connect — or a wired port to disconnect. ESC to cancel.';
        svg.classList.add('connect-mode-active');
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
        selectedAnchor = null;
        connectBtn.textContent = 'Connect Ports';
        connectBtn.classList.replace('btn-warning', 'btn-secondary');
        colorPicker?.classList.add('hidden');
        svg.classList.remove('connect-mode-active');
        container.querySelectorAll('.port-card').forEach(c => {
            c.classList.remove('connectable', 'conn-selected', 'conn-occupied');
            delete c.dataset.zone;
        });
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

        // Occupied port: clicking it in connect mode removes its connection
        if (occupiedPortIds.has(portId)) {
            const conn = connections.find(c => Number(c.port_a) === portId || Number(c.port_b) === portId);
            if (conn) removeConnection(conn);
            return;
        }

        if (selectedPortId === null) {
            selectedPortId = portId;
            selectedAnchor = getClickSide(card, e);
            card.classList.add('conn-selected');
            if (connectHint) {
                const num   = card.dataset.portNumber ?? '?';
                const label = card.dataset.label;
                const desc  = label ? `"${label}" (Port ${num})` : `Port ${num}`;
                connectHint.textContent = `${desc} selected — now click the destination port`;
            }
        } else if (selectedPortId === portId) {
            selectedPortId = null;
            selectedAnchor = null;
            card.classList.remove('conn-selected');
            if (connectHint) connectHint.textContent = 'Click a free port to connect — or a wired port to disconnect. ESC to cancel.';
        } else {
            const portA   = selectedPortId;
            const portB   = portId;
            const anchorA = selectedAnchor;
            const anchorB = getClickSide(card, e);
            const color   = selectedColor;
            exitConnectMode();
            try {
                const res = await fetch('/api/connections', {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken(),
                    },
                    body: JSON.stringify({ port_a: portA, port_b: portB, color,
                                          anchor_a: anchorA, anchor_b: anchorB }),
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

    // ── Zone highlight on hover during connect mode ───────────────────────
    container.addEventListener('mousemove', e => {
        if (!connectMode) return;
        const card = e.target.closest('.port-card.connectable');
        container.querySelectorAll('.port-card[data-zone]').forEach(c => delete c.dataset.zone);
        if (card) card.dataset.zone = getClickSide(card, e);
    });
    container.addEventListener('mouseleave', () => {
        container.querySelectorAll('.port-card[data-zone]').forEach(c => delete c.dataset.zone);
    });

    window.addEventListener('resize', drawConnections);
    container.addEventListener('deviceReordered', drawConnections);
    container.addEventListener('portUpdated', drawConnections);

    fetch('/api/connections')
        .then(r => r.json())
        .then(data => {
            connections     = data.connections     ?? data;
            occupiedPortIds = new Set((data.occupied_port_ids ?? []).map(Number));
            drawConnections();
        })
        .catch(err => console.error('Failed to load connections:', err));
}

// ── Inline Port Table Edit ────────────────────────────────────────────────────
function initPortsTableEdit() {

    const overlay     = document.getElementById('ipm-overlay');
    const modalTitle  = document.getElementById('ipm-title');
    const modalError  = document.getElementById('ipm-error');
    const modalSave   = document.getElementById('ipm-save');
    const modalClose  = document.getElementById('ipm-close');
    const modalCancel = document.getElementById('ipm-cancel');
    const fullEdit    = document.getElementById('ipm-full-edit');
    const mPortNum    = document.getElementById('ipm-port-number');
    const mLabel      = document.getElementById('ipm-label');
    const mPortType   = document.getElementById('ipm-port-type');
    const mSpeed      = document.getElementById('ipm-speed');
    const mStatus     = document.getElementById('ipm-status');
    const mDevice     = document.getElementById('ipm-device');
    const mVlan       = document.getElementById('ipm-vlan');
    const mPoe        = document.getElementById('ipm-poe');
    const mNotes      = document.getElementById('ipm-notes');

    let currentTr = null;

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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

    function openModal(tr) {
        currentTr = tr;
        const d = tr.dataset;
        modalTitle.textContent = `Edit Port ${d.portNumber}`;
        fullEdit.href = `/ports/${parseInt(d.id, 10)}/edit`;
        mPortNum.value  = d.portNumber ?? '';
        mLabel.value    = d.label      ?? '';
        mPortType.value = d.portType   ?? 'rj45';
        mSpeed.value    = d.speed      ?? '1G';
        mStatus.value   = d.status     ?? 'active';
        if (mDevice) mDevice.value = d.deviceId ?? '';
        mVlan.value  = d.vlan  ?? '';
        mPoe.checked = d.poe === '1';
        mNotes.value = d.notes ?? '';
        hideError();
        overlay.classList.remove('hidden');
        mPortNum.focus();
    }

    function closeModal() {
        overlay.classList.add('hidden');
        currentTr = null;
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

    async function savePort() {
        if (!currentTr) return;
        hideError();
        modalSave.disabled = true;

        const d      = currentTr.dataset;
        const portId = parseInt(d.id, 10);

        const payload = {
            port_number: parseInt(mPortNum.value, 10) || null,
            label:       mLabel.value.trim(),
            port_type:   mPortType.value,
            speed:       mSpeed.value,
            status:      mStatus.value,
            device_id:   mDevice?.value ? parseInt(mDevice.value, 10) : null,
            vlan_id:     mVlan.value ? parseInt(mVlan.value, 10) : null,
            poe_enabled: mPoe.checked,
            notes:       mNotes.value.trim(),
            port_row:    parseInt(d.row, 10),
            port_col:    parseInt(d.col, 10),
        };

        try {
            const updated = await apiFetch(`/api/ports/${portId}`, {
                method: 'PATCH',
                body:   JSON.stringify(payload),
            });
            updatePortRow(currentTr, updated);
            closeModal();
        } catch (err) {
            showError(err.message);
        } finally {
            modalSave.disabled = false;
        }
    }

    function updatePortRow(tr, p) {
        // Keep data attributes in sync so re-opening the modal shows fresh values
        tr.dataset.portNumber = p.port_number;
        tr.dataset.label      = p.label      ?? '';
        tr.dataset.portType   = p.port_type;
        tr.dataset.speed      = p.speed;
        tr.dataset.status     = p.status;
        tr.dataset.deviceId       = p.device_id       ?? '';
        tr.dataset.deviceHostname = p.device_hostname ?? '';
        tr.dataset.vlan       = p.vlan_id    ?? '';
        tr.dataset.poe        = (p.poe_enabled === true || p.poe_enabled === 't' || p.poe_enabled === '1') ? '1' : '0';
        tr.dataset.notes      = p.notes      ?? '';

        // Update visible cells — all via textContent or safe DOM construction (no innerHTML from user data)
        const portNumCell = tr.querySelector('.cell-port-number');
        if (portNumCell) portNumCell.textContent = String(p.port_number);

        const labelCell = tr.querySelector('.cell-label');
        if (labelCell) labelCell.textContent = p.label ?? '';

        const typeCell = tr.querySelector('.cell-type');
        if (typeCell) {
            typeCell.replaceChildren();
            const badge = document.createElement('span');
            badge.className = 'badge badge-type';
            badge.textContent = p.port_type.toUpperCase();
            typeCell.appendChild(badge);
        }

        const speedCell = tr.querySelector('.cell-speed');
        if (speedCell) speedCell.textContent = p.speed;

        const poeCell = tr.querySelector('.cell-poe');
        if (poeCell) {
            const isPoE = p.poe_enabled === true || p.poe_enabled === 't' || p.poe_enabled === '1';
            poeCell.replaceChildren();
            const el = document.createElement('span');
            el.className = isPoE ? 'badge badge-success' : 'text-muted';
            el.textContent = isPoE ? 'Yes' : '—';
            poeCell.appendChild(el);
        }

        const vlanCell = tr.querySelector('.cell-vlan');
        if (vlanCell) {
            vlanCell.replaceChildren();
            if (p.vlan_id) {
                vlanCell.textContent = String(p.vlan_id);
            } else {
                const span = document.createElement('span');
                span.className = 'text-muted';
                span.textContent = '—';
                vlanCell.appendChild(span);
            }
        }

        const statusCell = tr.querySelector('.cell-status');
        if (statusCell) {
            statusCell.replaceChildren();
            const badge = document.createElement('span');
            const cls = p.status === 'active'   ? 'badge-success'
                      : p.status === 'disabled'  ? 'badge-danger'
                      : 'badge-neutral';
            badge.className = `badge ${cls}`;
            badge.textContent = p.status.charAt(0).toUpperCase() + p.status.slice(1);
            statusCell.appendChild(badge);
        }

        const deviceCell = tr.querySelector('.cell-device');
        if (deviceCell) {
            deviceCell.replaceChildren();
            if (p.device_id && p.device_hostname) {
                const a = document.createElement('a');
                a.className = 'link';
                a.href = `/devices/${parseInt(p.device_id, 10)}`;
                a.textContent = p.device_hostname;
                deviceCell.appendChild(a);
            } else {
                const span = document.createElement('span');
                span.className = 'text-muted';
                span.textContent = '—';
                deviceCell.appendChild(span);
            }
        }

        const notesCell = tr.querySelector('.notes-cell');
        if (notesCell) notesCell.textContent = p.notes ?? '';
    }

    // Intercept all [data-inline-edit] links in the ports table
    document.querySelectorAll('[data-inline-edit]').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const tr = link.closest('tr');
            if (tr) openModal(tr);
        });
    });

    modalSave.addEventListener('click',   savePort);
    modalClose.addEventListener('click',  closeModal);
    modalCancel.addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeModal();
    });
}

// ── Inline Device Table Edit ──────────────────────────────────────────────────
function initDevicesTableEdit() {

    const overlay     = document.getElementById('idm-overlay');
    const modalTitle  = document.getElementById('idm-title');
    const modalError  = document.getElementById('idm-error');
    const modalSave   = document.getElementById('idm-save');
    const modalClose  = document.getElementById('idm-close');
    const modalCancel = document.getElementById('idm-cancel');
    const fullEdit    = document.getElementById('idm-full-edit');
    const mHostname   = document.getElementById('idm-hostname');
    const mType       = document.getElementById('idm-type');
    const mMac        = document.getElementById('idm-mac');
    const mNotes      = document.getElementById('idm-notes');

    let currentTr = null;

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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

    function openModal(tr) {
        currentTr = tr;
        const d = tr.dataset;
        modalTitle.textContent = `Edit: ${d.hostname}`;
        fullEdit.href  = `/devices/${parseInt(d.id, 10)}/edit`;
        mHostname.value = d.hostname ?? '';
        mType.value     = d.type     ?? 'unknown';
        mMac.value      = d.mac      ?? '';
        mNotes.value    = d.notes    ?? '';
        hideError();
        overlay.classList.remove('hidden');
        mHostname.focus();
    }

    function closeModal() {
        overlay.classList.add('hidden');
        currentTr = null;
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

    async function saveDevice() {
        if (!currentTr) return;
        hideError();
        modalSave.disabled = true;

        const d        = currentTr.dataset;
        const deviceId = parseInt(d.id, 10);

        const payload = {
            hostname:        mHostname.value.trim(),
            device_type:     mType.value,
            mac_address:     mMac.value.trim() || null,
            notes:           mNotes.value.trim(),
            panel_rear_rows: parseInt(d.rearRows || '0', 10),
        };

        try {
            const updated = await apiFetch(`/api/devices/${deviceId}`, {
                method: 'PATCH',
                body:   JSON.stringify(payload),
            });
            updateDeviceRow(currentTr, updated);
            closeModal();
        } catch (err) {
            showError(err.message);
        } finally {
            modalSave.disabled = false;
        }
    }

    function updateDeviceRow(tr, device) {
        // Keep data attributes in sync
        tr.dataset.hostname = device.hostname;
        tr.dataset.type     = device.device_type;
        tr.dataset.mac      = device.mac_address  ?? '';
        tr.dataset.notes    = device.notes        ?? '';
        tr.dataset.rearRows = device.panel_rear_rows ?? '0';

        // Update hostname cell — rebuild the link via safe DOM construction
        const hostnameCell = tr.querySelector('.cell-hostname');
        if (hostnameCell) {
            hostnameCell.replaceChildren();
            const a = document.createElement('a');
            a.href = `/devices/${parseInt(device.id, 10)}`;
            a.className = 'link link-strong';
            a.textContent = device.hostname;
            hostnameCell.appendChild(a);
        }

        // Update type badge
        const typeCell = tr.querySelector('.cell-type');
        if (typeCell) {
            typeCell.replaceChildren();
            const badge = document.createElement('span');
            badge.className = 'badge badge-type';
            // Match PHP: str_replace('-', ' ', ucfirst(device_type))
            const typeName = device.device_type.replace(/-/g, ' ');
            badge.textContent = typeName.charAt(0).toUpperCase() + typeName.slice(1);
            typeCell.appendChild(badge);
        }

        // Update MAC cell
        const macCell = tr.querySelector('.cell-mac');
        if (macCell) {
            macCell.replaceChildren();
            if (device.mac_address) {
                macCell.textContent = device.mac_address;
            } else {
                const span = document.createElement('span');
                span.className = 'text-muted';
                span.textContent = '—';
                macCell.appendChild(span);
            }
        }
    }

    // Intercept all [data-inline-edit] links in the devices table
    document.querySelectorAll('[data-inline-edit]').forEach(link => {
        link.addEventListener('click', e => {
            e.preventDefault();
            const tr = link.closest('tr');
            if (tr) openModal(tr);
        });
    });

    modalSave.addEventListener('click',   saveDevice);
    modalClose.addEventListener('click',  closeModal);
    modalCancel.addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeModal();
    });
}

// ── Devices Table Filter ──────────────────────────────────────────────────────
function initDevicesFilter() {
    const input     = document.getElementById('device-filter');
    const tbody     = document.getElementById('devices-tbody');
    const noResults = document.getElementById('devices-no-results');
    if (!input || !tbody) return;

    input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        let count = 0;
        tbody.querySelectorAll('tr[data-id]').forEach(tr => {
            const match = !q
                || (tr.dataset.hostname || '').toLowerCase().includes(q)
                || (tr.dataset.type     || '').toLowerCase().includes(q)
                || (tr.dataset.mac      || '').toLowerCase().includes(q)
                || (tr.dataset.notes    || '').toLowerCase().includes(q);
            tr.classList.toggle('filter-hidden', !match);
            if (match) count++;
        });
        if (noResults) noResults.classList.toggle('hidden', count > 0);
    });
}

// ── Ports Table Filter ────────────────────────────────────────────────────────
function initPortsFilter() {
    const searchInput  = document.getElementById('ports-search');
    const statusSelect = document.getElementById('ports-status-filter');
    const tbody        = document.getElementById('ports-tbody');
    const noResults    = document.getElementById('ports-no-results');
    if (!searchInput || !tbody) return;

    function applyFilter() {
        const q      = searchInput.value.trim().toLowerCase();
        const status = statusSelect ? statusSelect.value : '';
        let count = 0;
        tbody.querySelectorAll('tr[data-id]').forEach(tr => {
            const textMatch = !q
                || (tr.dataset.portNumber      || '').toLowerCase().includes(q)
                || (tr.dataset.label           || '').toLowerCase().includes(q)
                || (tr.dataset.deviceHostname  || '').toLowerCase().includes(q)
                || (tr.dataset.vlan            || '').toLowerCase().includes(q)
                || (tr.dataset.notes           || '').toLowerCase().includes(q);
            const statusMatch = !status || (tr.dataset.status || '') === status;
            const match = textMatch && statusMatch;
            tr.classList.toggle('filter-hidden', !match);
            if (match) count++;
        });
        if (noResults) noResults.classList.toggle('hidden', count > 0);
    }

    searchInput.addEventListener('input', applyFilter);
    if (statusSelect) statusSelect.addEventListener('change', applyFilter);
}

// ── Dashboard Port Grid Arrow-Key Navigation ──────────────────────────────────
// Implements roving tabindex so Tab moves between device panels and arrow keys
// navigate within a single panel's port grid.
function initPortGridArrowNav() {
    const container = document.getElementById('dashboard-devices');
    if (!container) return;

    container.querySelectorAll('.port-grid[data-cols]').forEach(grid => {
        const allCards = [...grid.querySelectorAll('.port-card[data-port-id]')];
        if (allCards.length === 0) return;

        // Sort top-left first so the first tabbable card is predictable
        allCards.sort((a, b) => {
            const rDiff = parseInt(a.dataset.row, 10) - parseInt(b.dataset.row, 10);
            return rDiff !== 0 ? rDiff : parseInt(a.dataset.col, 10) - parseInt(b.dataset.col, 10);
        });
        // Roving tabindex: only the first card of each grid starts in the tab order
        allCards.forEach((c, i) => c.setAttribute('tabindex', i === 0 ? '0' : '-1'));

        // Keep roving target in sync when a card gains focus via mouse or Tab
        grid.addEventListener('focusin', e => {
            const card = e.target.closest('.port-card[data-port-id]');
            if (!card) return;
            grid.querySelectorAll('.port-card[data-port-id]').forEach(c =>
                c.setAttribute('tabindex', c === card ? '0' : '-1')
            );
        });

        // Arrow keys navigate between cards within this grid
        grid.addEventListener('keydown', e => {
            if (!['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) return;
            const card = e.target.closest('.port-card[data-port-id]');
            if (!card) return;

            const row = parseInt(card.dataset.row, 10);
            const col = parseInt(card.dataset.col, 10);
            let targetRow = row, targetCol = col;

            if (e.key === 'ArrowUp')    targetRow--;
            if (e.key === 'ArrowDown')  targetRow++;
            if (e.key === 'ArrowLeft')  targetCol--;
            if (e.key === 'ArrowRight') targetCol++;

            // targetRow/targetCol are integers from arithmetic — safe in selector
            const target = grid.querySelector(
                `.port-card[data-row="${targetRow}"][data-col="${targetCol}"]`
            );
            // At the grid edge there is no adjacent card: let the browser's default
            // action (page scroll) proceed rather than silently trapping the user
            if (!target) return;

            e.preventDefault();
            grid.querySelectorAll('.port-card[data-port-id]').forEach(c =>
                c.setAttribute('tabindex', '-1')
            );
            target.setAttribute('tabindex', '0');
            target.focus();
        });
    });
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
        const header = section.querySelector('.device-panel-section-header');
        if (!header) return;

        // Only allow drag when initiated from the header (not links/buttons inside it)
        header.addEventListener('mousedown', e => {
            if (e.target.closest('a, button')) return;
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

            container.dispatchEvent(new CustomEvent('deviceReordered'));
        });
    });
}

// ── Dashboard Port Edit Modal ─────────────────────────────────────────────────
function initDashboardPortEdit() {

    const overlay     = document.getElementById('dpm-overlay');
    const modalTitle  = document.getElementById('dpm-title');
    const modalError  = document.getElementById('dpm-error');
    const modalSave   = document.getElementById('dpm-save');
    const modalClose  = document.getElementById('dpm-close');
    const modalCancel = document.getElementById('dpm-cancel');
    const fullEdit    = document.getElementById('dpm-full-edit');
    const mPortNum    = document.getElementById('dpm-port-number');
    const mLabel      = document.getElementById('dpm-label');
    const mPortType   = document.getElementById('dpm-port-type');
    const mSpeed      = document.getElementById('dpm-speed');
    const mStatus     = document.getElementById('dpm-status');
    const mDevice     = document.getElementById('dpm-device');
    const mVlan       = document.getElementById('dpm-vlan');
    const mPoe        = document.getElementById('dpm-poe');
    const mNotes      = document.getElementById('dpm-notes');

    let currentCard = null;

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ?? '';

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

    function openModal(card) {
        currentCard = card;
        const d = card.dataset;
        modalTitle.textContent = `Edit Port ${d.portNumber}`;
        fullEdit.href  = `/ports/${parseInt(d.portId, 10)}/edit`;
        mPortNum.value  = d.portNumber ?? '';
        mLabel.value    = d.label      ?? '';
        mPortType.value = d.portType   ?? 'rj45';
        mSpeed.value    = d.speed      ?? '1G';
        mStatus.value   = d.status     ?? 'active';
        if (mDevice) mDevice.value = d.deviceId ?? '';
        mVlan.value  = d.vlan  ?? '';
        mPoe.checked = d.poe === '1';
        mNotes.value = d.notes ?? '';
        hideError();
        overlay.classList.remove('hidden');
        mPortNum.focus();
    }

    function closeModal() {
        overlay.classList.add('hidden');
        currentCard = null;
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

    async function savePort() {
        if (!currentCard) return;
        hideError();
        modalSave.disabled = true;

        const d      = currentCard.dataset;
        const portId = parseInt(d.portId, 10);

        const payload = {
            port_number: parseInt(mPortNum.value, 10) || null,
            label:       mLabel.value.trim(),
            port_type:   mPortType.value,
            speed:       mSpeed.value,
            status:      mStatus.value,
            device_id:   mDevice?.value ? parseInt(mDevice.value, 10) : null,
            vlan_id:     mVlan.value ? parseInt(mVlan.value, 10) : null,
            poe_enabled: mPoe.checked,
            notes:       mNotes.value.trim(),
            port_row:    parseInt(d.row, 10),
            port_col:    parseInt(d.col, 10),
        };

        try {
            const updated = await apiFetch(`/api/ports/${portId}`, {
                method: 'PATCH',
                body:   JSON.stringify(payload),
            });
            updatePortCard(currentCard, updated);
            closeModal();
        } catch (err) {
            showError(err.message);
        } finally {
            modalSave.disabled = false;
        }
    }

    function updatePortCard(card, p) {
        // Sync data attributes
        card.dataset.portNumber = p.port_number;
        card.dataset.label      = p.label      ?? '';
        card.dataset.portType   = p.port_type;
        card.dataset.speed      = p.speed;
        card.dataset.status     = p.status;
        card.dataset.deviceId   = p.device_id  ?? '';
        card.dataset.vlan       = p.vlan_id    ?? '';
        card.dataset.poe        = (p.poe_enabled === true || p.poe_enabled === 't' || p.poe_enabled === '1') ? '1' : '0';
        card.dataset.notes      = p.notes      ?? '';

        // Update title attribute
        card.title = `Port ${p.port_number}${p.label ? ' \u2014 ' + p.label : ''}`;

        // Update color class — match PHP template logic
        ['port-connected', 'port-disabled', 'port-wan', 'port-mgmt'].forEach(c => card.classList.remove(c));
        if (p.status === 'disabled')     card.classList.add('port-disabled');
        else if (p.port_type === 'wan')  card.classList.add('port-wan');
        else if (p.port_type === 'mgmt') card.classList.add('port-mgmt');
        else                             card.classList.add('port-connected');

        // Update port number
        const portNumEl = card.querySelector('.port-number');
        if (portNumEl) portNumEl.textContent = String(p.port_number);

        // Update type badge
        const typeBadge = card.querySelector('.port-type-badge');
        if (typeBadge) typeBadge.textContent = p.port_type.toUpperCase();

        // Update PoE badge — show/hide as needed
        const isPoE = p.poe_enabled === true || p.poe_enabled === 't' || p.poe_enabled === '1';
        let poeBadge = card.querySelector('.port-poe-badge');
        if (isPoE && !poeBadge) {
            poeBadge = document.createElement('span');
            poeBadge.className = 'port-poe-badge';
            poeBadge.title = 'PoE Enabled';
            poeBadge.textContent = '\u26a1';
            const typeEl = card.querySelector('.port-type-badge');
            if (typeEl) typeEl.after(poeBadge);
            else card.appendChild(poeBadge);
        } else if (!isPoE && poeBadge) {
            poeBadge.remove();
        }

        // Update label/device area
        const deviceDiv = card.querySelector('.port-device');
        if (deviceDiv) {
            deviceDiv.replaceChildren();
            if (p.label) {
                deviceDiv.textContent = p.label;
            } else if (p.status === 'disabled') {
                const span = document.createElement('span');
                span.className = 'port-empty-label';
                span.textContent = 'Disabled';
                deviceDiv.appendChild(span);
            } else {
                const span = document.createElement('span');
                span.className = 'port-empty-label';
                span.textContent = '\u00a0';
                deviceDiv.appendChild(span);
            }
        }

        // Update VLAN display
        let vlanDiv = card.querySelector('.port-vlan');
        if (p.vlan_id) {
            if (!vlanDiv) {
                vlanDiv = document.createElement('div');
                vlanDiv.className = 'port-vlan';
                card.appendChild(vlanDiv);
            }
            vlanDiv.textContent = `VLAN ${p.vlan_id}`;
        } else if (vlanDiv) {
            vlanDiv.remove();
        }

        // Notify connection system to redraw lines after card geometry may have changed
        const container = document.getElementById('dashboard-devices');
        if (container) container.dispatchEvent(new CustomEvent('portUpdated'));
    }

    // Delegated click on the dashboard container — connection mode's capture
    // listener calls stopPropagation() when active, so this won't fire then.
    const container = document.getElementById('dashboard-devices');
    if (!container) return;

    container.addEventListener('click', e => {
        const card = e.target.closest('.port-card[data-port-id]');
        if (!card) return;
        openModal(card);
    });

    // Keyboard: Enter/Space on focused card
    container.addEventListener('keydown', e => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const card = e.target.closest('.port-card[data-port-id]');
        if (!card) return;
        e.preventDefault();
        openModal(card);
    });

    modalSave.addEventListener('click',   savePort);
    modalClose.addEventListener('click',  closeModal);
    modalCancel.addEventListener('click', closeModal);
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(); });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && !overlay.classList.contains('hidden')) closeModal();
    });
}
