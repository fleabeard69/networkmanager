<?php
declare(strict_types=1);

class SiteModel
{
    public function __construct(private Database $db) {}

    public function all(): array
    {
        return $this->db->fetchAll(
            "SELECT s.*,
                (SELECT COUNT(*) FROM devices WHERE site_id = s.id) AS device_count
             FROM sites s
             ORDER BY s.name"
        );
    }

    public function find(int $id): array|false
    {
        return $this->db->fetchOne(
            'SELECT * FROM sites WHERE id = :id',
            [':id' => $id]
        );
    }

    public function first(): array|false
    {
        return $this->db->fetchOne('SELECT * FROM sites ORDER BY id LIMIT 1');
    }

    public function create(array $data): int
    {
        $stmt = $this->db->query(
            'INSERT INTO sites (name, slug, description) VALUES (:name, :slug, :desc) RETURNING id',
            [':name' => $data['name'], ':slug' => $data['slug'], ':desc' => $data['description']]
        );
        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE sites SET name = :name, slug = :slug, description = :desc, updated_at = NOW() WHERE id = :id',
            [':name' => $data['name'], ':slug' => $data['slug'], ':desc' => $data['description'], ':id' => $id]
        );
    }

    public function delete(int $id): bool
    {
        return $this->db->execute('DELETE FROM sites WHERE id = :id', [':id' => $id]) > 0;
    }

    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $row = $this->db->fetchOne(
                'SELECT 1 FROM sites WHERE slug = :slug AND id != :id',
                [':slug' => $slug, ':id' => $excludeId]
            );
        } else {
            $row = $this->db->fetchOne(
                'SELECT 1 FROM sites WHERE slug = :slug',
                [':slug' => $slug]
            );
        }
        return (bool) $row;
    }
}
