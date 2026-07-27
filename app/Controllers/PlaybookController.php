<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;
use App\Models\Park;
use App\Models\Person;
use App\Models\Playbook;
use App\Models\PlaybookStep;

final class PlaybookController extends Controller
{
    public function index(): void
    {
        $this->auth();
        $departmentId = !empty($_GET['department']) ? (int) $_GET['department'] : null;
        $parkId = !empty($_GET['park']) ? (int) $_GET['park'] : null;
        view('playbooks/index', [
            'title' => 'Draaiboeken',
            'departments' => (new Department())->all(),
            'parks' => (new Park())->all(),
            'selectedDepartmentId' => $departmentId,
            'selectedParkId' => $parkId,
            'playbooks' => (new Playbook())->all($departmentId, $parkId),
        ]);
    }

    public function create(): void
    {
        $this->auth();
        view('playbooks/form', [
            'title' => 'Nieuw draaiboek',
            'playbook' => null,
            'departments' => (new Department())->all(),
            'parks' => (new Park())->all(),
            'people' => (new Person())->all(null, 'staff'),
        ]);
    }

    public function store(): void
    {
        $this->auth();
        $this->verifyCsrf();
        [$title, $departmentId, $parkId, $leaderPersonId, $leaderName, $description, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/draaiboeken/nieuw');
        }
        $id = (new Playbook())->create($title, $departmentId, $parkId, $leaderPersonId, $leaderName, $description);
        redirect('/draaiboeken/' . $id);
    }

    public function show(string $id): void
    {
        $this->auth();
        $playbook = (new Playbook())->find((int) $id);
        if (!$playbook) {
            http_response_code(404);
            view('errors/404', ['title' => 'Draaiboek niet gevonden']);
            return;
        }
        view('playbooks/show', [
            'title' => $playbook['title'],
            'playbook' => $playbook,
            'steps' => (new PlaybookStep())->forPlaybook((int) $id),
            'shareUrl' => absolute_url('/gedeeld/' . $playbook['share_token']),
        ]);
    }

    public function edit(string $id): void
    {
        $this->auth();
        $playbook = (new Playbook())->find((int) $id);
        if (!$playbook) {
            http_response_code(404);
            view('errors/404', ['title' => 'Draaiboek niet gevonden']);
            return;
        }
        view('playbooks/form', [
            'title' => 'Draaiboek bewerken',
            'playbook' => $playbook,
            'departments' => (new Department())->all(),
            'parks' => (new Park())->all(),
            'people' => (new Person())->all(null, 'staff'),
        ]);
    }

    public function update(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $playbook = (new Playbook())->find((int) $id);
        if (!$playbook) {
            http_response_code(404);
            view('errors/404', ['title' => 'Draaiboek niet gevonden']);
            return;
        }
        [$title, $departmentId, $parkId, $leaderPersonId, $leaderName, $description, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/draaiboeken/' . $id . '/bewerken');
        }
        (new Playbook())->update((int) $id, $title, $departmentId, $parkId, $leaderPersonId, $leaderName, $description);
        redirect('/draaiboeken/' . $id);
    }

    public function delete(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        (new Playbook())->delete((int) $id);
        flash('success', 'Het draaiboek is verwijderd.');
        redirect('/draaiboeken');
    }

    public function regenerateToken(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $playbook = (new Playbook())->find((int) $id);
        if (!$playbook) {
            http_response_code(404);
            view('errors/404', ['title' => 'Draaiboek niet gevonden']);
            return;
        }
        (new Playbook())->regenerateToken((int) $id);
        flash('success', 'De deelbare link is vernieuwd. De oude link werkt niet meer.');
        redirect('/draaiboeken/' . $id);
    }

    /** @return array{0:string,1:int,2:?int,3:?int,4:string,5:string,6:?string} */
    private function readInput(): array
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        $departmentId = (int) ($_POST['department_id'] ?? 0);
        $parkId = !empty($_POST['park_id']) ? (int) $_POST['park_id'] : null;
        $leaderPersonId = !empty($_POST['leader_person_id']) ? (int) $_POST['leader_person_id'] : null;
        $leaderName = trim((string) ($_POST['leader_name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($title === '' || mb_strlen($title) > 120) {
            return [$title, $departmentId, $parkId, $leaderPersonId, $leaderName, $description, 'Geef het draaiboek een titel.'];
        }
        if (!(new Department())->find($departmentId)) {
            return [$title, $departmentId, $parkId, $leaderPersonId, $leaderName, $description, 'Kies een geldige afdeling.'];
        }
        if ($leaderPersonId !== null) {
            $person = (new Person())->find($leaderPersonId);
            if (!$person) {
                return [$title, $departmentId, $parkId, $leaderPersonId, $leaderName, $description, 'Gekozen leidinggevende bestaat niet (meer).'];
            }
            $leaderName = $person['name'];
        } elseif ($leaderName === '') {
            return [$title, $departmentId, $parkId, $leaderPersonId, $leaderName, $description, 'Kies een leidinggevende of vul een naam in.'];
        }

        return [$title, $departmentId, $parkId, $leaderPersonId, $leaderName, $description, null];
    }
}
