<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Item;
use App\Models\Park;
use App\Models\Person;

final class ParkController extends Controller
{
    public function index(): void
    {
        $this->auth();
        $parks = new Park();
        view('parks/index', [
            'title' => 'Parken',
            'parks' => $parks->all(),
            'openCounts' => (new Item())->openCountsByPark(),
        ]);
    }

    public function create(): void
    {
        $this->auth();
        view('parks/form', ['title' => 'Nieuw park', 'park' => null]);
    }

    public function store(): void
    {
        $this->auth();
        $this->verifyCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            flash('error', 'Geef het park een naam.');
            redirect('/parken/nieuw');
        }
        $location = trim((string) ($_POST['location'] ?? ''));
        $attentionPoints = trim((string) ($_POST['attention_points'] ?? ''));
        $id = (new Park())->create($name, $location, $attentionPoints);
        redirect('/parken/' . $id);
    }

    public function show(string $id): void
    {
        $this->auth();
        $park = (new Park())->find((int) $id);
        if (!$park) {
            http_response_code(404);
            view('errors/404', ['title' => 'Park niet gevonden']);
            return;
        }
        $category = in_array($_GET['category'] ?? '', ['personeel', 'park', 'gasten', 'taken'], true)
            ? (string) $_GET['category']
            : null;
        view('parks/show', [
            'title' => $park['name'],
            'park' => $park,
            'staff' => (new Person())->forPark((int) $id, 'staff'),
            'guests' => (new Person())->forPark((int) $id, 'guest'),
            'items' => (new Item())->forPark((int) $id, $category),
            'category' => $category,
        ]);
    }

    public function edit(string $id): void
    {
        $this->auth();
        $park = (new Park())->find((int) $id);
        if (!$park) {
            http_response_code(404);
            view('errors/404', ['title' => 'Park niet gevonden']);
            return;
        }
        view('parks/form', ['title' => 'Park bewerken', 'park' => $park]);
    }

    public function update(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            flash('error', 'Geef het park een naam.');
            redirect('/parken/' . $id . '/bewerken');
        }
        $location = trim((string) ($_POST['location'] ?? ''));
        $attentionPoints = trim((string) ($_POST['attention_points'] ?? ''));
        (new Park())->update((int) $id, $name, $location, $attentionPoints);
        redirect('/parken/' . $id);
    }

    public function delete(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        (new Park())->delete((int) $id);
        flash('success', 'Het park is verwijderd.');
        redirect('/parken');
    }
}
