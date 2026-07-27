<?php

declare(strict_types=1);

namespace App\Models;

final class Park
{
    public function all(): array
    {
        return db()->query('SELECT * FROM parks ORDER BY sort_order ASC, name ASC')->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM parks WHERE id = ?');
        $stmt->execute([$id]);
        $park = $stmt->fetch();
        return $park ?: null;
    }

    public function create(string $name, string $location, string $attentionPoints): int
    {
        $slug = $this->uniqueSlug($name);
        $sortOrder = (int) db()->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM parks')->fetchColumn();
        $stmt = db()->prepare('INSERT INTO parks (name, slug, location, attention_points, sort_order) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $slug, $location, $attentionPoints, $sortOrder]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, string $name, string $location, string $attentionPoints): void
    {
        $stmt = db()->prepare('UPDATE parks SET name = ?, location = ?, attention_points = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$name, $location, $attentionPoints, $id]);
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM parks WHERE id = ?')->execute([$id]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($name)), '-');
        $base = $base !== '' ? $base : 'park';
        $slug = $base;
        $suffix = 1;
        $stmt = db()->prepare('SELECT COUNT(*) FROM parks WHERE slug = ?');
        while (true) {
            $stmt->execute([$slug]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $slug;
            }
            $suffix++;
            $slug = $base . '-' . $suffix;
        }
    }
}
