<?php
declare(strict_types=1);

class ConnectionModel
{
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

    /** @throws PDOException on duplicate connection */
    public function create(int $portA, int $portB): int
    {
        $this->db->execute(
            'INSERT INTO port_connections (port_a, port_b) VALUES (:a, :b)',
            [':a' => $portA, ':b' => $portB]
        );
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM port_connections WHERE id = :id', [':id' => $id]);
    }
}
