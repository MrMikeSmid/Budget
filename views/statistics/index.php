<?php

use App\Support\Charts;
use App\Support\View;

/** @var string $range */
/** @var array $buckets */
/** @var array $totals */
/** @var array $pots */

$rangeLabels = ['maand' => 'Maand', 'kwartaal' => 'Kwartaal', 'jaar' => 'Jaar'];

$labels = array_column($buckets, 'label');
$incomeSeries = array_map(static fn ($b) => $b['income_actual'], $buckets);
$fixedSeries = array_map(static fn ($b) => $b['fixed_actual'], $buckets);
$netSeries = array_map(static fn ($b) => $b['income_actual'] - $b['fixed_actual'], $buckets);

$leefpotjeSlices = [];
$spaarpotjeSlices = [];
foreach ($pots as $pot) {
    if ((float) $pot['resolved_amount'] <= 0) {
        continue;
    }
    if (($pot['type'] ?? 'leefpotje') === 'spaarpotje') {
        $spaarpotjeSlices[$pot['name']] = (float) $pot['resolved_amount'];
    } else {
        $leefpotjeSlices[$pot['name']] = (float) $pot['resolved_amount'];
    }
}
$potTotal = array_sum($leefpotjeSlices) + array_sum($spaarpotjeSlices);
?>
<div class="range-tabs">
    <?php foreach ($rangeLabels as $key => $label): ?>
        <a href="<?= View::e(View::url('statistieken', ['range' => $key])) ?>" class="<?= $range === $key ? 'active' : '' ?>"><?= View::e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="grid-stats">
    <div class="stat">
        <div class="label">Totaal inkomsten</div>
        <div class="value"><?= View::money($totals['income_actual']) ?></div>
    </div>
    <div class="stat">
        <div class="label">Totaal vaste lasten</div>
        <div class="value"><?= View::money($totals['fixed_actual']) ?></div>
    </div>
    <div class="stat">
        <div class="label">Netto gespaard</div>
        <div class="value <?= $totals['net_actual'] < 0 ? 'negative' : 'positive' ?>"><?= View::money($totals['net_actual']) ?></div>
    </div>
    <div class="stat">
        <div class="label">In potjes</div>
        <div class="value"><?= View::money($potTotal) ?></div>
    </div>
</div>

<div class="card">
    <h2 class="mt-0">Inkomsten &amp; uitgaven per <?= View::e(mb_strtolower($rangeLabels[$range])) ?></h2>
    <?php if (empty($buckets)): ?>
        <p class="text-muted">Nog geen periodes met data.</p>
    <?php else: ?>
        <?= Charts::lineChart($labels, [
            'Inkomsten' => $incomeSeries,
            'Vaste lasten' => $fixedSeries,
            'Netto gespaard' => $netSeries,
        ]) ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="mt-0">Verdeling leefpotjes</h2>
    <?php if (empty($leefpotjeSlices)): ?>
        <p class="text-muted">Nog geen leefpotjes met een positief bedrag.</p>
    <?php else: ?>
        <?= Charts::donutChart($leefpotjeSlices, 220, 'leefpotjes') ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="mt-0">Verdeling spaarpotjes</h2>
    <?php if (empty($spaarpotjeSlices)): ?>
        <p class="text-muted">Nog geen spaarpotjes met een positief bedrag.</p>
    <?php else: ?>
        <?= Charts::donutChart($spaarpotjeSlices, 220, 'spaarpotjes') ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="mt-0">Volledig overzicht</h2>
    <?php if (empty($buckets)): ?>
        <p class="text-muted">Nog geen data.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th><?= View::e($rangeLabels[$range]) ?></th>
                    <th class="num">Inkomsten begroot</th>
                    <th class="num">Inkomsten werkelijk</th>
                    <th class="num">Lasten begroot</th>
                    <th class="num">Lasten werkelijk</th>
                    <th class="num">Netto</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($buckets as $b): ?>
                    <?php $net = $b['income_actual'] - $b['fixed_actual']; ?>
                    <tr>
                        <td><?= View::e($b['label']) ?></td>
                        <td class="num"><?= View::money($b['income_budgeted']) ?></td>
                        <td class="num"><?= View::money($b['income_actual']) ?></td>
                        <td class="num"><?= View::money($b['fixed_budgeted']) ?></td>
                        <td class="num"><?= View::money($b['fixed_actual']) ?></td>
                        <td class="num <?= $net < 0 ? 'negative' : 'positive' ?>"><?= View::money($net) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr style="font-weight:700;">
                    <td>Totaal (<?= (int) $totals['period_count'] ?> periodes)</td>
                    <td class="num"><?= View::money($totals['income_budgeted']) ?></td>
                    <td class="num"><?= View::money($totals['income_actual']) ?></td>
                    <td class="num"><?= View::money($totals['fixed_budgeted']) ?></td>
                    <td class="num"><?= View::money($totals['fixed_actual']) ?></td>
                    <td class="num <?= $totals['net_actual'] < 0 ? 'negative' : 'positive' ?>"><?= View::money($totals['net_actual']) ?></td>
                </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
</div>
