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
        $settings = [];
        try {
            $rows = $this->db->fetchAll(
                "SELECT key, value FROM app_settings
                 WHERE key IN ('last_exported_at', 'last_imported_at')"
            );
            foreach ($rows as $row) {
                $settings[$row['key']] = $row['value'];
            }
        } catch (PDOException) {
            // app_settings table not yet migrated on this deployment — degrade gracefully
        }

        render('backup', [
            'navActive'      => 'backup',
            'title'          => 'Backup & Restore',
            'lastExportedAt' => $settings['last_exported_at'] ?? null,
            'lastImportedAt' => $settings['last_imported_at'] ?? null,
        ]);
    }

    // ── GET /backup/export ────────────────────────────────────────────────────
    public function export(): void
    {
        $sites = $this->db->fetchAll(
            'SELECT id, name, slug, description FROM sites ORDER BY id'
        );

        $devices = $this->db->fetchAll(
            'SELECT id, hostname, mac_address, device_type, notes,
                    panel_rows, panel_rear_rows, panel_cols, sort_order, site_id
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
            'SELECT id, port_a, port_b, color, anchor_a, anchor_b FROM port_connections ORDER BY id'
        );

        $ips = $this->db->fetchAll(
            'SELECT id, device_id,
                    host(ip_address)  AS ip_address,
                    subnet::text     AS subnet,
                    host(gateway)    AS gateway,
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
            'sites'          => $sites,
            'devices'        => $devices,
            'ports'          => $ports,
            'connections'    => $connections,
            'ip_assignments' => $ips,
            'service_ports'  => $services,
        ];

        // Record export timestamp before sending any output.
        // Silent on PDOException: export still works if table not yet migrated.
        try {
            $this->db->execute(
                "INSERT INTO app_settings (key, value) VALUES ('last_exported_at', :v)
                 ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value",
                [':v' => $backup['exported_at']]
            );
        } catch (PDOException) {}

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

        $tmpName = $_FILES['backup_file']['tmp_name'] ?? '';
        if (!is_uploaded_file($tmpName)) {
            Session::flash('error', 'No file uploaded or upload failed.');
            header('Location: /backup');
            exit;
        }

        $json = file_get_contents($tmpName);
        $data = $json !== false ? json_decode($json, true) : null;

        if (!is_array($data) || ($data['version'] ?? 0) !== 1) {
            Session::flash('error', 'Invalid backup file — must be a NetManager v1 export.');
            header('Location: /backup');
            exit;
        }

        // ── Rate limiting ─────────────────────────────────────────────────────
        // Two-layer check: session (fast, in-memory) + app_settings (persistent
        // across re-login and across browser sessions). Both enforce a 60-second
        // cooldown. The DB write happens before performImport() so even a failed
        // import counts against the limit.

        if (time() - Session::get('last_import_at', 0) < 60) {
            Session::flash('error', 'Please wait 60 seconds between restores.');
            header('Location: /backup');
            exit;
        }

        // Atomic DB claim: the upsert only writes (and RETURNING only returns a row)
        // if no import has been recorded in the last 60 seconds. Because the check
        // and the write happen in a single statement, there is no TOCTOU window —
        // Postgres row-level locking serializes concurrent attempts.
        // Silent on PDOException if app_settings not yet migrated.
        try {
            $claimed = $this->db->query(
                "INSERT INTO app_settings (key, value) VALUES ('last_imported_at', :v)
                 ON CONFLICT (key) DO UPDATE
                     SET value = EXCLUDED.value
                 WHERE app_settings.value IS NULL
                    OR (NOW() - app_settings.value::timestamptz) >= INTERVAL '60 seconds'
                 RETURNING key",
                [':v' => date('c')]
            )->fetch();

            if ($claimed === false) {
                Session::flash('error', 'Please wait 60 seconds between restores.');
                header('Location: /backup');
                exit;
            }
        } catch (PDOException) {}

        Session::set('last_import_at', time());

        try {
            $this->performImport($data);
            Session::forget('current_site_id');
            Session::forget('current_site_name');
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
            // Clear in dependency order (devices CASCADE to IPs + services)
            $this->db->execute('DELETE FROM port_connections');
            $this->db->execute('DELETE FROM switch_ports');
            $this->db->execute('DELETE FROM devices');

            // ── Sites ─────────────────────────────────────────────────────────
            // New backups include a 'sites' key; old backups do not.
            $siteMap = []; // old site id → new site id

            if (isset($data['sites'])) {
                if (empty($data['sites'])) {
                    throw new InvalidArgumentException('Backup contains an empty sites list — at least one site is required.');
                }

                // Full restore: wipe and re-create sites from the backup.
                $this->db->execute('DELETE FROM sites');

                foreach ($data['sites'] as $s) {
                    $siteName = is_string($s['name'] ?? null) ? trim($s['name']) : '';
                    if ($siteName === '') {
                        throw new InvalidArgumentException('A site is missing a valid name.');
                    }
                    $siteSlug = is_string($s['slug'] ?? null) ? trim($s['slug']) : '';
                    if ($siteSlug === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $siteSlug)) {
                        // Auto-generate slug from name if missing or invalid
                        $siteSlug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($siteName)), '-');
                    }
                    $stmt = $this->db->query(
                        'INSERT INTO sites (name, slug, description)
                         VALUES (:n, :sl, :d)
                         RETURNING id',
                        [
                            ':n'  => substr($siteName, 0, 128),
                            ':sl' => substr($siteSlug, 0, 64),
                            ':d'  => substr((string)($s['description'] ?? ''), 0, 1000),
                        ]
                    );
                    $siteMap[(int)$s['id']] = (int)$stmt->fetchColumn();
                }
            } else {
                // Old backup (no sites key): assign all devices to the first existing site.
                $existingSite = $this->db->fetchOne('SELECT id FROM sites ORDER BY id LIMIT 1');
                if (!$existingSite) {
                    $stmt = $this->db->query(
                        "INSERT INTO sites (name, slug) VALUES ('Default Site', 'default-site') RETURNING id"
                    );
                    $defaultSiteId = (int)$stmt->fetchColumn();
                } else {
                    $defaultSiteId = (int)$existingSite['id'];
                }
                // All old device IDs map to the same default site
                $siteMap = ['__default__' => $defaultSiteId];
            }

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

                // Resolve site_id: use siteMap for new backups, default site for old ones.
                if (isset($data['sites'])) {
                    $newSiteId = $siteMap[(int)($d['site_id'] ?? 0)] ?? null;
                    if ($newSiteId === null) {
                        throw new InvalidArgumentException(
                            "Device \"{$hostname}\" references an unknown site_id."
                        );
                    }
                } else {
                    $newSiteId = $siteMap['__default__'];
                }

                $stmt = $this->db->query(
                    'INSERT INTO devices
                         (hostname, mac_address, device_type, notes,
                          panel_rows, panel_rear_rows, panel_cols, sort_order, site_id)
                     VALUES (:h, :m, :t, :n, :r, :rr, :c, :s, :si)
                     RETURNING id',
                    [
                        ':h'  => $hostname,
                        ':m'  => $mac !== '' ? $mac : null,
                        ':t'  => $deviceType,
                        ':n'  => substr((string)($d['notes'] ?? ''), 0, 1000),
                        ':r'  => max(1, min(10, (int)($d['panel_rows']      ?? 2))),
                        ':rr' => max(0, min(10, (int)($d['panel_rear_rows'] ?? 0))),
                        ':c'  => max(1, min(50, (int)($d['panel_cols']      ?? 28))),
                        ':s'  => (int)($d['sort_order']      ?? 0),
                        ':si' => $newSiteId,
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
                // Strip CIDR prefix if present (old exports used inet::text which appends it, e.g. "192.168.0.1/32")
                if (str_contains($ipAddress, '/')) {
                    $ipAddress = explode('/', $ipAddress, 2)[0];
                }
                if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
                    throw new InvalidArgumentException(
                        "Invalid ip_address \"{$ipAddress}\" in an IP assignment."
                    );
                }

                $subnet = is_string($ip['subnet'] ?? null) ? trim($ip['subnet']) : '';
                if ($subnet !== '') {
                    $parts     = explode('/', $subnet, 2);
                    $maxPrefix = filter_var($parts[0] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 32 : 128;
                    if (count($parts) !== 2
                        || !filter_var($parts[0], FILTER_VALIDATE_IP)
                        || !ctype_digit($parts[1])
                        || (int)$parts[1] > $maxPrefix) {
                        throw new InvalidArgumentException(
                            "Invalid subnet \"{$subnet}\" in an IP assignment."
                        );
                    }
                }

                $gateway = is_string($ip['gateway'] ?? null) ? trim($ip['gateway']) : '';
                if ($gateway !== '' && str_contains($gateway, '/')) {
                    $gateway = explode('/', $gateway, 2)[0];
                }
                if ($gateway !== '' && !filter_var($gateway, FILTER_VALIDATE_IP)) {
                    throw new InvalidArgumentException(
                        "Invalid gateway \"{$gateway}\" in an IP assignment."
                    );
                }

                $this->db->execute(
                    'INSERT INTO ip_assignments
                         (device_id, ip_address, subnet, gateway, interface, is_primary, notes)
                     VALUES (:d, :ip, :sn, :gw, :if, :pr, :n)',
                    [
                        ':d'  => $newDev,
                        ':ip' => $ipAddress,
                        ':sn' => $subnet  !== '' ? $subnet  : null,
                        ':gw' => $gateway !== '' ? $gateway : null,
                        ':if' => substr((string)($ip['interface'] ?? ''), 0, 32),
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

                $servicePort = (int)($s['port_number'] ?? 0);
                if ($servicePort < 1 || $servicePort > 65535) {
                    throw new InvalidArgumentException(
                        "Invalid service port_number \"{$servicePort}\" — must be between 1 and 65535."
                    );
                }

                $this->db->execute(
                    'INSERT INTO service_ports
                         (device_id, protocol, port_number, service, description, is_external)
                     VALUES (:d, :pr, :pn, :sv, :de, :ex)',
                    [
                        ':d'  => $newDev,
                        ':pr' => $protocol,
                        ':pn' => $servicePort,
                        ':sv' => substr((string)($s['service'] ?? ''), 0, 64),
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

                $switchPortNumber = (int)($p['port_number'] ?? 0);
                if ($switchPortNumber < 1 || $switchPortNumber > 9999) {
                    throw new InvalidArgumentException(
                        "Invalid switch port_number \"{$switchPortNumber}\" — must be between 1 and 9999."
                    );
                }

                $vlanId = null;
                if (!empty($p['vlan_id'])) {
                    $vlanId = (int)$p['vlan_id'];
                    if ($vlanId < 1 || $vlanId > 4094) {
                        throw new InvalidArgumentException(
                            "Invalid vlan_id \"{$vlanId}\" — must be between 1 and 4094."
                        );
                    }
                }

                $stmt = $this->db->query(
                    'INSERT INTO switch_ports
                         (device_id, port_number, label, port_type, speed,
                          poe_enabled, vlan_id, status, notes, port_row, port_col)
                     VALUES (:d, :pn, :lb, :pt, :sp, :pe, :vl, :st, :nt, :pr, :pc)
                     RETURNING id',
                    [
                        ':d'  => $newDev,
                        ':pn' => $switchPortNumber,
                        ':lb' => substr((string)($p['label'] ?? ''), 0, 64),
                        ':pt' => $portType,
                        ':sp' => $speed,
                        ':pe' => $p['poe_enabled'] ? 'true' : 'false',
                        ':vl' => $vlanId,
                        ':st' => $status,
                        ':nt' => substr((string)($p['notes'] ?? ''), 0, 1000),
                        ':pr' => max(1, min(20, (int)($p['port_row'] ?? 1))),
                        ':pc' => max(1, min(50, (int)($p['port_col'] ?? 1))),
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

                $validAnchors = ['top', 'bottom', 'left', 'right'];
                $anchorA = in_array($c['anchor_a'] ?? null, $validAnchors, true) ? $c['anchor_a'] : null;
                $anchorB = in_array($c['anchor_b'] ?? null, $validAnchors, true) ? $c['anchor_b'] : null;

                $this->db->execute(
                    'INSERT INTO port_connections (port_a, port_b, color, anchor_a, anchor_b)
                     VALUES (:a, :b, :col, :aa, :ab)',
                    [':a' => $newA, ':b' => $newB, ':col' => $color, ':aa' => $anchorA, ':ab' => $anchorB]
                );
            }

            $this->db->execute('COMMIT');

        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
    }
}
