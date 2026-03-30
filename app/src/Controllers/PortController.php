<?php
declare(strict_types=1);

class PortController
{
    public function __construct(
        private PortModel $portModel,
        private DeviceModel $deviceModel
    ) {}

    public function index(): void
    {
        $ports = $this->portModel->all();
        render('ports', ['navActive' => 'ports', 'ports' => $ports]);
    }

    public function create(): void
    {
        $devices = $this->deviceModel->all();
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
            header('Location: /ports/new');
            exit;
        }

        try {
            $this->portModel->create($data);
            Session::flash('success', 'Port added successfully.');
            header('Location: /ports');
        } catch (PDOException $e) {
            Session::flash('error', $this->dbErrorMessage($e, 'unique', 'Port number already exists.'));
            header('Location: /ports/new');
        }
        exit;
    }

    public function edit(int $id): void
    {
        $port = $this->portModel->find($id);
        if (!$port) {
            $this->notFound('Port not found.');
        }
        $devices = $this->deviceModel->all();
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
        if (!$port) {
            $this->notFound('Port not found.');
        }

        $data = $this->validatePortData($_POST);
        if (is_string($data)) {
            Session::flash('error', $data);
            header("Location: /ports/{$id}/edit");
            exit;
        }

        try {
            $this->portModel->update($id, $data);
            Session::flash('success', 'Port updated.');
            header('Location: /ports');
        } catch (PDOException $e) {
            Session::flash('error', $this->dbErrorMessage($e, 'unique', 'Port number already exists.'));
            header("Location: /ports/{$id}/edit");
        }
        exit;
    }

    public function delete(int $id): void
    {
        $this->verifyCsrf();
        $this->portModel->delete($id);
        Session::flash('success', 'Port removed.');
        header('Location: /ports');
        exit;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /** @return array|string  Validated data array, or an error string. */
    private function validatePortData(array $post): array|string
    {
        $portNumber = filter_var(
            $post['port_number'] ?? '',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($portNumber === false) {
            return 'Port number must be a positive integer.';
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
            ['options' => ['min_range' => 1, 'max_range' => 10]]
        );
        if ($portRow === false) {
            return 'Row must be between 1 and 10.';
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
            'port_number' => $portNumber,
            'label'       => substr(trim($post['label'] ?? ''), 0, 64),
            'port_type'   => $portType,
            'speed'       => $speed,
            'poe_enabled' => !empty($post['poe_enabled']),
            'vlan_id'     => $vlanId,
            'status'      => $status,
            'device_id'   => $deviceId,
            'notes'       => trim($post['notes'] ?? ''),
            'port_row'    => $portRow,
            'port_col'    => $portCol,
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

    private function dbErrorMessage(PDOException $e, string $keyword, string $friendlyMsg): string
    {
        return str_contains(strtolower($e->getMessage()), $keyword)
            ? $friendlyMsg
            : 'A database error occurred. Please try again.';
    }
}
