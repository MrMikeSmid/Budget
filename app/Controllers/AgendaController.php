<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Absence;
use App\Models\Item;
use App\Models\PerformanceReview;
use App\Models\PlaybookStep;

final class AgendaController extends Controller
{
    public function index(): void
    {
        $this->auth();
        $month = playbook_calendar_month(is_string($_GET['maand'] ?? null) ? $_GET['maand'] : null);
        view('agenda/index', [
            'title' => 'Agenda',
            'entries' => $this->collectEntries(),
            'month' => $month,
            'monthUrlBase' => url('/agenda'),
            'backUrl' => url('/'),
        ]);
    }

    private function collectEntries(): array
    {
        $entries = [];
        $today = date('Y-m-d');

        foreach ((new Item())->all() as $item) {
            if ($item['due_date'] === null || $item['status'] === 'gearchiveerd') {
                continue;
            }
            $subtitle = $item['park_name'] . ($item['person_name'] ? ' · ' . $item['person_name'] : '');
            $entries[] = calendar_entry(
                $item['title'],
                $subtitle,
                $item['type'],
                $item['due_date'],
                $item['due_date'],
                url('/items/' . $item['id'] . '/bewerken')
            );
        }

        foreach ((new Absence())->all() as $absence) {
            $entries[] = calendar_entry(
                'Verzuim: ' . $absence['person_name'],
                $absence['reason'] !== '' ? $absence['reason'] : absence_status_label($absence['status']),
                'verzuim',
                $absence['start_date'],
                $absence['end_date'] ?: $today,
                url('/personen/' . $absence['person_id'])
            );
        }

        foreach ((new PerformanceReview())->all() as $review) {
            $personUrl = url('/personen/' . $review['person_id']);
            $entries[] = calendar_entry(
                'Gesprek: ' . $review['person_name'],
                review_type_label($review['type']),
                'gesprek',
                $review['review_date'],
                $review['review_date'],
                $personUrl
            );
            if ($review['follow_up_date']) {
                $entries[] = calendar_entry(
                    'Vervolgafspraak: ' . $review['person_name'],
                    review_type_label($review['type']),
                    'gesprek',
                    $review['follow_up_date'],
                    $review['follow_up_date'],
                    $personUrl
                );
            }
        }

        foreach ((new PlaybookStep())->all() as $step) {
            $entries[] = playbook_step_calendar_entry(
                $step,
                url('/draaiboeken/' . $step['playbook_id']),
                $step['playbook_title']
            );
        }

        usort($entries, fn(array $a, array $b) => $a['start_date'] <=> $b['start_date']);
        return $entries;
    }
}
