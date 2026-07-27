<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\PerformanceReview;
use App\Models\Person;

final class ReviewController extends Controller
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

        [$reviewDate, $type, $summary, $followUpDate, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/personen/' . $personId);
        }

        (new PerformanceReview())->create((int) $personId, $reviewDate, $type, $summary, $followUpDate);
        redirect('/personen/' . $personId);
    }

    public function update(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $review = (new PerformanceReview())->find((int) $id);
        if (!$review) {
            http_response_code(404);
            view('errors/404', ['title' => 'Gesprek niet gevonden']);
            return;
        }

        [$reviewDate, $type, $summary, $followUpDate, $error] = $this->readInput();
        if ($error !== null) {
            flash('error', $error);
            redirect('/personen/' . $review['person_id']);
        }

        (new PerformanceReview())->update((int) $id, $reviewDate, $type, $summary, $followUpDate);
        redirect('/personen/' . $review['person_id']);
    }

    public function delete(string $id): void
    {
        $this->auth();
        $this->verifyCsrf();
        $review = (new PerformanceReview())->find((int) $id);
        if (!$review) {
            http_response_code(404);
            view('errors/404', ['title' => 'Gesprek niet gevonden']);
            return;
        }
        (new PerformanceReview())->delete((int) $id);
        redirect('/personen/' . $review['person_id']);
    }

    /** @return array{0:string,1:string,2:string,3:?string,4:?string} */
    private function readInput(): array
    {
        $reviewDate = $this->validDate($_POST['review_date'] ?? null);
        $type = in_array($_POST['type'] ?? '', ['functioneringsgesprek', 'beoordelingsgesprek', 'proefperiode', 'overig'], true)
            ? (string) $_POST['type']
            : 'functioneringsgesprek';
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $followUpDate = $this->validDate($_POST['follow_up_date'] ?? null);

        if (!$reviewDate) {
            return ['', $type, $summary, null, 'Kies een geldige gespreksdatum.'];
        }
        if ($followUpDate === false) {
            return ['', $type, $summary, null, 'Kies een geldige vervolgdatum.'];
        }

        return [$reviewDate, $type, $summary, $followUpDate, null];
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
