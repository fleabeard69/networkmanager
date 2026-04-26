<?php
declare(strict_types=1);

class DeviceModel
{
    public function __construct(private Database $db) {}

    public function all(int $siteId): array
    {
        return $this->db->fetchAll(
            "SELECT d.*,
                (SELECT COUNT(*) FROM switch_ports WHERE device_id = d.id)                   AS port_count,
                (SELECT ip_address::text FROM ip_assignments
                 WHERE device_id = d.id AND is_primary = TRUE LIMIT 1)                       AS primary_ip
             FROM devices d
             WHERE d.site_id = :site_id
             ORDER BY d.sort_order, d.hostname",
            [':site_id' => $siteId]
        );
    }

    public function reorder(array $orderedIds, int $siteId): void
    {
        $this->db->execute('BEGIN');
        try {
            foreach ($orderedIds as $i => $id) {
                $this->db->execute(
                    'UPDATE devices SET sort_order = :order WHERE id = :id AND site_id = :site_id',
                    [':order' => $i, ':id' => (int) $id, ':site_id' => $siteId]
                );
            }
            $this->db->execute('COMMIT');
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
    }

    public function find(int $id, ?int $siteId = null): array|false
    {
        if ($siteId !== null) {
            return $this->db->fetchOne(
                "SELECT d.*,
                    (SELECT COUNT(*) FROM switch_ports WHERE device_id = d.id)  AS port_count,
                    (SELECT ip_address::text FROM ip_assignments
                     WHERE device_id = d.id AND is_primary = TRUE LIMIT 1)      AS primary_ip
                 FROM devices d
                 WHERE d.id = :id AND d.site_id = :site_id",
                [':id' => $id, ':site_id' => $siteId]
            );
        }
        return $this->db->fetchOne(
            "SELECT d.*,
                (SELECT COUNT(*) FROM switch_ports WHERE device_id = d.id)  AS port_count,
                (SELECT ip_address::text FROM ip_assignments
                 WHERE device_id = d.id AND is_primary = TRUE LIMIT 1)      AS primary_ip
             FROM devices d
             WHERE d.id = :id",
            [':id' => $id]
        );
    }

    public function updatePanelDims(int $id, int $rows, int $rearRows, int $cols): void
    {
        $this->db->execute(
            'UPDATE devices SET panel_rows = :rows, panel_rear_rows = :rear, panel_cols = :cols, updated_at = NOW() WHERE id = :id',
            [':rows' => $rows, ':rear' => $rearRows, ':cols' => $cols, ':id' => $id]
        );
    }

    public function ips(int $deviceId): array
    {
        return $this->db->fetchAll(
            'SELECT *,
                    ip_address::text AS ip_str,
                    subnet::text     AS subnet_str,
                    gateway::text    AS gateway_str
             FROM ip_assignments
             WHERE device_id = :id
             ORDER BY is_primary DESC, id ASC',
            [':id' => $deviceId]
        );
    }

    public function services(int $deviceId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM service_ports
             WHERE device_id = :id
             ORDER BY port_number ASC',
            [':id' => $deviceId]
        );
    }

