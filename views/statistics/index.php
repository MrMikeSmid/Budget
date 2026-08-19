<?php

use App\Support\Charts;
use App\Support\View;

/** @var array $periods */
/** @var int $fromId */
/** @var int $toId */
/** @var array $buckets */
/** @var array $totals */
/** @var array $pots */

$labels = array_column($buckets, 'name');
$incomeSeries = array_map(static fn ($b) => (float) $b['income_actual'], $buckets);
$fixedSeries = array_map(static fn ($b) => (float) $b['fixed_actual'], $buckets);
$netSeries = array_map(static fn ($b) => (float) $b['income_actual'] - (float) $b['fixed_actual'], $buckets);

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
<?php if (empty($periods)): ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php else: ?>
    <div class="card">
        <h2 class="mt-0">Periode</h2>
        <form class="inline-form" method="get" action="index.php">
            <input type="hidden" name="page" value="statistieken">
            <div class="field-row">
                <div class="field">
                    <label for="from">Van</label>
                    <select id="from" name="from" onchange="this.form.submit()">
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= $fromId === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= View::e($p['name']) ?><?= $p['is_active'] ? ' (actief)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="to">Tot en met</label>
                    <select id="to" name="to" onchange="this.form.submit()">
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= $toId === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= View::e($p['name']) ?><?= $p['is_active'] ? ' (actief)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php $periodSuffix = count($buckets) > 1 ? ' (' . count($buckets) . ' periodes)' : ''; ?>
<div class="grid-stats">
    <div class="stat">
        <div class="label">Inkomsten<?= $periodSuffix ?></div>
        <div class="value"><?= View::money($totals['income_actual']) ?></div>
    </div>
    <div class="stat">
        <div class="label">Vaste lasten<?= $periodSuffix ?></div>
        <div class="value"><?= View::money($totals['fixed_actual']) ?></div>
    </div>
    <div class="stat">
        <div class="label">Netto gespaard</div>
        <div class="value <?= $totals['net_actual'] < 0 ? 'negative' : 'positive' ?>"><?= View::money($totals['net_actual']) ?></div>
    </div>
    <div class="stat">
        <div class="label">In potjes (nu)</div>
        <div class="value"><?= View::money($potTotal) ?></div>
    </div>
</div>

<div class="card">
    <h2 class="mt-0">Inkomsten &amp; uitgaven per periode</h2>
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
    <p class="text-muted" style="margin-top:-8px;">Actuele stand, niet gebonden aan de gekozen periode hierboven.</p>
    <?php if (empty($leefpotjeSlices)): ?>
        <p class="text-muted">Nog geen leefpotjes met een positief bedrag.</p>
    <?php else: ?>
        <?= Charts::donutChart($leefpotjeSlices, 220, 'leefpotjes') ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="mt-0">Verdeling spaarpotjes</h2>
    <p class="text-muted" style="margin-top:-8px;">Actuele stand, niet gebonden aan de gekozen periode hierboven.</p>
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
                    <th class="nowrap">Periode</th>
                    <th class="num">Inkomsten begroot</th>
                    <th class="num">Inkomsten werkelijk</th>
                    <th class="num">Lasten begroot</th>
                    <th class="num">Lasten werkelijk</th>
                    <th class="num">Netto</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($buckets as $b): ?>
                    <?php $net = (float) $b['income_actual'] - (float) $b['fixed_actual']; ?>
                    <tr>
                        <td class="nowrap"><?= View::e($b['name']) ?></td>
                        <td class="num"><?= View::money((float) $b['income_budgeted']) ?></td>
                        <td class="num"><?= View::money((float) $b['income_actual']) ?></td>
                        <td class="num"><?= View::money((float) $b['fixed_budgeted']) ?></td>
                        <td class="num"><?= View::money((float) $b['fixed_actual']) ?></td>
                        <td class="num <?= $net < 0 ? 'negative' : 'positive' ?>"><?= View::money($net) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                <tr style="font-weight:700;">
                    <td class="nowrap">Totaal (<?= (int) $totals['period_count'] ?> periodes)</td>
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
