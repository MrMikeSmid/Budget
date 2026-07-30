<?php

use App\Support\View;

View::render('partials/line-items', [
    'periods' => $periods,
    'period' => $period,
    'items' => $items,
    'totals' => $totals,
    'outstanding' => $outstanding,
    'editing' => $editing,
    'listPage' => 'inkomsten',
    'savePage' => 'inkomsten-save',
    'deletePage' => 'inkomsten-delete',
    'statusSuggestions' => ['Ontvangen', 'Nog te ontvangen'],
    'outstandingLabel' => 'Nog te ontvangen',
], null);
