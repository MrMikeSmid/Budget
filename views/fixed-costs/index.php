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
    'groupByCategory' => false,
    'iconCards' => true,
    'iconMap' => $iconMap,
], null);

if ($period): ?>
<div class="card">
    <h2 class="mt-0">Periode afsluiten</h2>
    <p class="text-muted">Openstaande lasten en resterend saldo optioneel meenemen naar een andere periode.</p>
    <a class="btn danger" href="<?= View::e(View::url('periode-afsluiten', ['period' => $period['id']])) ?>">Periode afsluiten</a>
</div>
<?php endif; ?>

