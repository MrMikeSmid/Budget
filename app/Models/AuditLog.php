<?php

declare(strict_types=1);

namespace App\Models;

final class AuditLog
{
    public function record(array $user, string $event, string $description, string $location, array $metadata = []): void
    {
        $stmt = db()->prepare(<<<'SQL'
            INSERT INTO audit_logs (
                user_id, user_name, user_email, event, description, location,
                request_path, ip_address, user_agent, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([
            (int) $user['id'],
            (string) $user['name'],
            (string) $user['email'],
            $event,
            $description,
            $location,
            $this->requestPath(),
            $this->ipAddress(),
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function recordRegistration(array $user): void
    {
        $this->record($user, 'account.registered', 'Account geregistreerd', 'Account');
    }

    /** @return array{logs: array<int, array>, total: int, page: int, pages: int} */
    public function forAdmin(string $search = '', string $category = '', int $page = 1, int $perPage = 50): array
    {
        $conditions = [];
        $parameters = [];
        $search = trim($search);
        if ($search !== '') {
            $conditions[] = '(user_name LIKE :search OR user_email LIKE :search OR description LIKE :search OR location LIKE :search OR ip_address LIKE :search)';
            $parameters['search'] = '%' . $search . '%';
        }
        if ($category !== '') {
            $conditions[] = 'event LIKE :category';
            $parameters['category'] = $category . '.%';
        }
        $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $count = db()->prepare('SELECT COUNT(*) FROM audit_logs' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        $query = db()->prepare('SELECT * FROM audit_logs' . $where . ' ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset');
        foreach ($parameters as $key => $value) {
            $query->bindValue(':' . $key, $value);
        }
        $query->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * $perPage, \PDO::PARAM_INT);
        $query->execute();

        return ['logs' => $query->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    private function requestPath(): string
    {
        return mb_substr((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), 0, 500);
    }

    private function ipAddress(): string
    {
        return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'Onbekend'), 0, 45);
    }
}
