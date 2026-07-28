<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Absence;
use App\Models\Person;

final class AbsenceController extends Controller
{
    public function store(string $personId): void
    {
        $this->auth();
        $this->verifyCsrf();
        $person = (new Person())->find((int) $personId);
        if (!$person) {
            http_response_code(404);
            view('errors/404', ['title' => 'Persoon niet gevonden']);
            return;
        }

        [$startDate, $endDate, $reason, $status, $notes, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/personen/' . $personId);
        }

        (new Absence())->create((int) $personId, $startDate, $endDate, $reason, $status, $notes);
        redirect('/personen/' . $personId);
    }

    public function update(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $absence = (new Absence())->find((int) $id);
        if (!$absence) {
            http_response_code(404);
            view('errors/404', ['title' => 'Verzuimregel niet gevonden']);
            return;
        }

        [$startDate, $endDate, $reason, $status, $notes, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/personen/' . $absence['person_id']);
        }

        (new Absence())->update((int) $id, $startDate, $endDate, $reason, $status, $notes);
        redirect('/personen/' . $absence['person_id']);
    }

    public function delete(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $absence = (new Absence())->find((int) $id);
        if (!$absence) {
            http_response_code(404);
            view('errors/404', ['title' => 'Verzuimregel niet gevonden']);
            return;
        }
        (new Absence())->delete((int) $id);
        redirect('/personen/' . $absence['person_id']);
    }

    /** @return array{0:string,1:?string,2:string,3:string,4:string,5:?string} */
    private function readInput(): array
    {
        $startDate = $this->validDate($_POST['start_date'] ?? null);
        $endDate = $this->validDate($_POST['end_date'] ?? null);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $status = in_array($_POST['status'] ?? '', ['lopend', 'hersteld', 'langdurig'], true)
            ? (string) $_POST['status']
            : 'lopend';
        $notes = trim((string) ($_POST['notes'] ?? ''));

        if (!$startDate) {
            return ['', null, $reason, $status, $notes, 'Kies een geldige startdatum.'];
        }
        if ($endDate === false) {
            return ['', null, $reason, $status, $notes, 'Kies een geldige einddatum.'];
        }

        return [$startDate, $endDate, $reason, $status, $notes, null];
    }

    private function validDate(mixed $value): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
        return $date && $date->format('Y-m-d') === (string) $value ? (string) $value : false;
    }
}
