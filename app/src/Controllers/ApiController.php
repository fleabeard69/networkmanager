<?php
declare(strict_types=1);

class ApiController
{
    public function __construct(
        private PortModel $portModel,
        private DeviceModel $deviceModel,
        private ?ConnectionModel $connectionModel = null
    ) {
        Auth::requireLogin();
    }

    // ── GET /api/ports ────────────────────────────────────────────────────
    public function listPorts(): void
    {
        $this->json($this->portModel->all());
    }

    // ── GET /api/devices ──────────────────────────────────────────────────
    public function listDevices(): void
    {
        $this->json($this->deviceModel->all());
    }

    // ── POST /api/ports ───────────────────────────────────────────────────
    public function createPort(): void
    {
        $this->verifyCsrf();

        $data = $this->validatePortData($this->body());
        if (is_string($data)) {
            $this->json(['error' => $data], 422);
        }

        try {
            $id   = $this->portModel->create($data);
            $port = $this->portModel->find($id);
            $this->json($port, 201);
        } catch (PDOException $e) {
            $msg = str_contains(strtolower($e->getMessage()), 'unique')
                ? 'Port number already exists on this device.'
                : 'Database error. Please try again.';
            $this->json(['error' => $msg], 409);
        }
    }

    // ── PATCH /api/ports/{id} ─────────────────────────────────────────────
    public function updatePort(int $id): void
    {
        $this->verifyCsrf();

        $port = $this->portModel->find($id);
        if (!$port) {
            $this->json(['error' => 'Port not found.'], 404);
        }

        $data = $this->validatePortData($this->body());
        if (is_string($data)) {
            $this->json(['error' => $data], 422);
        }

        try {
            $this->portModel->update($id, $data);
            $this->json($this->portModel->find($id));
        } catch (PDOException $e) {
            $msg = str_contains(strtolower($e->getMessage()), 'unique')
                ? 'Port number already exists on this device.'
                : 'Database error. Please try again.';
            $this->json(['error' => $msg], 409);
        }
    }

    // ── PATCH /api/ports/{id}/position ────────────────────────────────────
    public function movePort(int $id): void
    {
        $this->verifyCsrf();

        $port = $this->portModel->find($id);
        if (!$port) {
            $this->json(['error' => 'Port not found.'], 404);
        }

        $body = $this->body();

        $row = filter_var($body['port_row'] ?? null, FILTER_VALIDATE_INT,
                          ['options' => ['min_range' => 1, 'max_range' => 20]]);
        $col = filter_var($body['port_col'] ?? null, FILTER_VALIDATE_INT,
                          ['options' => ['min_range' => 1, 'max_range' => 50]]);

        if ($row === false || $col === false) {
            $this->json(['error' => 'Invalid position (row 1–20, col 1–50).'], 422);
        }

        $this->portModel->move($id, $row, $col);
        $this->json($this->portModel->find($id));
    }

    // ── PATCH /api/devices/{id}/panel ────────────────────────────────────
    public function updateDevicePanel(int $id): void
    {
        $this->verifyCsrf();

        $device = $this->deviceModel->find($id);
        if (!$device) {
            $this->json(['error' => 'Device not found.'], 404);
        }

        $body     = $this->body();
        $rows     = filter_var($body['panel_rows']      ?? null, FILTER_VALIDATE_INT,
                               ['options' => ['min_range' => 1, 'max_range' => 10]]);
        $rearRows = filter_var($body['panel_rear_rows'] ?? 0,    FILTER_VALIDATE_INT,
                               ['options' => ['min_range' => 0, 'max_range' => 10]]);
        $cols     = filter_var($body['panel_cols']      ?? null, FILTER_VALIDATE_INT,
                               ['options' => ['min_range' => 1, 'max_range' => 50]]);

        if ($rows === false || $rearRows === false || $cols === false) {
            $this->json(['error' => 'panel_rows must be 1–10, panel_rear_rows 0–10, panel_cols 1–50.'], 422);
        }

        $this->deviceModel->updatePanelDims($id, $rows, $rearRows, $cols);
        $this->json($this->deviceModel->find($id));
    }

    // ── GET /api/connections ──────────────────────────────────────────────
    public function listConnections(): void
    {
        $this->json([
            'connections'      => $this->connectionModel->all(),
            'occupied_port_ids' => $this->connectionModel->occupiedPortIds(),
        ]);
    }

