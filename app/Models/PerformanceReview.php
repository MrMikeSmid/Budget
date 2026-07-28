<?php

declare(strict_types=1);

namespace App\Models;

final class PerformanceReview
{
    public function forPerson(int $personId): array
    {
        $stmt = db()->prepare('SELECT * FROM performance_reviews WHERE person_id = ? ORDER BY review_date DESC');
        $stmt->execute([$personId]);
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        return db()->query(<<<'SQL'
            SELECT r.*, p.name AS person_name
            FROM performance_reviews r
            JOIN people p ON p.id = r.person_id
            ORDER BY r.review_date DESC
        SQL)->fetchAll();
    }

    /** Upcoming follow-up conversations among staff linked to a park, used by the parkrapportage. */
    public function upcomingForPark(int $parkId): array
    {
        $stmt = db()->prepare(<<<'SQL'
            SELECT r.*, p.name AS person_name
            FROM performance_reviews r
            JOIN people p ON p.id = r.person_id
            JOIN person_parks pp ON pp.person_id = p.id AND pp.park_id = ?
            WHERE r.follow_up_date IS NOT NULL AND r.follow_up_date >= date('now')
            ORDER BY r.follow_up_date ASC
        SQL);
        $stmt->execute([$parkId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = db()->prepare('SELECT * FROM performance_reviews WHERE id = ?');
        $stmt->execute([$id]);
        $review = $stmt->fetch();
        return $review ?: null;
    }

    public function create(int $personId, string $reviewDate, string $type, string $summary, ?string $followUpDate): int
    {
        $stmt = db()->prepare('INSERT INTO performance_reviews (person_id, review_date, type, summary, follow_up_date) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$personId, $reviewDate, $type, $summary, $followUpDate]);
        return (int) db()->lastInsertId();
    }

    public function update(int $id, string $reviewDate, string $type, string $summary, ?string $followUpDate): void
    {
        $stmt = db()->prepare('UPDATE performance_reviews SET review_date = ?, type = ?, summary = ?, follow_up_date = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->execute([$reviewDate, $type, $summary, $followUpDate, $id]);
    }

    public function delete(int $id): void
    {
        db()->prepare('DELETE FROM performance_reviews WHERE id = ?')->execute([$id]);
    }
}
