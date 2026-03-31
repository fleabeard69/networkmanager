<?php
declare(strict_types=1);

class BackupController
{
    public function __construct(private Database $db)
    {
        Auth::requireLogin();
    }

    // ── GET /backup ───────────────────────────────────────────────────────────
    public function show(): void
    {
        render('backup', ['navActive' => 'backup', 'title' => 'Backup & Restore']);
    }

    // ── GET /backup/export ────────────────────────────────────────────────────
    public function export(): void
    {
        $devices = $this->db->fetchAll(
            'SELECT id, hostname, mac_address, device_type, notes,
                    panel_rows, panel_rear_rows, panel_cols, sort_order
             FROM devices ORDER BY sort_order, id'
        );

        $ports = $this->db->fetchAll(
            'SELECT id, device_id, port_number, label, port_type, speed,
                    poe_enabled, vlan_id, status, notes, port_row, port_col
             FROM switch_ports ORDER BY id'
        );

        foreach ($ports as &$p) {
            $p['poe_enabled'] = $p['poe_enabled'] === 't' || $p['poe_enabled'] === true;
            $p['vlan_id']     = $p['vlan_id'] !== null ? (int) $p['vlan_id'] : null;
            $p['device_id']   = $p['device_id'] !== null ? (int) $p['device_id'] : null;
        }
        unset($p);

        $connections = $this->db->fetchAll(
            'SELECT id, port_a, port_b, color FROM port_connections ORDER BY id'
        );

        $ips = $this->db->fetchAll(
            'SELECT id, device_id,
                    ip_address::text AS ip_address,
                    subnet::text     AS subnet,
                    gateway::text    AS gateway,
                    interface, is_primary, notes
             FROM ip_assignments ORDER BY device_id, id'
        );

        foreach ($ips as &$ip) {
            $ip['is_primary'] = $ip['is_primary'] === 't' || $ip['is_primary'] === true;
        }
        unset($ip);

        $services = $this->db->fetchAll(
            'SELECT id, device_id, protocol, port_number, service, description, is_external
             FROM service_ports ORDER BY device_id, port_number'
        );

        foreach ($services as &$s) {
            $s['is_external']  = $s['is_external'] === 't' || $s['is_external'] === true;
            $s['port_number']  = (int) $s['port_number'];
        }
        unset($s);

        $backup = [
            'version'        => 1,
            'exported_at'    => date('c'),
            'devices'        => $devices,
            'ports'          => $ports,
            'connections'    => $connections,
            'ip_assignments' => $ips,
            'service_ports'  => $services,
        ];

        $filename = 'netmanager-backup-' . date('Y-m-d') . '.json';
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ── POST /backup/import ───────────────────────────────────────────────────
    public function import(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            Session::flash('error', 'Invalid security token.');
            header('Location: /backup');
            exit;
        }

        $fileError = $_FILES['backup_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($fileError !== UPLOAD_ERR_OK) {
            Session::flash('error', 'No file uploaded or upload failed.');
            header('Location: /backup');
            exit;
        }

        if (($_FILES['backup_file']['size'] ?? 0) > 5 * 1024 * 1024) {
            Session::flash('error', 'Backup file is too large (5 MB maximum).');
            header('Location: /backup');
            exit;
        }

        $json = file_get_contents($_FILES['backup_file']['tmp_name']);
        $data = $json !== false ? json_decode($json, true) : null;

        if (!is_array($data) || ($data['version'] ?? 0) !== 1) {
            Session::flash('error', 'Invalid backup file — must be a NetManager v1 export.');
            header('Location: /backup');
            exit;
        }

        try {
            $this->performImport($data);
            $dc = count($data['devices']     ?? []);
            $pc = count($data['ports']        ?? []);
            $cc = count($data['connections']  ?? []);
            Session::flash('success',
                "Restore complete: {$dc} device(s), {$pc} port(s), {$cc} connection(s) imported.");
        } catch (InvalidArgumentException $e) {
            Session::flash('error', 'Import failed: ' . $e->getMessage());
        } catch (Throwable $e) {
            Session::flash('error', 'Import failed: a database error occurred. Please check the backup file.');
        }

        header('Location: /backup');
        exit;
    }

