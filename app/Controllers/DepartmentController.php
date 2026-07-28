<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Department;

final class DepartmentController extends Controller
{
    public function index(): void
    {
        $this->auth();
        view('departments/index', ['title' => 'Afdelingen', 'departments' => (new Department())->all()]);
    }

    public function create(): void
    {
        $this->auth();
        view('departments/form', ['title' => 'Nieuwe afdeling', 'department' => null]);
    }

    public function store(): void
    {
        $this->auth();
        $this->verifyCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            flash('error', 'Geef de afdeling een naam.');
            redirect('/afdelingen/nieuw');
        }
        $description = trim((string) ($_POST['description'] ?? ''));
        (new Department())->create($name, $description);
        redirect('/afdelingen');
    }

    public function edit(string $id): void
    {
        $this->auth();
        $department = (new Department())->find((int) $id);
        if (!$department) {
            http_response_code(404);
            view('errors/404', ['title' => 'Afdeling niet gevonden']);
            return;
        }
        view('departments/form', ['title' => 'Afdeling bewerken', 'department' => $department]);
    }

    public function update(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            flash('error', 'Geef de afdeling een naam.');
            redirect('/afdelingen/' . $id . '/bewerken');
        }
        $description = trim((string) ($_POST['description'] ?? ''));
        (new Department())->update((int) $id, $name, $description);
        redirect('/afdelingen');
    }

    public function delete(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        (new Department())->delete((int) $id);
        flash('success', 'De afdeling is verwijderd.');
        redirect('/afdelingen');
    }
}
