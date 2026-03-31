<?php
declare(strict_types=1);

class DeviceController
{
    public function __construct(
        private DeviceModel $deviceModel,
        private PortModel $portModel
    ) {
        Auth::requireLogin();
    }

    public function index(): void
    {
        $devices = $this->deviceModel->all();
        render('devices', ['navActive' => 'devices', 'devices' => $devices]);
    }

    public function create(): void
    {
        render('device_form', [
            'navActive' => 'devices',
            'device'    => null,
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $data = $this->validateDeviceData($_POST);
        if (is_string($data)) {
            Session::flash('error', $data);
            header('Location: /devices/new');
            exit;
        }

        try {
            $id = $this->deviceModel->create($data);
            Session::flash('success', 'Device added successfully.');
            header("Location: /devices/{$id}");
        } catch (PDOException) {
            Session::flash('error', 'A database error occurred. Please try again.');
            header('Location: /devices/new');
        }
        exit;
    }

    public function show(int $id): void
    {
        $device = $this->deviceModel->find($id);
        if (!$device) {
            $this->notFound('Device not found.');
        }

        $ips             = $this->deviceModel->ips($id);
        $services        = $this->deviceModel->services($id);
        $switchPorts     = $this->portModel->allForDevice($id);
        $unassignedPorts = $this->portModel->allUnassigned();

        render('device_detail', [
            'navActive'       => 'devices',
            'device'          => $device,
            'ips'             => $ips,
            'services'        => $services,
            'switchPorts'     => $switchPorts,
            'unassignedPorts' => $unassignedPorts,
        ]);
    }

    public function edit(int $id): void
    {
        $device = $this->deviceModel->find($id);
        if (!$device) {
            $this->notFound('Device not found.');
        }

        render('device_form', [
            'navActive' => 'devices',
            'device'    => $device,
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();

        $device = $this->deviceModel->find($id);
        if (!$device) {
            $this->notFound('Device not found.');
        }

        $data = $this->validateDeviceData($_POST);
        if (is_string($data)) {
            Session::flash('error', $data);
            header("Location: /devices/{$id}/edit");
            exit;
        }

        try {
            $this->deviceModel->update($id, $data);
            Session::flash('success', 'Device updated.');
            header("Location: /devices/{$id}");
        } catch (PDOException) {
            Session::flash('error', 'A database error occurred. Please try again.');
            header("Location: /devices/{$id}/edit");
        }
        exit;
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        if (!$this->deviceModel->delete($id)) {
            $this->notFound('Device not found.');
        }
        Session::flash('success', 'Device removed.');
        header('Location: /devices');
        exit;
    }

    // ── Device Port Panel ─────────────────────────────────────────────────

    public function portPanel(int $id): void
    {
        $device = $this->deviceModel->find($id);
        if (!$device) {
            $this->notFound('Device not found.');
        }

        render('device_port_panel', [
            'navActive' => 'devices',
            'title'     => h($device['hostname']) . ' — Switch Ports',
            'device'    => $device,
        ]);
    }

    public function assignPortForm(int $deviceId): void
    {
        $this->verifyCsrf();

        $device = $this->deviceModel->find($deviceId);
        if (!$device) {
            $this->notFound('Device not found.');
        }

        $portId = filter_var(
            $_POST['port_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($portId === false) {
            Session::flash('error', 'Please select a port to assign.');
            header("Location: /devices/{$deviceId}#switch-ports");
            exit;
        }

        $port = $this->portModel->find($portId);
        if (!$port) {
            Session::flash('error', 'Port not found.');
            header("Location: /devices/{$deviceId}#switch-ports");
            exit;
        }
        if ($port['device_id'] !== null) {
            Session::flash('error', 'That port is already assigned to a device.');
            header("Location: /devices/{$deviceId}#switch-ports");
            exit;
        }

        $this->portModel->assign($portId, $deviceId);
        Session::flash('success', 'Port assigned to device.');
        header("Location: /devices/{$deviceId}#switch-ports");
        exit;
    }

    // ── IP Assignments ────────────────────────────────────────────────────

    public function addIp(int $deviceId): void
    {
        $this->verifyCsrf();

        $device = $this->deviceModel->find($deviceId);
        if (!$device) {
            $this->notFound('Device not found.');
        }

        $data = $this->validateIpData($_POST);
        if (is_string($data)) {
            Session::flash('error', $data);
            header("Location: /devices/{$deviceId}#ips");
            exit;
        }

        try {
            $this->deviceModel->addIp($deviceId, $data);
            Session::flash('success', 'IP address added.');
        } catch (PDOException $e) {
            $msg = str_contains(strtolower($e->getMessage()), 'unique')
                ? 'This device already has a primary IP. Remove it first.'
                : 'Invalid IP address or subnet format.';
            Session::flash('error', $msg);
        }

        header("Location: /devices/{$deviceId}#ips");
        exit;
    }

    public function deleteIp(int $deviceId, int $ipId): void
    {
        $this->verifyCsrf();
        if (!$this->deviceModel->deleteIp($deviceId, $ipId)) {
            $this->notFound('IP address not found.');
        }
        Session::flash('success', 'IP address removed.');
        header("Location: /devices/{$deviceId}#ips");
        exit;
    }

    // ── Service Ports ─────────────────────────────────────────────────────

    public function addService(int $deviceId): void
    {
        $this->verifyCsrf();

        $device = $this->deviceModel->find($deviceId);
        if (!$device) {
            $this->notFound('Device not found.');
        }

        $data = $this->validateServiceData($_POST);
        if (is_string($data)) {
            Session::flash('error', $data);
            header("Location: /devices/{$deviceId}#services");
            exit;
        }

        try {
            $this->deviceModel->addService($deviceId, $data);
            Session::flash('success', 'Service port saved.');
        } catch (PDOException) {
            Session::flash('error', 'A database error occurred. Please try again.');
        }

        header("Location: /devices/{$deviceId}#services");
        exit;
    }

    public function deleteService(int $deviceId, int $serviceId): void
    {
        $this->verifyCsrf();
        if (!$this->deviceModel->deleteService($deviceId, $serviceId)) {
            $this->notFound('Service port not found.');
        }
        Session::flash('success', 'Service port removed.');
        header("Location: /devices/{$deviceId}#services");
        exit;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /** @return array|string Validated data array, or an error string. */
    private function validateDeviceData(array $post): array|string
    {
        $hostname = trim($post['hostname'] ?? '');
        if ($hostname === '') {
            return 'Hostname is required.';
        }
        if (strlen($hostname) > 128) {
            return 'Hostname must be 128 characters or fewer.';
        }

        $mac = strtoupper(trim($post['mac_address'] ?? ''));
        if ($mac !== '' && !preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            return 'Invalid MAC address format. Expected: AA:BB:CC:DD:EE:FF';
        }

        $validTypes = [
            'server', 'workstation', 'laptop', 'router', 'switch',
            'access-point', 'nas', 'iot', 'printer', 'camera',
            'phone', 'tv', 'game-console', 'other', 'unknown',
        ];
        $deviceType = $post['device_type'] ?? 'unknown';
        if (!in_array($deviceType, $validTypes, true)) {
            $deviceType = 'unknown';
        }

        $rearRows = filter_var($post['panel_rear_rows'] ?? 0, FILTER_VALIDATE_INT,
                               ['options' => ['min_range' => 0, 'max_range' => 10]]);

        return [
            'hostname'        => $hostname,
            'mac_address'     => $mac !== '' ? $mac : null,
            'device_type'     => $deviceType,
            'notes'           => substr(trim($post['notes'] ?? ''), 0, 1000),
            'panel_rear_rows' => $rearRows !== false ? $rearRows : 0,
        ];
    }

    /** @return array|string Validated data array, or an error string. */
    private function validateIpData(array $post): array|string
    {
        $ip = trim($post['ip_address'] ?? '');
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return 'Invalid IP address format.';
        }

        $subnet = trim($post['subnet'] ?? '');
        if ($subnet !== '' && !preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $subnet)) {
            return 'Invalid subnet format. Expected: 192.168.1.0/24';
        }

        $gateway = trim($post['gateway'] ?? '');
        if ($gateway !== '' && !filter_var($gateway, FILTER_VALIDATE_IP)) {
            return 'Invalid gateway IP address format.';
        }

        return [
            'ip_address' => $ip,
            'subnet'     => $subnet !== '' ? $subnet : null,
            'gateway'    => $gateway !== '' ? $gateway : null,
            'interface'  => substr(trim($post['interface'] ?? ''), 0, 32),
            'is_primary' => !empty($post['is_primary']),
            'notes'      => substr(trim($post['notes'] ?? ''), 0, 1000),
        ];
    }

    /** @return array|string Validated data array, or an error string. */
    private function validateServiceData(array $post): array|string
    {
        $validProtocols = ['tcp', 'udp', 'both'];
        $protocol       = $post['protocol'] ?? 'tcp';
        if (!in_array($protocol, $validProtocols, true)) {
            return 'Invalid protocol. Choose tcp, udp, or both.';
        }

        $portNum = filter_var(
            $post['port_number'] ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]]
        );
        if ($portNum === false) {
            return 'Port number must be between 1 and 65535.';
        }

        return [
            'protocol'    => $protocol,
            'port_number' => $portNum,
            'service'     => substr(trim($post['service'] ?? ''), 0, 64),
            'description' => substr(trim($post['description'] ?? ''), 0, 1000),
            'is_external' => !empty($post['is_external']),
        ];
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(403);
            exit('Invalid CSRF token.');
        }
    }

    private function notFound(string $message): never
    {
        http_response_code(404);
        exit(htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
