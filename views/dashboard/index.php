<?php

use App\Support\View;

/** @var array|null $period */
/** @var float|null $balance */
/** @var array $pots */

$spaarpotjesTotal = array_sum(array_map(
    static fn ($p) => (float) $p['resolved_amount'],
    array_filter($pots, static fn ($p) => ($p['type'] ?? 'leefpotje') === 'spaarpotje')
));
?>
<?php if ($period): ?>
    <h2>Totaal kapitaal</h2>
    <div class="grid-halves">
        <div class="stat">
            <div class="label">Losse rekening</div>
            <div class="value <?= $balance !== null && $balance < 0 ? 'negative' : 'positive' ?>"><?= View::money($balance) ?></div>
        </div>
        <div class="stat">
            <div class="label">Spaarpotjes</div>
            <div class="value positive"><?= View::money($spaarpotjesTotal) ?></div>
        </div>
    </div>
<?php else: ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php endif; ?>
