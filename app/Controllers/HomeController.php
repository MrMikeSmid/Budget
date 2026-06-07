<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\TodoList;

final class HomeController extends Controller
{
    public function index(): void
    {
        $user = $this->auth();
        $lists = (new TodoList())->forUser((int) $user['id']);
        view('lists/index', ['title' => 'Mijn lijstjes', 'user' => $user, 'lists' => $lists]);
    }
}
