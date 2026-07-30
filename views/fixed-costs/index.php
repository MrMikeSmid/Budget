<?php

use App\Support\View;

View::render('partials/line-items', [
    'periods' => $periods,
    'period' => $period,
    'items' => $items,
    'totals' => $totals,
    'outstanding' => $outstanding,
    'editing' => $editing,
    'listPage' => 'vaste-lasten',
    'savePage' => 'vaste-lasten-save',
    'deletePage' => 'vaste-lasten-delete',
    'statusSuggestions' => ['Betaald', 'Open', 'Volgende maand'],
    'outstandingLabel' => 'Nog openstaand',
    'showRecurrenceOptions' => true,
], null);
