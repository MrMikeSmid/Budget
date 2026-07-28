<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Absence;
use App\Models\Item;
use App\Models\Park;
use App\Models\PerformanceReview;
use App\Models\Person;

final class PersonController extends Controller
{
    private const TYPES = ['staff', 'guest', 'candidate'];
    private const APPLICATION_STATUSES = ['nieuw', 'gesprek_gepland', 'afgewezen', 'aangenomen'];

    public function index(): void
    {
        $this->auth();
        $parkId = !empty($_GET['park']) ? (int) $_GET['park'] : null;
        $type = in_array($_GET['type'] ?? '', self::TYPES, true) ? (string) $_GET['type'] : null;
        view('people/index', [
            'title' => 'Personen',
            'parks' => (new Park())->all(),
            'selectedParkId' => $parkId,
            'selectedType' => $type,
            'people' => (new Person())->all($parkId, $type),
        ]);
    }

    public function create(): void
    {
        $this->auth();
        $type = in_array($_GET['type'] ?? '', self::TYPES, true) ? (string) $_GET['type'] : 'staff';
        $preselectedParkId = !empty($_GET['park']) ? (int) $_GET['park'] : null;
        view('people/form', [
            'title' => 'Nieuw persoon',
            'parks' => (new Park())->all(),
            'personParkIds' => $preselectedParkId !== null ? [$preselectedParkId] : [],
            'person' => null,
            'type' => $type,
        ]);
    }

    public function store(): void
    {
        $this->auth();
        $this->verifyCsrf();
        $type = in_array($_POST['type'] ?? '', self::TYPES, true) ? (string) $_POST['type'] : 'staff';
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Vul een naam in.');
            redirect('/personen/nieuw?type=' . $type);
        }
        $role = trim((string) ($_POST['role'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $parkIds = array_map('intval', (array) ($_POST['park_ids'] ?? []));
        $applicationStatus = $type === 'candidate' && in_array($_POST['application_status'] ?? '', self::APPLICATION_STATUSES, true)
            ? (string) $_POST['application_status']
            : ($type === 'candidate' ? 'nieuw' : null);
        $id = (new Person())->create($type, $name, $role, $email, $phone, $notes, $parkIds, $applicationStatus);
        redirect('/personen/' . $id);
    }

    public function show(string $id): void
    {
        $this->auth();
        $person = (new Person())->find((int) $id);
        if (!$person) {
            http_response_code(404);
            view('errors/404', ['title' => 'Persoon niet gevonden']);
            return;
        }
        view('people/show', [
            'title' => $person['name'],
            'person' => $person,
            'parks' => (new Person())->parksForPerson((int) $id),
            'items' => (new Item())->forPerson((int) $id),
            'absences' => $person['type'] === 'staff' ? (new Absence())->forPerson((int) $id) : [],
            'reviews' => $person['type'] === 'staff' ? (new PerformanceReview())->forPerson((int) $id) : [],
        ]);
    }

    public function edit(string $id): void
    {
        $this->auth();
        $person = (new Person())->find((int) $id);
        if (!$person) {
            http_response_code(404);
            view('errors/404', ['title' => 'Persoon niet gevonden']);
            return;
        }
        $personParks = (new Person())->parksForPerson((int) $id);
        view('people/form', [
            'title' => 'Persoon bewerken',
            'parks' => (new Park())->all(),
            'personParkIds' => array_map(fn(array $p): int => (int) $p['id'], $personParks),
            'person' => $person,
            'type' => $person['type'],
        ]);
    }

    public function update(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $person = (new Person())->find((int) $id);
        if (!$person) {
            http_response_code(404);
            view('errors/404', ['title' => 'Persoon niet gevonden']);
            return;
        }
        $type = in_array($_POST['type'] ?? '', self::TYPES, true) ? (string) $_POST['type'] : $person['type'];
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Vul een naam in.');
            redirect('/personen/' . $id . '/bewerken');
        }
        $role = trim((string) ($_POST['role'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $isActive = !empty($_POST['is_active']);
        $parkIds = array_map('intval', (array) ($_POST['park_ids'] ?? []));
        $applicationStatus = $type === 'candidate' && in_array($_POST['application_status'] ?? '', self::APPLICATION_STATUSES, true)
            ? (string) $_POST['application_status']
            : ($type === 'candidate' ? $person['application_status'] : null);
        $people = new Person();
        $people->update((int) $id, $type, $name, $role, $email, $phone, $notes, $isActive, $applicationStatus);
        $people->setParks((int) $id, $parkIds);
        redirect('/personen/' . $id);
    }

    public function delete(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $person = (new Person())->find((int) $id);
        if (!$person) {
            http_response_code(404);
            view('errors/404', ['title' => 'Persoon niet gevonden']);
            return;
        }
        (new Person())->delete((int) $id);
        flash('success', 'De persoon is verwijderd.');
        redirect('/personen');
    }
}
