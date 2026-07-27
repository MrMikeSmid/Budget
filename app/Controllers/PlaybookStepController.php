<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Park;
use App\Models\Playbook;
use App\Models\PlaybookStep;

final class PlaybookStepController extends Controller
{
    private const TYPES = ['eenmalig', 'periodiek'];
    private const INTERVALS = ['dagelijks', 'wekelijks', 'maandelijks'];

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
        [$parkId, $type, $title, $body, $startDate, $endDate, $interval, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/draaiboeken/' . $playbookId);
        }
        (new PlaybookStep())->create((int) $playbookId, $parkId, $type, $title, $body, $startDate, $endDate, $interval);
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
        [$parkId, $type, $title, $body, $startDate, $endDate, $interval, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/draaiboeken/' . $step['playbook_id']);
        }
        (new PlaybookStep())->update((int) $id, $parkId, $type, $title, $body, $startDate, $endDate, $interval);
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

    /** @return array{0:?int,1:string,2:string,3:string,4:string,5:string,6:?string,7:?string} */
    private function readInput(): array
    {
        $parkId = !empty($_POST['park_id']) ? (int) $_POST['park_id'] : null;
        $type = in_array($_POST['type'] ?? '', self::TYPES, true) ? (string) $_POST['type'] : 'eenmalig';
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $startDate = (string) ($_POST['start_date'] ?? '');
        $endDate = (string) ($_POST['end_date'] ?? '');
        $interval = in_array($_POST['recurrence_interval'] ?? '', self::INTERVALS, true) ? (string) $_POST['recurrence_interval'] : null;
        if ($type !== 'periodiek') {
            $interval = null;
        }

        if ($title === '' || mb_strlen($title) > 160) {
            return [$parkId, $type, $title, $body, $startDate, $endDate, $interval, 'Geef de stap een korte titel.'];
        }
        if ($parkId !== null && !(new Park())->find($parkId)) {
            return [$parkId, $type, $title, $body, $startDate, $endDate, $interval, 'Kies een geldig park.'];
        }
        if (!$this->isValidDate($startDate)) {
            return [$parkId, $type, $title, $body, $startDate, $endDate, $interval, 'Kies een geldige startdatum.'];
        }
        if (!$this->isValidDate($endDate)) {
            return [$parkId, $type, $title, $body, $startDate, $endDate, $interval, 'Kies een geldige einddatum.'];
        }
        if ($endDate < $startDate) {
            return [$parkId, $type, $title, $body, $startDate, $endDate, $interval, 'De einddatum kan niet voor de startdatum liggen.'];
        }
        if ($type === 'periodiek' && $interval === null) {
            return [$parkId, $type, $title, $body, $startDate, $endDate, $interval, 'Kies een herhaling voor een periodieke stap.'];
        }

        return [$parkId, $type, $title, $body, $startDate, $endDate, $interval, null];
    }

    private function isValidDate(string $date): bool
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed && $parsed->format('Y-m-d') === $date;
    }
}
