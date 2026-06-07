<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class LegalController extends Controller
{
    public function privacy(): void
    {
        view('legal/privacy', ['title' => 'Privacy']);
    }

    public function terms(): void
    {
        view('legal/terms', ['title' => 'Voorwaarden']);
    }
}
