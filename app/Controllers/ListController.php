<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\TodoList;
use App\Models\User;

final class ListController extends Controller
{
    private TodoList $lists;

    public function __construct()
    {
        $this->lists = new TodoList();
    }

    public function create(): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 80) {
            flash('error', 'Geef je lijst een korte naam.');
            redirect('/');
        }
        $allowedColors = ['violet', 'coral', 'mint', 'sun'];
        $color = in_array($_POST['color'] ?? '', $allowedColors, true) ? $_POST['color'] : 'violet';
        $emoji = mb_substr(trim((string) ($_POST['emoji'] ?? '✨')), 0, 2) ?: '✨';
        $id = $this->lists->create((int) $user['id'], $title, $emoji, $color);
        redirect('/lists/' . $id);
    }

    public function show(string $id): void
    {
        $user = $this->auth();
        $list = $this->lists->findAccessible((int) $id, (int) $user['id']);
        if (!$list) {
            http_response_code(404);
            view('errors/404', ['title' => 'Lijst niet gevonden']);
            return;
        }
        view('lists/show', [
            'title' => $list['title'],
            'user' => $user,
            'list' => $list,
            'items' => $this->lists->items((int) $id),
            'members' => $this->lists->members((int) $id),
        ]);
    }

    public function state(string $id): void
    {
        $user = $this->auth();
        $this->accessible((int) $id, (int) $user['id']);
        $this->respondWithState((int) $id);
    }

    public function addItem(string $id): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $this->accessible((int) $id, (int) $user['id']);
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title !== '' && mb_strlen($title) <= 160) {
            $this->lists->addItem((int) $id, (int) $user['id'], $title);
        }
        if ($this->wantsJson()) {
            $this->respondWithState((int) $id);
            return;
        }
        redirect('/lists/' . $id);
    }

    public function toggle(string $listId, string $itemId): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $this->accessible((int) $listId, (int) $user['id']);
        $this->lists->toggleItem((int) $itemId, (int) $listId, (int) $user['id']);
        if ($this->wantsJson()) {
            $this->respondWithState((int) $listId);
            return;
        }
        redirect('/lists/' . $listId);
    }

    public function share(string $id): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $list = $this->accessible((int) $id, (int) $user['id']);
        if ((int) $list['owner_id'] !== (int) $user['id']) {
            flash('error', 'Alleen de maker kan mensen uitnodigen.');
            redirect('/lists/' . $id);
        }
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $email === $user['email']) {
            flash('error', 'Vul het e-mailadres van iemand anders in.');
            redirect('/lists/' . $id);
        }
        $member = (new User())->findOrCreate($email);
        $this->lists->share((int) $id, (int) $user['id'], (int) $member['id']);
        flash('success', $member['name'] . ' kan nu meedoen.');
        redirect('/lists/' . $id);
    }

    public function delete(string $id): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $this->lists->delete((int) $id, (int) $user['id']);
        flash('success', 'Het lijstje is verwijderd.');
        redirect('/');
    }

    private function accessible(int $id, int $userId): array
    {
        $list = $this->lists->findAccessible($id, $userId);
        if (!$list) {
            http_response_code(403);
            exit('Geen toegang tot deze lijst.');
        }
        return $list;
    }

    private function wantsJson(): bool
    {
        return str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    private function respondWithState(int $listId): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        echo json_encode($this->lists->liveState($listId), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
