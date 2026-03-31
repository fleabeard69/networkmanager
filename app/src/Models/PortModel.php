<?php
declare(strict_types=1);

class PortModel
{
    public function __construct(private Database $db) {}

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, d.hostname AS device_hostname, d.device_type AS device_type
             FROM switch_ports p
             LEFT JOIN devices d ON d.id = p.device_id
             ORDER BY p.port_row, p.port_col, p.port_number'
        );
    }

    public function find(int $id): array|false
    {
        return $this->db->fetchOne(
            'SELECT p.*, d.hostname AS device_hostname
             FROM switch_ports p
             LEFT JOIN devices d ON d.id = p.device_id
             WHERE p.id = :id',
            [':id' => $id]
        );
    }

    /**
     * @throws PDOException on constraint violations (e.g., duplicate port_number)
     */
    public function create(array $data): int
    {
        $stmt = $this->db->query(
            'INSERT INTO switch_ports
             (port_number, label, port_type, speed, poe_enabled, vlan_id, status, device_id, notes, port_row, port_col)
             VALUES (:num, :label, :type, :speed, :poe, :vlan, :status, :dev, :notes, :row, :col)
             RETURNING id',
            [
                ':num'    => $data['port_number'],
                ':label'  => $data['label'],
                ':type'   => $data['port_type'],
                ':speed'  => $data['speed'],
                ':poe'    => $data['poe_enabled'] ? 'true' : 'false',
                ':vlan'   => $data['vlan_id'],
                ':status' => $data['status'],
                ':dev'    => $data['device_id'],
                ':notes'  => $data['notes'],
                ':row'    => $data['port_row'],
                ':col'    => $data['port_col'],
            ]
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * @throws PDOException on constraint violations
     */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE switch_ports SET
             port_number = :num,
             label       = :label,
             port_type   = :type,
             speed       = :speed,
             poe_enabled = :poe,
             vlan_id     = :vlan,
             status      = :status,
             device_id   = :dev,
             notes       = :notes,
             port_row    = :row,
             port_col    = :col,
             updated_at  = NOW()
             WHERE id = :id',
            [
                ':num'    => $data['port_number'],
                ':label'  => $data['label'],
                ':type'   => $data['port_type'],
                ':speed'  => $data['speed'],
                ':poe'    => $data['poe_enabled'] ? 'true' : 'false',
                ':vlan'   => $data['vlan_id'],
                ':status' => $data['status'],
                ':dev'    => $data['device_id'],
                ':notes'  => $data['notes'],
                ':row'    => $data['port_row'],
                ':col'    => $data['port_col'],
                ':id'     => $id,
            ]
        );
    }

    public function allForDevice(int $deviceId): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, d.hostname AS device_hostname, d.device_type
             FROM switch_ports p
             LEFT JOIN devices d ON d.id = p.device_id
             WHERE p.device_id = :device_id
             ORDER BY p.port_row, p.port_col, p.port_number',
            [':device_id' => $deviceId]
        );
    }

    public function allUnassigned(): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM switch_ports WHERE device_id IS NULL ORDER BY port_number'
        );
    }

    /**
     * Assigns or unassigns a switch port to/from a device.
     * Pass null to unassign.
     */
    public function assign(int $id, ?int $deviceId): void
    {
        $this->db->execute(
            'UPDATE switch_ports SET device_id = :dev, updated_at = NOW() WHERE id = :id',
            [':dev' => $deviceId, ':id' => $id]
        );
    }

    public function move(int $id, int $row, int $col): void
    {
        $this->db->execute(
            'UPDATE switch_ports SET port_row = :row, port_col = :col, updated_at = NOW() WHERE id = :id',
            [':row' => $row, ':col' => $col, ':id' => $id]
        );
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM switch_ports WHERE id = :id', [':id' => $id]) > 0;
    }

    public function stats(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*)                                       AS total,
                COUNT(device_id)                              AS in_use,
                COUNT(*) FILTER (WHERE status = 'disabled')  AS disabled,
                COUNT(*) FILTER (WHERE port_type = 'wan')    AS wan
             FROM switch_ports"
        );
        return $row ?: ['total' => 0, 'in_use' => 0, 'disabled' => 0, 'wan' => 0];
    }
}
