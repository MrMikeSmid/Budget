<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Item;
use App\Models\Park;

final class HomeController extends Controller
{
    public function index(): void
    {
        $user = $this->auth();
        $parks = (new Park())->all();
        $items = new Item();
        view('home/index', [
            'title' => 'Overzicht',
            'user' => $user,
            'parks' => $parks,
            'dueSoon' => $items->dueSoon(7),
            'openCounts' => $items->openCountsByPark(),
        ]);
    }
}