    // ── POST /api/connections ─────────────────────────────────────────────
    public function createConnection(): void
    {
        $this->verifyCsrf();

        $body  = $this->body();
        $portA = filter_var($body['port_a'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $portB = filter_var($body['port_b'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($portA === false || $portB === false || $portA === $portB) {
            $this->json(['error' => 'Two distinct valid port IDs are required.'], 422);
        }

        $validColors = [
            '#388bfd', '#2ea043', '#d29922', '#da3633', '#bc8cff', '#ff7b72',
            '#ffa657', '#39d353', '#79c0ff', '#d2a8ff', '#e3b341', '#f08bb4',
            '#58a6ff', '#7ee787', '#c9d1d9', '#8b949e',
        ];
        $color = $body['color'] ?? '#388bfd';
        if (!in_array($color, $validColors, true)) {
            $color = '#388bfd';
        }

        try {
            $id = $this->connectionModel->create($portA, $portB, $color);
            $this->json($this->connectionModel->find($id), 201);
        } catch (RuntimeException $e) {
            $this->json(['error' => $e->getMessage()], 409);
        } catch (PDOException $e) {
            $msg = str_contains(strtolower($e->getMessage()), 'unique')
                ? 'These ports are already connected.'
                : 'Database error.';
            $this->json(['error' => $msg], 409);
        }
    }

    // ── DELETE /api/connections/{id} ──────────────────────────────────────
    public function deleteConnection(int $id): void
    {
        $this->verifyCsrf();

        $conn = $this->connectionModel->find($id);
        if (!$conn) {
            $this->json(['error' => 'Connection not found.'], 404);
        }

        $this->connectionModel->delete($id);
        $this->json(['deleted' => true]);
    }

    // ── PATCH /api/devices/reorder ────────────────────────────────────────
    public function reorderDevices(): void
    {
        $this->verifyCsrf();

        $body = $this->body();
        $ids  = $body['ids'] ?? null;

        if (!is_array($ids)) {
            $this->json(['error' => 'ids must be an array.'], 422);
        }

        $validated = [];
        foreach ($ids as $id) {
            $val = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($val === false) {
                $this->json(['error' => 'Invalid device ID.'], 422);
            }
            $validated[] = $val;
        }

        $this->deviceModel->reorder($validated);
        $this->json(['reordered' => true]);
    }

    // ── GET /api/ports/unassigned ─────────────────────────────────────────
    public function listUnassignedPorts(): void
    {
        $this->json($this->portModel->allUnassigned());
    }

    // ── GET /api/devices/{id}/ports ───────────────────────────────────────
    public function listDevicePorts(int $deviceId): void
    {
        $device = $this->deviceModel->find($deviceId);
        if (!$device) {
            $this->json(['error' => 'Device not found.'], 404);
        }
        $this->json($this->portModel->allForDevice($deviceId));
    }

    // ── PATCH /api/ports/{id}/assign ──────────────────────────────────────
    public function assignPort(int $id): void
    {
        $this->verifyCsrf();

        $port = $this->portModel->find($id);
        if (!$port) {
            $this->json(['error' => 'Port not found.'], 404);
        }

        $body     = $this->body();
        $deviceId = null;

        if (array_key_exists('device_id', $body) && $body['device_id'] !== null) {
            $deviceId = filter_var($body['device_id'], FILTER_VALIDATE_INT,
                                   ['options' => ['min_range' => 1]]);
            if ($deviceId === false) {
                $this->json(['error' => 'Invalid device ID.'], 422);
            }
        }

        $this->portModel->assign($id, $deviceId);
        $this->json($this->portModel->find($id));
    }

    // ── DELETE /api/ports/{id} ────────────────────────────────────────────
    public function deletePort(int $id): void
    {
        $this->verifyCsrf();

        $port = $this->portModel->find($id);
        if (!$port) {
            $this->json(['error' => 'Port not found.'], 404);
        }

        $this->portModel->delete($id);
        $this->json(['deleted' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /** @return array|string Validated data or error string. */
    private function validatePortData(array $body): array|string
    {
        $portNumber = filter_var($body['port_number'] ?? null, FILTER_VALIDATE_INT,
                                 ['options' => ['min_range' => 1]]);
        if ($portNumber === false) {
            return 'Port number must be a positive integer.';
        }

        $validTypes = ['rj45', 'sfp', 'sfp+', 'wan', 'mgmt'];
        $portType   = $body['port_type'] ?? '';
        if (!in_array($portType, $validTypes, true)) {
            return 'Invalid port type.';
        }

        $validStatuses = ['active', 'disabled', 'unknown'];
        $status        = $body['status'] ?? 'active';
        if (!in_array($status, $validStatuses, true)) {
            return 'Invalid status.';
        }

        $validSpeeds = ['10M', '100M', '1G', '2.5G', '5G', '10G'];
        $speed       = $body['speed'] ?? '1G';
        if (!in_array($speed, $validSpeeds, true)) {
            $speed = '1G';
        }

        $vlanId = null;
        if (!empty($body['vlan_id'])) {
            $vlanId = filter_var($body['vlan_id'], FILTER_VALIDATE_INT,
                                 ['options' => ['min_range' => 1, 'max_range' => 4094]]);
            if ($vlanId === false) {
                return 'VLAN ID must be between 1 and 4094.';
            }
        }

        $deviceId = null;
        if (!empty($body['device_id'])) {
            $deviceId = filter_var($body['device_id'], FILTER_VALIDATE_INT,
                                   ['options' => ['min_range' => 1]]);
            if ($deviceId === false) {
                return 'Invalid device.';
            }
        }

        $row = filter_var($body['port_row'] ?? 1, FILTER_VALIDATE_INT,
                          ['options' => ['min_range' => 1, 'max_range' => 20]]);
        $col = filter_var($body['port_col'] ?? 1, FILTER_VALIDATE_INT,
                          ['options' => ['min_range' => 1, 'max_range' => 50]]);
        if ($row === false) return 'Row must be between 1 and 20.';
        if ($col === false) return 'Column must be between 1 and 50.';

        return [
            'port_number' => $portNumber,
            'label'       => substr(trim((string)($body['label'] ?? '')), 0, 64),
            'port_type'   => $portType,
            'speed'       => $speed,
            'poe_enabled' => !empty($body['poe_enabled']),
            'vlan_id'     => $vlanId,
            'status'      => $status,
            'device_id'   => $deviceId,
            'notes'       => trim((string)($body['notes'] ?? '')),
            'port_row'    => $row,
            'port_col'    => $col,
        ];
    }

    private function body(): array
    {
        $raw  = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    private function verifyCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!Csrf::verify($token)) {
            $this->json(['error' => 'Invalid CSRF token.'], 403);
        }
    }

    /** Sends a JSON response and exits. */
    private function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }
}
