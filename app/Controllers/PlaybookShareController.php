<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Playbook;
use App\Models\PlaybookStep;

/**
 * Public, unauthenticated read-only view of a playbook via its share token.
 * Deliberately never calls $this->auth() — this is the one intentionally
 * public endpoint in the app, so it must stay strictly read-only.
 */
final class PlaybookShareController extends Controller
{
    public function show(string $token): void
    {
        $playbook = (new Playbook())->findByToken($token);
        if (!$playbook) {
            http_response_code(404);
            view('errors/404', ['title' => 'Draaiboek niet gevonden']);
            return;
        }
        $steps = (new PlaybookStep())->forPlaybook((int) $playbook['id']);
        [$calendarStart, $calendarEnd] = playbook_calendar_range($steps);
        view('playbooks/share', [
            'title' => $playbook['title'],
            'playbook' => $playbook,
            'steps' => $steps,
            'calendarStart' => $calendarStart,
            'calendarEnd' => $calendarEnd,
        ], 'print');
    }
}
