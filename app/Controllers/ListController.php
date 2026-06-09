<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\TodoList;
use App\Models\User;
use App\Services\InvitationMailer;
use App\Services\ListNotificationService;

final class ListController extends Controller
{
    private const MAX_TASK_IMAGE_SIZE = 5 * 1024 * 1024;

    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    private const PRIORITIES = ['none', 'low', 'medium', 'high'];

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
        $allowedColors = ['violet', 'coral', 'mint', 'sun', 'rose', 'peach', 'sky', 'sage', 'lavender'];
        $color = in_array($_POST['color'] ?? '', $allowedColors, true) ? $_POST['color'] : 'violet';
        $requestedMood = trim((string) ($_POST['emoji'] ?? 'sparkles'));
        $emoji = array_key_exists($requestedMood, list_mood_options()) ? $requestedMood : 'sparkles';
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
        (new User())->touchPresence((int) $user['id']);
        $state = $this->lists->liveState((int) $id);
        view('lists/show', [
            'title' => $list['title'],
            'user' => $user,
            'list' => $list,
            'items' => $state['items'],
            'members' => $state['members'],
            'initialState' => $state,
        ]);
    }

    public function state(string $id): void
    {
        $user = $this->auth();
        $this->accessible((int) $id, (int) $user['id']);
        (new User())->touchPresence((int) $user['id']);
        $this->respondWithState((int) $id);
    }

    public function addItem(string $id): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $list = $this->accessible((int) $id, (int) $user['id']);
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) {
            $this->itemError($id, 'Geef je taak een korte naam.');
        }

        $priority = in_array($_POST['priority'] ?? 'none', self::PRIORITIES, true)
            ? (string) ($_POST['priority'] ?? 'none')
            : 'none';
        $dueDate = $this->validatedDueDate((string) ($_POST['due_date'] ?? ''));
        if ($dueDate === false) {
            $this->itemError($id, 'Kies een geldige vervaldatum.');
        }

        $imageFilename = $this->storeTaskImage($_FILES['image'] ?? null);
        if ($imageFilename === false) {
            $this->itemError($id, 'Gebruik een JPG-, PNG-, WebP- of GIF-afbeelding van maximaal 5 MB.');
        }

        try {
            $this->lists->addItem((int) $id, (int) $user['id'], $title, $priority, $dueDate, $imageFilename);
        } catch (\Throwable $exception) {
            $this->deleteTaskImage($imageFilename);
            throw $exception;
        }
        $this->notify(fn(ListNotificationService $notifications) => $notifications->taskCreated($list, $user, $title));

        if ($this->wantsJson()) {
            $this->respondWithState((int) $id);
            return;
        }
        redirect('/lists/' . $id);
    }

    public function itemImage(string $listId, string $itemId): void
    {
        $user = $this->auth();
        $this->accessible((int) $listId, (int) $user['id']);
        $filename = $this->lists->itemImage((int) $itemId, (int) $listId);
        $path = $this->taskImageDirectory() . '/' . basename((string) $filename);
        $extension = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $contentTypes = array_flip(self::IMAGE_TYPES);

        if ($filename === null || !isset($contentTypes[$extension]) || !is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: ' . $contentTypes[$extension]);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    public function addComment(string $listId, string $itemId): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $this->accessible((int) $listId, (int) $user['id']);
        $body = trim((string) ($_POST['body'] ?? ''));
        if ($body !== '' && mb_strlen($body) <= 1000) {
            $this->lists->addComment((int) $itemId, (int) $listId, (int) $user['id'], $body);
        }
        if ($this->wantsJson()) {
            $this->respondWithState((int) $listId);
            return;
        }
        redirect('/lists/' . $listId);
    }

    public function toggle(string $listId, string $itemId): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $list = $this->accessible((int) $listId, (int) $user['id']);
        $item = $this->lists->toggleItem((int) $itemId, (int) $listId, (int) $user['id']);
        if ($item) {
            $this->notify(static fn(ListNotificationService $notifications) => (bool) $item['is_completed']
                ? $notifications->taskCompleted($list, $user, $item['title'])
                : $notifications->taskChanged($list, $user, $item['title']));
        }
        if ($this->wantsJson()) {
            $this->respondWithState((int) $listId);
            return;
        }
        redirect('/lists/' . $listId);
    }

    public function deleteItem(string $listId, string $itemId): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $this->accessible((int) $listId, (int) $user['id']);
        $imageFilename = $this->lists->itemImage((int) $itemId, (int) $listId);
        if ($this->lists->deleteCompletedItem((int) $itemId, (int) $listId)) {
            $this->deleteTaskImage($imageFilename);
        }
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
        $sent = (new InvitationMailer())->send($email, $user, $list);
        if ($sent) {
            flash('success', 'De uitnodiging is naar ' . $email . ' verstuurd.');
        } else {
            flash('error', $member['name'] . ' kan nu meedoen, maar de e-mail kon niet worden verstuurd.');
        }
        redirect('/lists/' . $id);
    }

    public function delete(string $id): void
    {
        $user = $this->auth();
        $this->verifyCsrf();
        $imageFilenames = $this->lists->imageFilenames((int) $id);
        if ($this->lists->delete((int) $id, (int) $user['id'])) {
            foreach ($imageFilenames as $imageFilename) {
                $this->deleteTaskImage($imageFilename);
            }
        }
        flash('success', 'Het lijstje is verwijderd.');
        redirect('/');
    }

    private function validatedDueDate(string $value): string|false|null
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value ? $value : false;
    }

    private function storeTaskImage(?array $upload): string|false|null
    {
        if ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_TASK_IMAGE_SIZE) {
            return false;
        }
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        if (!is_string($mimeType) || !isset(self::IMAGE_TYPES[$mimeType]) || @getimagesize($temporaryPath) === false) {
            return false;
        }

        $directory = $this->taskImageDirectory();
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return false;
        }
        $filename = bin2hex(random_bytes(20)) . '.' . self::IMAGE_TYPES[$mimeType];
        return move_uploaded_file($temporaryPath, $directory . '/' . $filename) ? $filename : false;
    }

    private function deleteTaskImage(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }
        $path = $this->taskImageDirectory() . '/' . basename($filename);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function taskImageDirectory(): string
    {
        return dirname(__DIR__, 2) . '/storage/task-images';
    }

    private function itemError(string $listId, string $message): never
    {
        if ($this->wantsJson()) {
            $this->json(['message' => $message], 422);
        }
        flash('error', $message);
        redirect('/lists/' . $listId);
    }

    private function notify(callable $notification): void
    {
        try {
            if (!$notification(new ListNotificationService())) {
                error_log('Samen kon een lijstnotificatie niet versturen.');
            }
        } catch (\Throwable $exception) {
            error_log('Samen lijstnotificatie mislukt: ' . $exception->getMessage());
        }
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
