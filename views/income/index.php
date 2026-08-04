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
    'listPage' => 'inkomsten',
    'savePage' => 'inkomsten-save',
    'deletePage' => 'inkomsten-delete',
    'statusSuggestions' => ['Ontvangen', 'Nog te ontvangen'],
    'outstandingLabel' => 'Nog te ontvangen',
    'showRecurrenceOptions' => true,
    'showHero' => true,
    'heroLabel' => 'Nog te ontvangen',
], null);
