<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Item;
use App\Models\Park;
use App\Models\Person;

final class ItemController extends Controller
{
    private const CATEGORIES = ['personeel', 'park', 'gasten', 'taken'];
    private const TYPES = ['notitie', 'afspraak', 'taak'];

    public function index(): void
    {
        $this->auth();
        $parkId = !empty($_GET['park']) ? (int) $_GET['park'] : null;
        $category = in_array($_GET['category'] ?? '', self::CATEGORIES, true) ? (string) $_GET['category'] : null;
        $status = in_array($_GET['status'] ?? '', ['open', 'in_uitvoering', 'afgerond', 'gearchiveerd'], true)
            ? (string) $_GET['status']
            : 'open';
        view('items/index', [
            'title' => 'Taken & afspraken',
            'parks' => (new Park())->all(),
            'selectedParkId' => $parkId,
            'selectedCategory' => $category,
            'selectedStatus' => $status,
            'items' => (new Item())->all($parkId, $category, $status === 'alle' ? null : $status),
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
        $category = in_array($_GET['category'] ?? '', self::CATEGORIES, true) ? (string) $_GET['category'] : 'park';
        $personId = isset($_GET['person_id']) ? (int) $_GET['person_id'] : null;
        view('items/form', [
            'title' => 'Nieuwe notitie',
            'park' => $park,
            'item' => null,
            'category' => $category,
            'personId' => $personId,
            'people' => (new Person())->forPark((int) $parkId),
        ]);
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

        [$category, $type, $title, $body, $dueDate, $personId, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/parken/' . $parkId . '/items/nieuw?category=' . $category);
        }

        $id = (new Item())->create((int) $parkId, $category, $type, $personId, $title, $body, $dueDate);
        $this->redirectAfterSave($personId, (int) $parkId, $category);
    }

    public function edit(string $id): void
    {
        $this->auth();
        $item = (new Item())->find((int) $id);
        if (!$item) {
            http_response_code(404);
            view('errors/404', ['title' => 'Item niet gevonden']);
            return;
        }
        $park = (new Park())->find((int) $item['park_id']);
        view('items/form', [
            'title' => 'Bewerken',
            'park' => $park,
            'item' => $item,
            'category' => $item['category'],
            'personId' => $item['person_id'] !== null ? (int) $item['person_id'] : null,
            'people' => (new Person())->forPark((int) $item['park_id']),
        ]);
    }

    public function update(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $item = (new Item())->find((int) $id);
        if (!$item) {
            http_response_code(404);
            view('errors/404', ['title' => 'Item niet gevonden']);
            return;
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 160) {
            flash('error', 'Geef het een korte titel.');
            redirect('/items/' . $id . '/bewerken');
        }
        $body = trim((string) ($_POST['body'] ?? ''));
        $dueDate = $this->validDate($_POST['due_date'] ?? null);
        if ($dueDate === false) {
            flash('error', 'Kies een geldige datum.');
            redirect('/items/' . $id . '/bewerken');
        }
        $status = in_array($_POST['status'] ?? '', ['open', 'in_uitvoering', 'afgerond', 'gearchiveerd'], true)
            ? (string) $_POST['status']
            : $item['status'];

        (new Item())->update((int) $id, $title, $body, $dueDate, $status);
        $this->redirectAfterSave(
            $item['person_id'] !== null ? (int) $item['person_id'] : null,
            (int) $item['park_id'],
            (string) $item['category']
        );
    }

    public function toggle(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $item = (new Item())->find((int) $id);
        if (!$item) {
            http_response_code(404);
            view('errors/404', ['title' => 'Item niet gevonden']);
            return;
        }
        (new Item())->toggle((int) $id);
        $this->redirectAfterSave(
            $item['person_id'] !== null ? (int) $item['person_id'] : null,
            (int) $item['park_id'],
            (string) $item['category']
        );
    }

    public function delete(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $item = (new Item())->find((int) $id);
        if (!$item) {
            http_response_code(404);
            view('errors/404', ['title' => 'Item niet gevonden']);
            return;
        }
        (new Item())->delete((int) $id);
        $this->redirectAfterSave(
            $item['person_id'] !== null ? (int) $item['person_id'] : null,
            (int) $item['park_id'],
            (string) $item['category']
        );
    }

    /** @return array{0:string,1:string,2:string,3:string,4:?string,5:?int,6:?string} */
    private function readInput(): array
    {
        $category = in_array($_POST['category'] ?? '', self::CATEGORIES, true) ? (string) $_POST['category'] : 'park';
        $type = in_array($_POST['type'] ?? '', self::TYPES, true) ? (string) $_POST['type'] : 'notitie';
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $personId = !empty($_POST['person_id']) ? (int) $_POST['person_id'] : null;
        $dueDate = $this->validDate($_POST['due_date'] ?? null);

        if ($title === '' || mb_strlen($title) > 160) {
            return [$category, $type, $title, $body, null, $personId, 'Geef het een korte titel.'];
        }
        if ($dueDate === false) {
            return [$category, $type, $title, $body, null, $personId, 'Kies een geldige datum.'];
        }

        return [$category, $type, $title, $body, $dueDate, $personId, null];
    }

    private function validDate(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === (string) $value ? (string) $value : false;
    }

    private function redirectAfterSave(?int $personId, int $parkId, string $category): never
    {
        if ($personId !== null) {
            redirect('/personen/' . $personId);
        }
        redirect('/parken/' . $parkId . '?category=' . $category);
    }
}
