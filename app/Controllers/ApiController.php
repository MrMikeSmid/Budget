<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\JsonResponse;
use App\Models\AuditLog;
use App\Models\TodoList;
use App\Models\User;

final class ApiController
{
    private const PRIORITIES = ['none', 'low', 'medium', 'high'];
    private const ROLES = ['member'];

    public function login(): void
    {
        $input = $this->input();
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            JsonResponse::error(400, 'Vul een geldig e-mailadres in.');
            return;
        }
        $users = new User();
        $user = $users->findByEmail($email);
        if (!$user || ($user['password_hash'] && !password_verify($password, (string) $user['password_hash']))) {
            JsonResponse::error(401, 'Ongeldige inloggegevens.');
            return;
        }
        if (!$user['password_hash'] && $password !== '') {
            JsonResponse::error(401, 'Ongeldige inloggegevens.');
            return;
        }
        $token = bin2hex(random_bytes(32));
        db()->prepare('INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, datetime(CURRENT_TIMESTAMP, \'+90 days\'))')
            ->execute([(int) $user['id'], hash('sha256', $token)]);
        $users->recordLogin((int) $user['id']);
        JsonResponse::send(['token' => $token, 'user' => $this->userPayload($user)]);
    }

    public function lists(): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        $stmt = db()->prepare(<<<'SQL'
            SELECT l.id, l.title, l.owner_id AS owner_user_id, l.created_at, l.updated_at, l.deleted_at,
                CASE WHEN l.owner_id = :user_id THEN 'owner' ELSE COALESCE(lm.role, 'member') END AS role
            FROM todo_lists l
            LEFT JOIN list_members lm ON lm.list_id = l.id AND lm.user_id = :user_id AND lm.accepted_at IS NOT NULL
            WHERE l.deleted_at IS NULL AND (l.owner_id = :user_id OR lm.user_id IS NOT NULL)
            ORDER BY l.updated_at DESC, l.id DESC
        SQL);
        $stmt->execute(['user_id' => (int) $user['id']]);
        JsonResponse::send(['lists' => array_map($this->listMapper(), $stmt->fetchAll())]);
    }

    public function createList(): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        $input = $this->input();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 80) { JsonResponse::error(400, 'Geef je lijst een korte naam.'); return; }
        $id = (new TodoList())->create((int) $user['id'], $title, 'sparkles', 'violet');
        JsonResponse::send(['list' => $this->findListForApi($id, (int) $user['id'])], 201);
    }

    public function updateList(string $listId): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        $list = $this->accessibleList((int) $listId, (int) $user['id']); if (!$list) { return; }
        if ((int) $list['owner_id'] !== (int) $user['id']) { JsonResponse::error(403, 'Alleen de eigenaar kan dit lijstje wijzigen.'); return; }
        $title = trim((string) ($this->input()['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 80) { JsonResponse::error(400, 'Geef je lijst een korte naam.'); return; }
        db()->prepare('UPDATE todo_lists SET title = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND owner_id = ? AND deleted_at IS NULL')->execute([$title, (int) $listId, (int) $user['id']]);
        JsonResponse::send(['list' => $this->findListForApi((int) $listId, (int) $user['id'])]);
    }

    public function deleteList(string $listId): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        $list = $this->accessibleList((int) $listId, (int) $user['id']); if (!$list) { return; }
        if ((int) $list['owner_id'] !== (int) $user['id']) { JsonResponse::error(403, 'Alleen de eigenaar kan dit lijstje verwijderen.'); return; }
        db()->prepare('UPDATE todo_lists SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND owner_id = ?')->execute([(int) $listId, (int) $user['id']]);
        JsonResponse::send(['deleted' => true]);
    }

    public function items(string $listId): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        if (!$this->participant((int) $listId, (int) $user['id'])) { return; }
        $stmt = db()->prepare('SELECT * FROM todo_items WHERE list_id = ? AND deleted_at IS NULL ORDER BY is_completed ASC, id DESC');
        $stmt->execute([(int) $listId]);
        JsonResponse::send(['items' => array_map($this->itemMapper(), $stmt->fetchAll())]);
    }

    public function createItem(string $listId): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        if (!$this->participant((int) $listId, (int) $user['id'])) { return; }
        $input = $this->input();
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) { JsonResponse::error(400, 'Geef je taak een korte naam.'); return; }
        $priority = in_array($input['priority'] ?? 'none', self::PRIORITIES, true) ? (string) ($input['priority'] ?? 'none') : 'none';
        $dueDate = $this->validDate($input['due_date'] ?? null);
        if ($dueDate === false) { JsonResponse::error(400, 'Kies een geldige vervaldatum.'); return; }
        $id = (new TodoList())->addItem((int) $listId, (int) $user['id'], $title, $priority, $dueDate);
        $this->setItemNote($id, $input['note'] ?? null);
        JsonResponse::send(['item' => $this->findItemForApi($id)], 201);
    }

    public function updateItem(string $itemId): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        $item = $this->findItemForApi((int) $itemId); if (!$item) { JsonResponse::error(404, 'Taak niet gevonden.'); return; }
        if (!(new TodoList())->canParticipate((int) $item['list_id'], (int) $user['id'])) { JsonResponse::error(403, 'Geen toegang tot dit lijstje.'); return; }
        $input = $this->input();
        $fields = [];$params = [];
        if (array_key_exists('title', $input)) { $title = trim((string) $input['title']); if ($title === '' || mb_strlen($title) > 160) { JsonResponse::error(400, 'Geef je taak een korte naam.'); return; } $fields[]='title = ?'; $params[]=$title; }
        if (array_key_exists('note', $input)) { $fields[]='note = ?'; $params[]=(string) $input['note']; }
        if (array_key_exists('priority', $input)) { if (!in_array($input['priority'], self::PRIORITIES, true)) { JsonResponse::error(400, 'Ongeldige prioriteit.'); return; } $fields[]='priority = ?'; $params[]=$input['priority']; }
        if (array_key_exists('due_date', $input)) { $dueDate = $this->validDate($input['due_date']); if ($dueDate === false) { JsonResponse::error(400, 'Kies een geldige vervaldatum.'); return; } $fields[]='due_date = ?'; $params[]=$dueDate; }
        if (array_key_exists('completed', $input)) { $completed = (bool) $input['completed']; $fields[]='is_completed = ?'; $params[]=$completed ? 1 : 0; $fields[]='completed_by = ?'; $params[]=$completed ? (int) $user['id'] : null; $fields[]='completed_at = '.($completed ? 'CURRENT_TIMESTAMP' : 'NULL'); }
        if (!$fields) { JsonResponse::error(400, 'Geen wijzigingen opgegeven.'); return; }
        $fields[]='updated_at = CURRENT_TIMESTAMP'; $params[]=(int) $itemId;
        db()->prepare('UPDATE todo_items SET '.implode(', ', $fields).' WHERE id = ? AND deleted_at IS NULL')->execute($params);
        db()->prepare('UPDATE todo_lists SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $item['list_id']]);
        JsonResponse::send(['item' => $this->findItemForApi((int) $itemId)]);
    }

    public function deleteItem(string $itemId): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        $item = $this->findItemForApi((int) $itemId); if (!$item) { JsonResponse::error(404, 'Taak niet gevonden.'); return; }
        if (!(new TodoList())->canParticipate((int) $item['list_id'], (int) $user['id'])) { JsonResponse::error(403, 'Geen toegang tot dit lijstje.'); return; }
        db()->prepare('UPDATE todo_items SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $itemId]);
        db()->prepare('UPDATE todo_lists SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(int) $item['list_id']]);
        JsonResponse::send(['deleted' => true]);
    }

    public function addMember(string $listId): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        $list = $this->accessibleList((int) $listId, (int) $user['id']); if (!$list) { return; }
        if ((int) $list['owner_id'] !== (int) $user['id']) { JsonResponse::error(403, 'Alleen de eigenaar kan mensen uitnodigen.'); return; }
        $input = $this->input(); $email = mb_strtolower(trim((string) ($input['email'] ?? ''))); $role = (string) ($input['role'] ?? 'member');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $email === $user['email']) { JsonResponse::error(400, 'Vul het e-mailadres van iemand anders in.'); return; }
        if (!in_array($role, self::ROLES, true)) { JsonResponse::error(400, 'Ongeldige rol.'); return; }
        $users = new User(); $member = $users->findByEmail($email);
        if ($member === null) { $member = $users->findOrCreate($email); (new AuditLog())->recordRegistration($member); }
        db()->prepare('INSERT OR IGNORE INTO list_members (list_id, user_id, invited_by, accepted_at, role) VALUES (?, ?, ?, CURRENT_TIMESTAMP, ?)')->execute([(int) $listId, (int) $member['id'], (int) $user['id'], $role]);
        JsonResponse::send(['member' => ['user_id' => (int) $member['id'], 'email' => $member['email'], 'display_name' => $member['name'], 'role' => $role]], 201);
    }

    public function sync(): void
    {
        $user = $this->requireUser(); if (!$user) { return; }
        $since = (string) ($_GET['since'] ?? '1970-01-01 00:00:00');
        if (strtotime($since) === false) { JsonResponse::error(400, 'Ongeldige since timestamp.'); return; }
        $listStmt = db()->prepare('SELECT l.id, l.title, l.owner_id AS owner_user_id, l.created_at, l.updated_at, l.deleted_at FROM todo_lists l LEFT JOIN list_members lm ON lm.list_id=l.id AND lm.user_id=:user_id AND lm.accepted_at IS NOT NULL WHERE (l.owner_id=:user_id OR lm.user_id IS NOT NULL) AND (l.updated_at >= :since OR l.deleted_at >= :since)');
        $listStmt->execute(['user_id'=>(int)$user['id'], 'since'=>$since]);
        $itemStmt = db()->prepare('SELECT i.* FROM todo_items i JOIN todo_lists l ON l.id=i.list_id LEFT JOIN list_members lm ON lm.list_id=l.id AND lm.user_id=:user_id AND lm.accepted_at IS NOT NULL WHERE (l.owner_id=:user_id OR lm.user_id IS NOT NULL) AND (i.updated_at >= :since OR i.deleted_at >= :since)');
        $itemStmt->execute(['user_id'=>(int)$user['id'], 'since'=>$since]);
        JsonResponse::send(['server_time' => date('Y-m-d H:i:s'), 'lists' => array_map($this->listMapper(), $listStmt->fetchAll()), 'items' => array_map($this->itemMapper(), $itemStmt->fetchAll())]);
    }

    private function requireUser(): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $m)) { JsonResponse::error(401, 'Authenticatie vereist.'); return null; }
        $stmt = db()->prepare('SELECT u.* FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? AND (t.expires_at IS NULL OR t.expires_at > CURRENT_TIMESTAMP)');
        $stmt->execute([hash('sha256', trim($m[1]))]);
        $user = $stmt->fetch();
        if (!$user) { JsonResponse::error(401, 'Ongeldig of verlopen token.'); return null; }
        db()->prepare('UPDATE api_tokens SET last_used_at = CURRENT_TIMESTAMP WHERE token_hash = ?')->execute([hash('sha256', trim($m[1]))]);
        return $user;
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : $_POST;
    }
    private function userPayload(array $u): array { return ['id'=>(int)$u['id'], 'email'=>$u['email'], 'display_name'=>$u['name']]; }
    private function listMapper(): callable { return fn(array $l): array => ['id'=>(int)$l['id'], 'title'=>$l['title'], 'owner_user_id'=>(int)$l['owner_user_id'], 'created_at'=>$l['created_at'], 'updated_at'=>$l['updated_at'], 'deleted_at'=>$l['deleted_at'] ?? null, 'role'=>$l['role'] ?? null]; }
    private function itemMapper(): callable { return fn(array $i): array => ['id'=>(int)$i['id'], 'list_id'=>(int)$i['list_id'], 'title'=>$i['title'], 'note'=>$i['note'] ?? null, 'priority'=>$i['priority'], 'due_date'=>$i['due_date'], 'completed'=>(bool)$i['is_completed'], 'completed_by_user_id'=>$i['completed_by'] !== null ? (int)$i['completed_by'] : null, 'created_at'=>$i['created_at'], 'updated_at'=>$i['updated_at'] ?? $i['created_at'], 'deleted_at'=>$i['deleted_at'] ?? null]; }
    private function findListForApi(int $id, int $userId): ?array { $stmt=db()->prepare('SELECT id,title,owner_id AS owner_user_id,created_at,updated_at,deleted_at FROM todo_lists WHERE id=? AND owner_id=?'); $stmt->execute([$id,$userId]); $row=$stmt->fetch(); return $row ? ($this->listMapper())($row) : null; }
    private function findItemForApi(int $id): ?array { $stmt=db()->prepare('SELECT * FROM todo_items WHERE id=? AND deleted_at IS NULL'); $stmt->execute([$id]); $row=$stmt->fetch(); return $row ? ($this->itemMapper())($row) : null; }
    private function accessibleList(int $listId, int $userId): ?array { $list=(new TodoList())->findAccessible($listId,$userId); if (!$list || $list['deleted_at'] !== null) { JsonResponse::error(404, 'Lijst niet gevonden.'); return null; } return $list; }
    private function participant(int $listId, int $userId): bool { $list=$this->accessibleList($listId,$userId); if (!$list) { return false; } if (!(new TodoList())->canParticipate($listId,$userId)) { JsonResponse::error(403, 'Accepteer de uitnodiging voordat je taken wijzigt.'); return false; } return true; }
    private function validDate(mixed $value): string|false|null { if ($value === null || $value === '') return null; $date=\DateTimeImmutable::createFromFormat('!Y-m-d', (string)$value); return $date && $date->format('Y-m-d') === (string)$value ? (string)$value : false; }
    private function setItemNote(int $id, mixed $note): void { if ($note !== null) { db()->prepare('UPDATE todo_items SET note = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([(string)$note, $id]); } }
}
