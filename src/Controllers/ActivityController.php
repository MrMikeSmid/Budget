<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Support\View;

final class ActivityController
{
    public static function index(): void
    {
        View::render('activity/index', [
            'activities' => Activity::recent(200),
        ]);
    }
}
