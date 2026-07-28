<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Absence;
use App\Models\Item;
use App\Models\Park;
use App\Models\PerformanceReview;
use App\Models\Person;

final class PrintController extends Controller
{
    public function person(string $id): void
    {
        $this->auth();
        $person = (new Person())->find((int) $id);
        if (!$person) {
            http_response_code(404);
            view('errors/404', ['title' => 'Persoon niet gevonden']);
            return;
        }
        view('print/person', [
            'title' => $person['name'],
            'person' => $person,
            'parks' => (new Person())->parksForPerson((int) $id),
            'items' => (new Item())->forPerson((int) $id),
            'absences' => $person['type'] === 'staff' ? (new Absence())->forPerson((int) $id) : [],
            'reviews' => $person['type'] === 'staff' ? (new PerformanceReview())->forPerson((int) $id) : [],
        ], 'print');
    }

    public function park(string $id): void
    {
        $this->auth();
        $park = (new Park())->find((int) $id);
        if (!$park) {
            http_response_code(404);
            view('errors/404', ['title' => 'Park niet gevonden']);
            return;
        }
        view('print/park', [
            'title' => $park['name'],
            'park' => $park,
            'staff' => (new Person())->forPark((int) $id, 'staff'),
            'guests' => (new Person())->forPark((int) $id, 'guest'),
            'items' => (new Item())->forPark((int) $id),
        ], 'print');
    }

    /** Beknopte rapportage voor de Parkmanager: openstaande zaken, verzuim en aankomende gesprekken. */
    public function report(string $id): void
    {
        $this->auth();
        $park = (new Park())->find((int) $id);
        if (!$park) {
            http_response_code(404);
            view('errors/404', ['title' => 'Park niet gevonden']);
            return;
        }
        view('print/report', [
            'title' => $park['name'] . ' · Rapportage',
            'park' => $park,
            'openItems' => (new Item())->openForPark((int) $id),
            'activeAbsences' => (new Absence())->activeForPark((int) $id),
            'upcomingReviews' => (new PerformanceReview())->upcomingForPark((int) $id),
        ], 'print');
    }
}
