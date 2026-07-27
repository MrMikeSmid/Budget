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
    public function index(): void
    {
        $this->auth();
        $parkId = !empty($_GET['park']) ? (int) $_GET['park'] : null;
        $type = in_array($_GET['type'] ?? '', ['staff', 'guest'], true) ? (string) $_GET['type'] : null;
        view('people/index', [
            'title' => 'Personen',
            'parks' => (new Park())->all(),
            'selectedParkId' => $parkId,
            'selectedType' => $type,
            'people' => (new Person())->all($parkId, $type),
        ]);
    }

    public function create(string $parkId): void
    {
        $this->auth();
        $park = (new Park())->find((int) $parkId);
        if (!$park) {
            http_response_code(404);
            view('errors/404', ['title' => 'Park niet gevonden']);
            return;
        }
        $type = $_GET['type'] ?? 'staff';
        view('people/form', ['title' => 'Nieuw persoon', 'park' => $park, 'person' => null, 'type' => $type]);
    }

    public function store(string $parkId): void
    {
        $this->auth();
        $this->verifyCsrf();
        $park = (new Park())->find((int) $parkId);
        if (!$park) {
            http_response_code(404);
            view('errors/404', ['title' => 'Park niet gevonden']);
            return;
        }
        $type = in_array($_POST['type'] ?? '', ['staff', 'guest'], true) ? (string) $_POST['type'] : 'staff';
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Vul een naam in.');
            redirect('/parken/' . $parkId . '/personen/nieuw?type=' . $type);
        }
        $role = trim((string) ($_POST['role'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $id = (new Person())->create((int) $parkId, $type, $name, $role, $email, $phone, $notes);
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
        $park = (new Park())->find((int) $person['park_id']);
        view('people/show', [
            'title' => $person['name'],
            'person' => $person,
            'park' => $park,
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
        $park = (new Park())->find((int) $person['park_id']);
        view('people/form', ['title' => 'Persoon bewerken', 'park' => $park, 'person' => $person, 'type' => $person['type']]);
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
        (new Person())->update((int) $id, $name, $role, $email, $phone, $notes, $isActive);
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
        redirect('/parken/' . $person['park_id']);
    }
}