    // ── Import logic (wrapped in a transaction) ───────────────────────────────
    private function performImport(array $data): void
    {
        $this->db->execute('BEGIN');

        try {
            // Clear in dependency order
            $this->db->execute('DELETE FROM port_connections');
            $this->db->execute('DELETE FROM switch_ports');
            $this->db->execute('DELETE FROM devices'); // cascades IPs + services

            // ── Devices ───────────────────────────────────────────────────────
            $validDeviceTypes = [
                'server', 'workstation', 'laptop', 'router', 'switch',
                'access-point', 'nas', 'iot', 'printer', 'camera',
                'phone', 'tv', 'game-console', 'other', 'unknown',
            ];

            $deviceMap = []; // old id → new id
            foreach ($data['devices'] ?? [] as $d) {
                $hostname = is_string($d['hostname'] ?? null) ? trim($d['hostname']) : '';
                if ($hostname === '') {
                    throw new InvalidArgumentException('A device is missing a valid hostname.');
                }
                if (strlen($hostname) > 128) {
                    throw new InvalidArgumentException(
                        "Device hostname \"{$hostname}\" exceeds 128 characters."
                    );
                }

                $mac = strtoupper(trim((string)($d['mac_address'] ?? '')));
                if ($mac !== '' && !preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
                    throw new InvalidArgumentException(
                        "Invalid MAC address \"{$mac}\" for device \"{$hostname}\"."
                    );
                }

                $deviceType = $d['device_type'] ?? 'unknown';
                if (!in_array($deviceType, $validDeviceTypes, true)) {
                    $deviceType = 'unknown';
                }

                $stmt = $this->db->query(
                    'INSERT INTO devices
                         (hostname, mac_address, device_type, notes,
                          panel_rows, panel_rear_rows, panel_cols, sort_order)
                     VALUES (:h, :m, :t, :n, :r, :rr, :c, :s)
                     RETURNING id',
                    [
                        ':h'  => $hostname,
                        ':m'  => $mac !== '' ? $mac : null,
                        ':t'  => $deviceType,
                        ':n'  => substr((string)($d['notes'] ?? ''), 0, 1000),
                        ':r'  => (int)($d['panel_rows']      ?? 2),
                        ':rr' => (int)($d['panel_rear_rows'] ?? 0),
                        ':c'  => (int)($d['panel_cols']      ?? 28),
                        ':s'  => (int)($d['sort_order']      ?? 0),
                    ]
                );
                $deviceMap[(int)$d['id']] = (int)$stmt->fetchColumn();
            }

            // ── IP assignments ────────────────────────────────────────────────
            foreach ($data['ip_assignments'] ?? [] as $ip) {
                $newDev = $deviceMap[(int)$ip['device_id']] ?? null;
                if (!$newDev) continue;

                $ipAddress = is_string($ip['ip_address'] ?? null) ? trim($ip['ip_address']) : '';
                if ($ipAddress === '') {
                    throw new InvalidArgumentException('An IP assignment is missing a valid ip_address.');
                }

                $this->db->execute(
                    'INSERT INTO ip_assignments
                         (device_id, ip_address, subnet, gateway, interface, is_primary, notes)
                     VALUES (:d, :ip, :sn, :gw, :if, :pr, :n)',
                    [
                        ':d'  => $newDev,
                        ':ip' => $ipAddress,
                        ':sn' => $ip['subnet']  ?: null,
                        ':gw' => $ip['gateway'] ?: null,
                        ':if' => (string)($ip['interface'] ?? ''),
                        ':pr' => $ip['is_primary'] ? 'true' : 'false',
                        ':n'  => substr((string)($ip['notes'] ?? ''), 0, 1000),
                    ]
                );
            }

            // ── Service ports ─────────────────────────────────────────────────
            $validProtocols = ['tcp', 'udp', 'both'];

            foreach ($data['service_ports'] ?? [] as $s) {
                $newDev = $deviceMap[(int)$s['device_id']] ?? null;
                if (!$newDev) continue;

                $protocol = $s['protocol'] ?? 'tcp';
                if (!in_array($protocol, $validProtocols, true)) {
                    $protocol = 'tcp';
                }

                $this->db->execute(
                    'INSERT INTO service_ports
                         (device_id, protocol, port_number, service, description, is_external)
                     VALUES (:d, :pr, :pn, :sv, :de, :ex)',
                    [
                        ':d'  => $newDev,
                        ':pr' => $protocol,
                        ':pn' => (int)$s['port_number'],
                        ':sv' => (string)($s['service']     ?? ''),
                        ':de' => substr((string)($s['description'] ?? ''), 0, 1000),
                        ':ex' => $s['is_external'] ? 'true' : 'false',
                    ]
                );
            }

            // ── Switch ports ──────────────────────────────────────────────────
            $validPortTypes = ['rj45', 'sfp', 'sfp+', 'wan', 'mgmt'];
            $validStatuses  = ['active', 'disabled', 'unknown'];
            $validSpeeds    = ['10M', '100M', '1G', '2.5G', '5G', '10G'];

            $portMap = []; // old id → new id
            foreach ($data['ports'] ?? [] as $p) {
                $newDev = isset($p['device_id']) ? ($deviceMap[(int)$p['device_id']] ?? null) : null;

                $portType = $p['port_type'] ?? 'rj45';
                if (!in_array($portType, $validPortTypes, true)) {
                    $portType = 'rj45';
                }

                $status = $p['status'] ?? 'active';
                if (!in_array($status, $validStatuses, true)) {
                    $status = 'active';
                }

                $speed = $p['speed'] ?? '1G';
                if (!in_array($speed, $validSpeeds, true)) {
                    $speed = '1G';
                }

                $stmt = $this->db->query(
                    'INSERT INTO switch_ports
                         (device_id, port_number, label, port_type, speed,
                          poe_enabled, vlan_id, status, notes, port_row, port_col)
                     VALUES (:d, :pn, :lb, :pt, :sp, :pe, :vl, :st, :nt, :pr, :pc)
                     RETURNING id',
                    [
                        ':d'  => $newDev,
                        ':pn' => (int)$p['port_number'],
                        ':lb' => (string)($p['label'] ?? ''),
                        ':pt' => $portType,
                        ':sp' => $speed,
                        ':pe' => $p['poe_enabled'] ? 'true' : 'false',
                        ':vl' => !empty($p['vlan_id']) ? (int)$p['vlan_id'] : null,
                        ':st' => $status,
                        ':nt' => substr((string)($p['notes'] ?? ''), 0, 1000),
                        ':pr' => (int)($p['port_row'] ?? 1),
                        ':pc' => (int)($p['port_col'] ?? 1),
                    ]
                );
                $portMap[(int)$p['id']] = (int)$stmt->fetchColumn();
            }

            // ── Connections ───────────────────────────────────────────────────
            $validColors = [
                '#388bfd', '#2ea043', '#d29922', '#da3633', '#bc8cff', '#ff7b72',
                '#ffa657', '#39d353', '#79c0ff', '#d2a8ff', '#e3b341', '#f08bb4',
                '#58a6ff', '#7ee787', '#c9d1d9', '#8b949e',
            ];

            foreach ($data['connections'] ?? [] as $c) {
                $newA = $portMap[(int)$c['port_a']] ?? null;
                $newB = $portMap[(int)$c['port_b']] ?? null;
                if (!$newA || !$newB) continue;

                $color = $c['color'] ?? '#388bfd';
                if (!in_array($color, $validColors, true)) {
                    $color = '#388bfd';
                }

                $this->db->execute(
                    'INSERT INTO port_connections (port_a, port_b, color) VALUES (:a, :b, :col)',
                    [':a' => $newA, ':b' => $newB, ':col' => $color]
                );
            }

            $this->db->execute('COMMIT');

        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
    }
}
