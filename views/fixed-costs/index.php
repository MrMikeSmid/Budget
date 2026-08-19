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
    'statusSuggestions' => ['Open', 'Betaald'],
    'defaultStatus' => 'Open',
    'outstandingLabel' => 'Nog openstaand',
    'showRecurrenceOptions' => true,
    'showHero' => true,
    'heroLabel' => 'Werkelijke lasten',
    'heroValue' => (float) $totals['actual'],
    'quickActionIcon' => 'vaste-lasten',
    'quickActionLabel' => 'Last toevoegen',
    'categories' => $categories,
    'groupByCategory' => false,
    'iconCards' => true,
    'iconMap' => $iconMap,
    'attentionMessage' => ($period && !empty($period['closed_at']))
        ? 'Deze periode is afgesloten — bewerken kan nog gewoon, maar er kan niet nogmaals afgesloten worden.'
        : null,
], null);

if ($period && empty($period['closed_at'])): ?>
<div class="card">
    <a class="btn danger block" href="<?= View::e(View::url('periode-afsluiten', ['period' => $period['id']])) ?>">Periode afsluiten</a>
</div>
<?php endif; ?>
