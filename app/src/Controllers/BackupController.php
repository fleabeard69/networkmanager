<?php
declare(strict_types=1);

class BackupController
{
    public function __construct(private Database $db) {}

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
        } catch (Throwable $e) {
            Session::flash('error', 'Import failed: ' . $e->getMessage());
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
            $deviceMap = []; // old id → new id
            foreach ($data['devices'] ?? [] as $d) {
                $this->db->execute(
                    'INSERT INTO devices
                         (hostname, mac_address, device_type, notes,
                          panel_rows, panel_rear_rows, panel_cols, sort_order)
                     VALUES (:h, :m, :t, :n, :r, :rr, :c, :s)',
                    [
                        ':h'  => $d['hostname'],
                        ':m'  => $d['mac_address'] ?: null,
                        ':t'  => $d['device_type']  ?? 'unknown',
                        ':n'  => $d['notes']         ?? '',
                        ':r'  => (int)($d['panel_rows']      ?? 2),
                        ':rr' => (int)($d['panel_rear_rows'] ?? 0),
                        ':c'  => (int)($d['panel_cols']      ?? 28),
                        ':s'  => (int)($d['sort_order']      ?? 0),
                    ]
                );
                $deviceMap[(int)$d['id']] = (int)$this->db->lastInsertId();
            }

            // ── IP assignments ────────────────────────────────────────────────
            foreach ($data['ip_assignments'] ?? [] as $ip) {
                $newDev = $deviceMap[(int)$ip['device_id']] ?? null;
                if (!$newDev) continue;
                $this->db->execute(
                    'INSERT INTO ip_assignments
                         (device_id, ip_address, subnet, gateway, interface, is_primary, notes)
                     VALUES (:d, :ip, :sn, :gw, :if, :pr, :n)',
                    [
                        ':d'  => $newDev,
                        ':ip' => $ip['ip_address'],
                        ':sn' => $ip['subnet']    ?: null,
                        ':gw' => $ip['gateway']   ?: null,
                        ':if' => $ip['interface'] ?? '',
                        ':pr' => $ip['is_primary'] ? 'true' : 'false',
                        ':n'  => $ip['notes'] ?? '',
                    ]
                );
            }

            // ── Service ports ─────────────────────────────────────────────────
            foreach ($data['service_ports'] ?? [] as $s) {
                $newDev = $deviceMap[(int)$s['device_id']] ?? null;
                if (!$newDev) continue;
                $this->db->execute(
                    'INSERT INTO service_ports
                         (device_id, protocol, port_number, service, description, is_external)
                     VALUES (:d, :pr, :pn, :sv, :de, :ex)',
                    [
                        ':d'  => $newDev,
                        ':pr' => $s['protocol']    ?? 'tcp',
                        ':pn' => (int)$s['port_number'],
                        ':sv' => $s['service']     ?? '',
                        ':de' => $s['description'] ?? '',
                        ':ex' => $s['is_external'] ? 'true' : 'false',
                    ]
                );
            }

            // ── Switch ports ──────────────────────────────────────────────────
            $portMap = []; // old id → new id
            foreach ($data['ports'] ?? [] as $p) {
                $newDev = isset($p['device_id']) ? ($deviceMap[(int)$p['device_id']] ?? null) : null;
                $this->db->execute(
                    'INSERT INTO switch_ports
                         (device_id, port_number, label, port_type, speed,
                          poe_enabled, vlan_id, status, notes, port_row, port_col)
                     VALUES (:d, :pn, :lb, :pt, :sp, :pe, :vl, :st, :nt, :pr, :pc)',
                    [
                        ':d'  => $newDev,
                        ':pn' => (int)$p['port_number'],
                        ':lb' => $p['label']    ?? '',
                        ':pt' => $p['port_type'] ?? 'rj45',
                        ':sp' => $p['speed']     ?? '1G',
                        ':pe' => $p['poe_enabled'] ? 'true' : 'false',
                        ':vl' => !empty($p['vlan_id']) ? (int)$p['vlan_id'] : null,
                        ':st' => $p['status']   ?? 'active',
                        ':nt' => $p['notes']    ?? '',
                        ':pr' => (int)($p['port_row'] ?? 1),
                        ':pc' => (int)($p['port_col'] ?? 1),
                    ]
                );
                $portMap[(int)$p['id']] = (int)$this->db->lastInsertId();
            }

            // ── Connections ───────────────────────────────────────────────────
            foreach ($data['connections'] ?? [] as $c) {
                $newA = $portMap[(int)$c['port_a']] ?? null;
                $newB = $portMap[(int)$c['port_b']] ?? null;
                if (!$newA || !$newB) continue;
                $this->db->execute(
                    'INSERT INTO port_connections (port_a, port_b, color) VALUES (:a, :b, :col)',
                    [':a' => $newA, ':b' => $newB, ':col' => $c['color'] ?? '#388bfd']
                );
            }

            $this->db->execute('COMMIT');

        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
    }
}
