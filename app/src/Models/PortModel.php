<?php
declare(strict_types=1);

class PortModel
{
    public function __construct(private Database $db) {}

    public function all(int $siteId): array
    {
        return $this->db->fetchAll(
            'SELECT p.*, d.hostname AS device_hostname, d.device_type AS device_type
             FROM switch_ports p
             LEFT JOIN devices d ON d.id = p.device_id
             WHERE p.device_id IS NULL OR d.site_id = :site_id
             ORDER BY p.port_row, p.port_col, p.port_number',
            [':site_id' => $siteId]
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
             (port_number, label, port_type, speed, poe_enabled, vlan_id, status, device_id, notes, port_row, port_col, client_label)
             VALUES (:num, :label, :type, :speed, :poe, :vlan, :status, :dev, :notes, :row, :col, :client)
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
                ':client' => $data['client_label'],
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
             port_number  = :num,
             label        = :label,
             port_type    = :type,
             speed        = :speed,
             poe_enabled  = :poe,
             vlan_id      = :vlan,
             status       = :status,
             device_id    = :dev,
             notes        = :notes,
             port_row     = :row,
             port_col     = :col,
             client_label = :client,
             updated_at   = NOW()
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
                ':client' => $data['client_label'],
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

    /**
     * Atomically swap the grid positions of two ports in one transaction.
     *
     * The unique constraint uq_switch_ports_position is deferred for the
     * duration of the transaction so both UPDATEs can execute before the
     * database validates the final state — at which point each port occupies
     * the other's former cell and there is no collision.
     *
     * @return array{port_a: array, port_b: array} Fresh port records after swap.
     * @throws \RuntimeException if either port does not exist.
     * @throws \PDOException     on any other database error.
     */
    public function swap(int $idA, int $idB): array
    {
        $portA = $this->find($idA);
        $portB = $this->find($idB);

        if (!$portA || !$portB) {
            throw new \RuntimeException('One or both ports not found.');
        }

        $this->db->beginTransaction();
        try {
            // Defer constraint to transaction end so the intermediate state
            // (both ports momentarily sharing one cell) doesn't trigger a violation.
            $this->db->execute('SET CONSTRAINTS uq_switch_ports_position DEFERRED');

            $this->db->execute(
                'UPDATE switch_ports SET port_row = :row, port_col = :col, updated_at = NOW() WHERE id = :id',
                [':row' => $portB['port_row'], ':col' => $portB['port_col'], ':id' => $idA]
            );
            $this->db->execute(
                'UPDATE switch_ports SET port_row = :row, port_col = :col, updated_at = NOW() WHERE id = :id',
                [':row' => $portA['port_row'], ':col' => $portA['port_col'], ':id' => $idB]
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'port_a' => $this->find($idA),
            'port_b' => $this->find($idB),
        ];
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM switch_ports WHERE id = :id', [':id' => $id]) > 0;
    }

    /**
     * Count ports belonging to a device whose row exceeds the total panel height.
     * Used to detect ports that would become unreachable if panel dimensions shrink.
     */
    public function countOutOfBounds(int $deviceId, int $totalRows): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM switch_ports WHERE device_id = :dev AND port_row > :total',
            [':dev' => $deviceId, ':total' => $totalRows]
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Delete ports belonging to a device whose row exceeds the total panel height.
     * Call only after the user has explicitly confirmed the deletion.
     */
    public function deleteOutOfBounds(int $deviceId, int $totalRows): void
    {
        $this->db->execute(
            'DELETE FROM switch_ports WHERE device_id = :dev AND port_row > :total',
            [':dev' => $deviceId, ':total' => $totalRows]
        );
    }

    public function stats(int $siteId): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(*)                                           AS total,
                COUNT(*) FILTER (WHERE sp.status != 'disabled')   AS in_use,
                COUNT(*) FILTER (WHERE sp.status = 'disabled')    AS disabled,
                COUNT(*) FILTER (WHERE sp.port_type = 'wan')      AS wan
             FROM switch_ports sp
             JOIN devices d ON d.id = sp.device_id
             WHERE d.site_id = :site_id",
            [':site_id' => $siteId]
        );
        return $row ?: ['total' => 0, 'in_use' => 0, 'disabled' => 0, 'wan' => 0];
    }
}
