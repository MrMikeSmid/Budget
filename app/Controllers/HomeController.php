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
        $listRepository = new TodoList();
        view('lists/index', [
            'title' => 'Mijn lijstjes',
            'user' => $user,
            'lists' => $listRepository->forUser((int) $user['id']),
            'overdueTasks' => $listRepository->overdueTasksForUser((int) $user['id']),
        ]);
    }
}
