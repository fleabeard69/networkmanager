<?php
declare(strict_types=1);

class PortController
{
    private int $siteId;

    public function __construct(
        private PortModel $portModel,
        private DeviceModel $deviceModel
    ) {
        Auth::requireLogin();
        $this->siteId = (int) Session::get('current_site_id');
    }

    public function panel(): void
    {
        render('panel_editor', ['navActive' => 'ports', 'navSub' => 'panel']);
    }

    public function index(): void
    {
        $ports   = $this->portModel->all($this->siteId);
        $devices = $this->deviceModel->all($this->siteId);
        render('ports', ['navActive' => 'ports', 'navSub' => 'list', 'ports' => $ports, 'devices' => $devices]);
    }

    public function create(): void
    {
        $devices = $this->deviceModel->all($this->siteId);
        render('port_form', [
            'navActive' => 'ports',
            'port'      => null,
            'devices'   => $devices,
        ]);
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $data = $this->validatePortData($_POST);
        if (is_string($data)) {
            Session::flash('error', $data);
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header('Location: /ports/new');
            exit;
        }

        if ($data['device_id'] !== null && !$this->deviceModel->find($data['device_id'], $this->siteId)) {
            Session::flash('error', 'Device not found.');
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header('Location: /ports/new');
            exit;
        }

        try {
            $this->portModel->create($data);
            Session::flash('success', 'Port added successfully.');
            header('Location: /ports');
        } catch (PDOException $e) {
            Session::flash('error', $this->dbErrorMessage($e, 'unique', 'Port number already exists on this device.'));
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header('Location: /ports/new');
        }
        exit;
    }

    public function edit(int $id): void
    {
        $port = $this->portModel->find($id);
        if (!$port || !$this->portBelongsToSite($port)) {
            $this->notFound('Port not found.');
        }
        $devices = $this->deviceModel->all($this->siteId);
        render('port_form', [
            'navActive' => 'ports',
            'port'      => $port,
            'devices'   => $devices,
        ]);
    }

    public function update(int $id): void
    {
        $this->verifyCsrf();

        $port = $this->portModel->find($id);
        if (!$port || !$this->portBelongsToSite($port)) {
            $this->notFound('Port not found.');
        }

        $data = $this->validatePortData($_POST);
        if (is_string($data)) {
            Session::flash('error', $data);
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header("Location: /ports/{$id}/edit");
            exit;
        }

        if ($data['device_id'] !== null && !$this->deviceModel->find($data['device_id'], $this->siteId)) {
            Session::flash('error', 'Device not found.');
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header("Location: /ports/{$id}/edit");
            exit;
        }

        try {
            $this->portModel->update($id, $data);
            Session::flash('success', 'Port updated.');
            header('Location: /ports');
        } catch (PDOException $e) {
            Session::flash('error', $this->dbErrorMessage($e, 'unique', 'Port number already exists on this device.'));
            Session::flashInput(array_diff_key($_POST, ['_csrf' => '']));
            header("Location: /ports/{$id}/edit");
        }
        exit;
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $port = $this->portModel->find($id);
        if (!$port || !$this->portBelongsToSite($port)) {
            $this->notFound('Port not found.');
        }
        $this->portModel->delete($id);
        Session::flash('success', 'Port removed.');
        header('Location: /ports');
        exit;
    }

    public function unassign(int $id): void
    {
        $this->verifyCsrf();

        $port = $this->portModel->find($id);
        if (!$port || !$this->portBelongsToSite($port)) {
            $this->notFound('Port not found.');
        }

        $deviceId = $port['device_id'];
        $this->portModel->assign($id, null);
        Session::flash('success', 'Port unassigned from device.');

        if ($deviceId && $this->deviceModel->find($deviceId, $this->siteId)) {
            header("Location: /devices/{$deviceId}#switch-ports");
        } else {
            header('Location: /ports');
        }
        exit;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /** @return array|string  Validated data array, or an error string. */
    private function validatePortData(array $post): array|string
    {
        $portNumber = filter_var(
            $post['port_number'] ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 9999]]
        );
        if ($portNumber === false) {
            return 'Port number must be between 1 and 9999.';
        }

        $validTypes = ['rj45', 'sfp', 'sfp+', 'wan', 'mgmt'];
        $portType   = $post['port_type'] ?? '';
        if (!in_array($portType, $validTypes, true)) {
            return 'Invalid port type selected.';
        }

        $validStatuses = ['active', 'disabled', 'unknown'];
        $status        = $post['status'] ?? '';
        if (!in_array($status, $validStatuses, true)) {
            return 'Invalid status selected.';
        }

        $validSpeeds = ['10M', '100M', '1G', '2.5G', '5G', '10G'];
        $speed       = $post['speed'] ?? '1G';
        if (!in_array($speed, $validSpeeds, true)) {
            $speed = '1G';
        }

        $vlanId = null;
        if (!empty($post['vlan_id'])) {
            $vlanId = filter_var(
                $post['vlan_id'],
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 4094]]
            );
            if ($vlanId === false) {
                return 'VLAN ID must be between 1 and 4094.';
            }
        }

        $deviceId = null;
        if (!empty($post['device_id'])) {
            $deviceId = filter_var($post['device_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($deviceId === false) {
                return 'Invalid device selection.';
            }
        }

        $portRow = filter_var(
            $post['port_row'] ?? 1,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 20]]
        );
        if ($portRow === false) {
            return 'Row must be between 1 and 20.';
        }

        $portCol = filter_var(
            $post['port_col'] ?? 1,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 50]]
        );
        if ($portCol === false) {
            return 'Column must be between 1 and 50.';
        }

        return [
            'port_number'  => $portNumber,
            'label'        => substr(trim($post['label'] ?? ''), 0, 64),
            'port_type'    => $portType,
            'speed'        => $speed,
            'poe_enabled'  => !empty($post['poe_enabled']),
            'vlan_id'      => $vlanId,
            'status'       => $status,
            'device_id'    => $deviceId,
            'notes'        => substr(trim($post['notes'] ?? ''), 0, 1000),
            'port_row'     => $portRow,
            'port_col'     => $portCol,
            'client_label' => substr(trim($post['client_label'] ?? ''), 0, 128),
        ];
    }

    private function portBelongsToSite(array $port): bool
    {
        return $port['device_id'] === null
            || (bool) $this->deviceModel->find((int) $port['device_id'], $this->siteId);
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

    private function dbErrorMessage(PDOException $e, string $keyword, string $friendlyMsg): string
    {
        return str_contains(strtolower($e->getMessage()), $keyword)
            ? $friendlyMsg
            : 'A database error occurred. Please try again.';
    }
}
