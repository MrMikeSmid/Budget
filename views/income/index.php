<?php

use App\Models\IncomeItem;
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
    'heroLabel' => 'Werkelijk ontvangen',
    'heroValue' => $period ? IncomeItem::receivedTotal((int) $period['id']) : 0.0,
    'quickActionIcon' => 'inkomsten',
    'quickActionLabel' => 'Inkomst toevoegen',
], null);
