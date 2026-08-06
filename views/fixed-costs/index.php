<?php

use App\Support\View;

View::render('partials/line-items', [
    'periods' => $periods,
    'period' => $period,
    'items' => $items,
    'totals' => $totals,
    'outstanding' => $outstanding,
    'editing' => $editing,
    'openForm' => $openForm,
    'listPage' => 'vaste-lasten',
    'savePage' => 'vaste-lasten-save',
    'deletePage' => 'vaste-lasten-delete',
    'statusSuggestions' => ['Betaald', 'Open', 'Volgende maand'],
    'defaultStatus' => 'Open',
    'outstandingLabel' => 'Nog openstaand',
    'showRecurrenceOptions' => true,
    'showHero' => true,
    'heroLabel' => 'Werkelijke lasten',
    'heroValue' => (float) $totals['actual'],
    'quickActionIcon' => 'vaste-lasten',
    'quickActionLabel' => 'Last toevoegen',
    'categories' => $categories,
    'groupByCategory' => true,
    'iconCards' => true,
    'iconMap' => $iconMap,
], null);
