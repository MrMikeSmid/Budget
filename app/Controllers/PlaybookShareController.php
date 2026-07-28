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
        view('playbooks/share', [
            'title' => $playbook['title'],
            'playbook' => $playbook,
            'steps' => (new PlaybookStep())->forPlaybook((int) $playbook['id']),
            'token' => $token,
        ], 'print');
    }

    public function calendar(string $token): void
    {
        $playbook = (new Playbook())->findByToken($token);
        if (!$playbook) {
            http_response_code(404);
            view('errors/404', ['title' => 'Draaiboek niet gevonden']);
            return;
        }
        $month = playbook_calendar_month(is_string($_GET['maand'] ?? null) ? $_GET['maand'] : null);
        view('playbooks/calendar', [
            'title' => $playbook['title'] . ' · Kalender',
            'playbook' => $playbook,
            'steps' => (new PlaybookStep())->forPlaybook((int) $playbook['id']),
            'month' => $month,
            'monthUrlBase' => url('/gedeeld/' . $token . '/kalender'),
            'backUrl' => url('/gedeeld/' . $token),
        ], 'print');
    }
}
