<?php
declare(strict_types=1);

class DeviceModel
{
    public function __construct(private Database $db) {}

    public function all(): array
    {
        return $this->db->fetchAll(
            "SELECT d.*,
                (SELECT COUNT(*) FROM switch_ports WHERE device_id = d.id)                   AS port_count,
                (SELECT ip_address::text FROM ip_assignments
                 WHERE device_id = d.id AND is_primary = TRUE LIMIT 1)                       AS primary_ip
             FROM devices d
             ORDER BY d.sort_order, d.hostname"
        );
    }

    public function reorder(array $orderedIds): void
    {
        $this->db->execute('BEGIN');
        try {
            foreach ($orderedIds as $i => $id) {
                $this->db->execute(
                    'UPDATE devices SET sort_order = :order WHERE id = :id',
                    [':order' => $i, ':id' => (int) $id]
                );
            }
            $this->db->execute('COMMIT');
        } catch (Throwable $e) {
            $this->db->execute('ROLLBACK');
            throw $e;
        }
    }

    public function find(int $id): array|false
    {
        return $this->db->fetchOne(
            'SELECT d.*,
                (SELECT COUNT(*) FROM switch_ports WHERE device_id = d.id) AS port_count
             FROM devices d
             WHERE d.id = :id',
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
    public function create(array $data): int
    {
        $this->db->execute(
            'INSERT INTO devices (hostname, mac_address, device_type, notes, panel_rear_rows)
             VALUES (:host, :mac, :type, :notes, :rear)',
            [
                ':host'  => $data['hostname'],
                ':mac'   => $data['mac_address'],
                ':type'  => $data['device_type'],
                ':notes' => $data['notes'],
                ':rear'  => $data['panel_rear_rows'] ?? 0,
            ]
        );
        return (int) $this->db->lastInsertId();
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

    // ── Stats ─────────────────────────────────────────────────────────────

    public function count(): int
    {
        return (int) ($this->db->fetchOne('SELECT COUNT(*) AS c FROM devices')['c'] ?? 0);
    }

    public function ipCount(): int
    {
        return (int) ($this->db->fetchOne('SELECT COUNT(*) AS c FROM ip_assignments')['c'] ?? 0);
    }
}
