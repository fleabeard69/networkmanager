<?php
declare(strict_types=1);

class ConnectionModel
{
    private const VALID_COLORS = [
        '#388bfd', '#2ea043', '#d29922', '#da3633', '#bc8cff', '#ff7b72',
        '#ffa657', '#39d353', '#79c0ff', '#d2a8ff', '#e3b341', '#f08bb4',
        '#58a6ff', '#7ee787', '#c9d1d9', '#8b949e',
    ];

    public function __construct(private Database $db) {}

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT pc.*,
                    pa.port_number AS port_a_number, pa.label AS port_a_label,
                    pa.device_id   AS port_a_device_id,
                    pb.port_number AS port_b_number, pb.label AS port_b_label,
                    pb.device_id   AS port_b_device_id
             FROM port_connections pc
             JOIN switch_ports pa ON pa.id = pc.port_a
             JOIN switch_ports pb ON pb.id = pc.port_b'
        );
    }

    public function find(int $id): array|false
    {
        return $this->db->fetchOne(
            'SELECT pc.*,
                    pa.port_number AS port_a_number, pa.label AS port_a_label,
                    pb.port_number AS port_b_number, pb.label AS port_b_label
             FROM port_connections pc
             JOIN switch_ports pa ON pa.id = pc.port_a
             JOIN switch_ports pb ON pb.id = pc.port_b
             WHERE pc.id = :id',
            [':id' => $id]
        );
    }

    /**
     * Returns the set of port IDs that already have a connection.
     * Used by the API and JS to pre-flag occupied ports.
     */
    public function occupiedPortIds(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT port_a AS id FROM port_connections
             UNION
             SELECT port_b AS id FROM port_connections'
        );
        return array_column($rows, 'id');
    }

    /**
     * @throws RuntimeException if either port is already connected
     * @throws PDOException on duplicate connection (race condition fallback)
     */
    public function create(int $portA, int $portB, string $color = '#388bfd'): int
    {
        if (!in_array($color, self::VALID_COLORS, true)) {
            $color = '#388bfd';
        }

        // Application-level check for a friendly error message
        $row = $this->db->fetchOne(
            'SELECT id FROM port_connections
             WHERE port_a = :a OR port_b = :a OR port_a = :b OR port_b = :b
             LIMIT 1',
            [':a' => $portA, ':b' => $portB]
        );
        if ($row) {
            throw new RuntimeException('One of those ports is already connected.');
        }

        $this->db->execute(
            'INSERT INTO port_connections (port_a, port_b, color) VALUES (:a, :b, :color)',
            [':a' => $portA, ':b' => $portB, ':color' => $color]
        );
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM port_connections WHERE id = :id', [':id' => $id]);
    }
}
