<?php

use App\Support\View;

/** @var array $periods */
/** @var array|null $period */
/** @var array $incomeTotals */
/** @var array $fixedTotals */
/** @var float $fixedOutstanding */
/** @var float|null $balance */
/** @var float|null $balanceAfterFixedCosts */
/** @var array $recentTransactions */
/** @var array $pots */
?>
<?php View::render('partials/period-switcher', ['periods' => $periods, 'period' => $period, 'page' => 'dashboard'], null); ?>

<?php if ($period): ?>
    <div class="grid-stats">
        <div class="stat">
            <div class="label">Inkomsten begroot</div>
            <div class="value"><?= View::money((float) $incomeTotals['budgeted']) ?></div>
        </div>
        <div class="stat">
            <div class="label">Inkomsten ontvangen</div>
            <div class="value"><?= View::money((float) $incomeTotals['actual']) ?></div>
        </div>
        <div class="stat">
            <div class="label">Vaste lasten begroot</div>
            <div class="value"><?= View::money((float) $fixedTotals['budgeted']) ?></div>
        </div>
        <div class="stat">
            <div class="label">Nog openstaand</div>
            <div class="value negative"><?= View::money($fixedOutstanding) ?></div>
        </div>
    </div>

    <div class="card balance-card">
        <div class="balance-grid">
            <div class="balance-label">Saldo</div>
            <div class="balance-amount <?= $balance !== null && $balance < 0 ? 'negative' : 'positive' ?>"><?= View::money($balance) ?></div>
            <div class="balance-amount <?= $balanceAfterFixedCosts !== null && $balanceAfterFixedCosts < 0 ? 'negative' : 'positive' ?>"><?= View::money($balanceAfterFixedCosts) ?></div>
            <div class="balance-label">na vaste lasten</div>
        </div>
        <p><a href="<?= View::e(View::url('kasstroom', ['period' => $period['id']])) ?>">Bekijk kasstroom &rarr;</a></p>
    </div>

    <div class="card">
        <div class="section-header">
            <h2 class="mt-0">Laatste mutaties</h2>
        </div>
        <?php if (empty($recentTransactions)): ?>
            <p class="text-muted">Nog geen mutaties toegevoegd.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Datum</th><th>Omschrijving</th><th class="num">Mutatie</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentTransactions as $t): ?>
                        <tr>
                            <td><?= View::e($t['txn_date']) ?></td>
                            <td><?= View::e($t['description']) ?></td>
                            <td class="num <?= $t['amount'] < 0 ? 'negative' : 'positive' ?>"><?= View::money((float) $t['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="section-header">
        <h2 class="mt-0">Potjes</h2>
        <a class="btn small secondary" href="<?= View::e(View::url('potjes')) ?>">Beheren</a>
    </div>
    <?php if (empty($pots)): ?>
        <p class="text-muted">Nog geen potjes aangemaakt.</p>
    <?php else: ?>
        <div class="pots-grid">
            <?php foreach ($pots as $pot): ?>
                <div class="pot-card">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span class="pot-icon"><?= View::e($pot['icon'] ?: '💶') ?></span>
                        <div>
                            <div class="pot-name"><?= View::e($pot['name']) ?></div>
                            <?php if ($pot['note']): ?><div class="pot-note"><?= View::e($pot['note']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="pot-amount"><?= View::money((float) $pot['resolved_amount']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
