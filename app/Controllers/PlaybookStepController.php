<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Playbook;
use App\Models\PlaybookStep;

final class PlaybookStepController extends Controller
{
    private const SCHEDULE_TYPES = ['op_datum', 'vanaf_datum'];

    public function store(string $playbookId): void
    {
        $this->auth();
        $this->verifyCsrf();
        $playbook = (new Playbook())->find((int) $playbookId);
        if (!$playbook) {
            http_response_code(404);
            view('errors/404', ['title' => 'Draaiboek niet gevonden']);
            return;
        }
        [$title, $body, $scheduleType, $date, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/draaiboeken/' . $playbookId);
        }
        (new PlaybookStep())->create((int) $playbookId, $title, $body, $scheduleType, $date);
        redirect('/draaiboeken/' . $playbookId);
    }

    public function update(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $step = (new PlaybookStep())->find((int) $id);
        if (!$step) {
            http_response_code(404);
            view('errors/404', ['title' => 'Stap niet gevonden']);
            return;
        }
        [$title, $body, $scheduleType, $date, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/draaiboeken/' . $step['playbook_id']);
        }
        (new PlaybookStep())->update((int) $id, $title, $body, $scheduleType, $date);
        redirect('/draaiboeken/' . $step['playbook_id']);
    }

    public function toggle(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $step = (new PlaybookStep())->find((int) $id);
        if (!$step) {
            http_response_code(404);
            view('errors/404', ['title' => 'Stap niet gevonden']);
            return;
        }
        (new PlaybookStep())->toggle((int) $id);
        redirect('/draaiboeken/' . $step['playbook_id']);
    }

    public function delete(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $step = (new PlaybookStep())->find((int) $id);
        if (!$step) {
            http_response_code(404);
            view('errors/404', ['title' => 'Stap niet gevonden']);
            return;
        }
        (new PlaybookStep())->delete((int) $id);
        redirect('/draaiboeken/' . $step['playbook_id']);
    }

    /** @return array{0:string,1:string,2:string,3:string,4:?string} */
    private function readInput(): array
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $scheduleType = in_array($_POST['schedule_type'] ?? '', self::SCHEDULE_TYPES, true)
            ? (string) $_POST['schedule_type']
            : 'op_datum';
        $date = (string) ($_POST['date'] ?? '');

        if ($title === '' || mb_strlen($title) > 160) {
            return [$title, $body, $scheduleType, $date, 'Geef de stap een korte titel.'];
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            return [$title, $body, $scheduleType, $date, 'Kies een geldige datum.'];
        }

        return [$title, $body, $scheduleType, $date, null];
    }
}