    /**
     * @throws PDOException on constraint violations
     */
    public function create(array $data, int $siteId): int
    {
        $stmt = $this->db->query(
            'INSERT INTO devices (hostname, mac_address, device_type, notes, panel_rear_rows, site_id)
             VALUES (:host, :mac, :type, :notes, :rear, :site_id)
             RETURNING id',
            [
                ':host'    => $data['hostname'],
                ':mac'     => $data['mac_address'],
                ':type'    => $data['device_type'],
                ':notes'   => $data['notes'],
                ':rear'    => $data['panel_rear_rows'] ?? 0,
                ':site_id' => $siteId,
            ]
        );
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE devices SET
             hostname        = :host,
             mac_address     = :mac,
             device_type     = :type,
             notes           = :notes,
             panel_rear_rows = :rear,
             updated_at      = NOW()
             WHERE id = :id',
            [
                ':host'  => $data['hostname'],
                ':mac'   => $data['mac_address'],
                ':type'  => $data['device_type'],
                ':notes' => $data['notes'],
                ':rear'  => $data['panel_rear_rows'] ?? 0,
                ':id'    => $id,
            ]
        );
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM devices WHERE id = :id', [':id' => $id]) > 0;
    }

    // ── IP Assignments ────────────────────────────────────────────────────

    /**
     * @throws PDOException if IP format invalid or primary constraint violated
     */
    public function addIp(int $deviceId, array $data): void
    {
        $this->db->execute(
            'INSERT INTO ip_assignments
             (device_id, ip_address, subnet, gateway, interface, is_primary, notes)
             VALUES (:dev, :ip, :subnet, :gw, :iface, :primary, :notes)',
            [
                ':dev'     => $deviceId,
                ':ip'      => $data['ip_address'],
                ':subnet'  => $data['subnet'],
                ':gw'      => $data['gateway'],
                ':iface'   => $data['interface'],
                ':primary' => $data['is_primary'] ? 'true' : 'false',
                ':notes'   => $data['notes'],
            ]
        );
    }

    /**
     * Clears any existing primary flag for the device, then marks the given IP
     * as primary — all inside a single transaction to satisfy the unique partial
     * index on (device_id) WHERE is_primary = TRUE.
     * Returns false if ipId does not exist or does not belong to deviceId.
     */
    public function setPrimaryIp(int $deviceId, int $ipId): bool
    {
        $this->db->execute('BEGIN');
        try {
            $this->db->execute(
                'UPDATE ip_assignments SET is_primary = FALSE WHERE device_id = :device_id AND is_primary = TRUE',
                [':device_id' => $deviceId]
            );
            $updated = $this->db->execute(
                'UPDATE ip_assignments SET is_primary = TRUE WHERE id = :id AND device_id = :device_id',
                [':id' => $ipId, ':device_id' => $deviceId]
            );
            if ($updated === 0) {
                $this->db->execute('ROLLBACK');
                return false;
            }
            $this->db->execute('COMMIT');
            return true;
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
    }

    /**
     * Deletes an IP assignment owned by the given device.
     * Returns true if a row was deleted, false if not found or device mismatch.
     */
    public function deleteIp(int $deviceId, int $ipId): bool
    {
        return $this->db->execute(
            'DELETE FROM ip_assignments WHERE id = :id AND device_id = :device_id',
            [':id' => $ipId, ':device_id' => $deviceId]
        ) > 0;
    }

    /**
     * Updates an IP assignment including is_primary.
     * When promoting to primary, clears any existing primary flag first — all in
     * a transaction to satisfy the unique partial index on (device_id) WHERE is_primary = TRUE.
     * Returns the updated row with text-cast inet/cidr columns, or false if not found.
     */
    public function updateIp(int $deviceId, int $ipId, array $data): array|false
    {
        $params = [
            ':ip'     => $data['ip_address'],
            ':subnet' => $data['subnet'],
            ':gw'     => $data['gateway'],
            ':iface'  => $data['interface'],
            ':notes'  => $data['notes'],
            ':id'     => $ipId,
            ':dev'    => $deviceId,
        ];

        $returning = 'RETURNING id, device_id,
                       ip_address::text AS ip_str,
                       subnet::text     AS subnet_str,
                       gateway::text    AS gateway_str,
                       interface, is_primary, notes';

        if ($data['is_primary']) {
            // Transaction: clear all primary flags for this device first, then update
            // this row with is_primary = TRUE — mirrors setPrimaryIp().
            $this->db->execute('BEGIN');
            try {
                $this->db->execute(
                    'UPDATE ip_assignments SET is_primary = FALSE WHERE device_id = :device_id AND is_primary = TRUE',
                    [':device_id' => $deviceId]
                );
                $row = $this->db->fetchOne(
                    "UPDATE ip_assignments SET
                         ip_address = :ip,
                         subnet     = :subnet,
                         gateway    = :gw,
                         interface  = :iface,
                         notes      = :notes,
                         is_primary = TRUE
                     WHERE id = :id AND device_id = :dev
                     {$returning}",
                    $params
                );
                if (!$row) {
                    $this->db->execute('ROLLBACK');
                    return false;
                }
                $this->db->execute('COMMIT');
                return $row;
            } catch (Throwable $e) {
                $this->db->execute('ROLLBACK');
                throw $e;
            }
        }

        return $this->db->fetchOne(
            "UPDATE ip_assignments SET
                 ip_address = :ip,
                 subnet     = :subnet,
                 gateway    = :gw,
                 interface  = :iface,
                 notes      = :notes,
                 is_primary = FALSE
             WHERE id = :id AND device_id = :dev
             {$returning}",
            $params
        );
    }

    // ── Service Ports ─────────────────────────────────────────────────────

    /**
     * Upserts a service port (updates if same device+protocol+port_number exists).
     * @throws PDOException
     */
    public function addService(int $deviceId, array $data): void
    {
        $this->db->execute(
            'INSERT INTO service_ports (device_id, protocol, port_number, service, description, is_external)
             VALUES (:dev, :proto, :port, :svc, :desc, :ext)
             ON CONFLICT (device_id, protocol, port_number) DO UPDATE SET
                 service     = EXCLUDED.service,
                 description = EXCLUDED.description,
                 is_external = EXCLUDED.is_external',
            [
                ':dev'   => $deviceId,
                ':proto' => $data['protocol'],
                ':port'  => $data['port_number'],
                ':svc'   => $data['service'],
                ':desc'  => $data['description'],
                ':ext'   => $data['is_external'] ? 'true' : 'false',
            ]
        );
    }

    /**
     * Deletes a service port owned by the given device.
     * Returns true if a row was deleted, false if not found or device mismatch.
     */
    public function deleteService(int $deviceId, int $serviceId): bool
    {
        return $this->db->execute(
            'DELETE FROM service_ports WHERE id = :id AND device_id = :device_id',
            [':id' => $serviceId, ':device_id' => $deviceId]
        ) > 0;
    }

    /**
     * Updates a service port owned by the given device.
     * Returns the updated row, or false if not found.
     * @throws PDOException if the new protocol+port_number conflicts with another row.
     */
    public function updateService(int $deviceId, int $serviceId, array $data): array|false
    {
        return $this->db->fetchOne(
            'UPDATE service_ports SET
                 protocol    = :proto,
                 port_number = :port,
                 service     = :svc,
                 description = :desc,
                 is_external = :ext
             WHERE id = :id AND device_id = :dev
             RETURNING *',
            [
                ':proto' => $data['protocol'],
                ':port'  => $data['port_number'],
                ':svc'   => $data['service'],
                ':desc'  => $data['description'],
                ':ext'   => $data['is_external'] ? 'true' : 'false',
                ':id'    => $serviceId,
                ':dev'   => $deviceId,
            ]
        );
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    public function count(int $siteId): int
    {
        return (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM devices WHERE site_id = :site_id',
            [':site_id' => $siteId]
        )['c'] ?? 0);
    }

    public function ipCount(int $siteId): int
    {
        return (int) ($this->db->fetchOne(
            'SELECT COUNT(*) AS c FROM ip_assignments
             WHERE device_id IN (SELECT id FROM devices WHERE site_id = :site_id)',
            [':site_id' => $siteId]
        )['c'] ?? 0);
    }
}
