<?php

use App\Support\Charts;
use App\Support\Csrf;
use App\Support\View;

/** @var array $periods */
/** @var array|null $period */
/** @var float|null $balance */
/** @var float $leefpotjesTotal */
/** @var float $spaarpotjesTotal */
/** @var float|null $totalKapitaal */
/** @var float $paidActual */
/** @var float $openBudgeted */
/** @var float $totalPayments */
/** @var float $incomeBudgeted */
/** @var float $incomeActual */
/** @var float $incomeOutstanding */
/** @var float $incomeTotal */
/** @var array $partialLoanPayments */
/** @var array $overpaidCosts */
/** @var array $overreceivedIncome */
/** @var array $categorySpending */

$categorySpendingTotal = array_sum(array_column($categorySpending, 'actual'));
?>
<?php if ($period): ?>
    <div class="hero-balance">
        <div class="hero-balance-label">Uw saldo</div>
        <div class="hero-balance-amount"><?= View::money($balance) ?></div>
    </div>

    <div class="quick-actions">
        <a class="quick-action" href="<?= View::e(View::url('kasstroom', ['period' => $period['id'], 'open' => 1, 'tab' => 'uitgave'])) ?>">
            <span class="quick-action-icon"><?= View::navIcon('uitgave') ?></span>
            Uitgaven
        </a>
        <a class="quick-action" href="<?= View::e(View::url('kasstroom', ['period' => $period['id'], 'open' => 1, 'tab' => 'overboeken'])) ?>">
            <span class="quick-action-icon"><?= View::navIcon('overboeking') ?></span>
            Overboeking
        </a>
        <a class="quick-action" href="<?= View::e(View::url('inkomsten', ['period' => $period['id'], 'open' => 1])) ?>">
            <span class="quick-action-icon"><?= View::navIcon('inkomsten') ?></span>
            Inkomsten
        </a>
    </div>
<?php endif; ?>

<?php if ($period && !empty($overpaidCosts)): ?>
    <div class="warning-card negative">
        <h2 class="mt-0">⚠️ Meer betaald dan begroot</h2>
        <?php foreach ($overpaidCosts as $item): ?>
            <?php $over = (float) $item['actual'] - (float) $item['budgeted']; ?>
            <form method="post" action="<?= View::e(View::url('waarschuwing-dismiss')) ?>" class="warning-row">
                <?= Csrf::field() ?>
                <input type="hidden" name="type" value="fixed_cost">
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
                <input type="hidden" name="transaction_id" value="<?= (int) ($item['linked_transaction_id'] ?? 0) ?>">
                <div class="warning-row-text">
                    <div class="warning-row-title"><?= View::e($item['loan_name'] ?? $item['description']) ?></div>
                    <div class="warning-row-detail">Begroot <?= View::money((float) $item['budgeted']) ?>, werkelijk <?= View::money((float) $item['actual']) ?> (<?= View::money($over) ?> te veel)</div>
                </div>
                <button type="submit" class="btn small">Naar mutatie</button>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($period && !empty($overreceivedIncome)): ?>
    <div class="warning-card positive">
        <h2 class="mt-0">🎉 Meer ontvangen dan begroot</h2>
        <?php foreach ($overreceivedIncome as $item): ?>
            <?php $over = (float) $item['actual'] - (float) $item['budgeted']; ?>
            <form method="post" action="<?= View::e(View::url('waarschuwing-dismiss')) ?>" class="warning-row">
                <?= Csrf::field() ?>
                <input type="hidden" name="type" value="income">
                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
                <input type="hidden" name="transaction_id" value="<?= (int) ($item['linked_transaction_id'] ?? 0) ?>">
                <div class="warning-row-text">
                    <div class="warning-row-title"><?= View::e($item['description']) ?></div>
                    <div class="warning-row-detail">Begroot <?= View::money((float) $item['budgeted']) ?>, werkelijk <?= View::money((float) $item['actual']) ?> (<?= View::money($over) ?> meer ontvangen)</div>
                </div>
                <button type="submit" class="btn small">Naar mutatie</button>
            </form>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($period && !empty($partialLoanPayments)): ?>
    <div class="attention-card">
        <h2 class="mt-0">Gedeeltelijk betaalde leningtermijn<?= count($partialLoanPayments) > 1 ? 'en' : '' ?></h2>
        <?php foreach ($partialLoanPayments as $payment): ?>
            <?php $remaining = (float) $payment['budgeted'] - (float) $payment['actual']; ?>
            <div class="attention-row">
                <div class="attention-row-title"><?= View::e($payment['loan_name']) ?></div>
                <div class="attention-row-detail">
                    <span>Betaald: <?= View::money((float) $payment['actual']) ?></span>
                    <span>Nog te betalen: <?= View::money($remaining) ?></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($period): ?>
    <div class="card">
        <div class="tab-switch" role="tablist">
            <button type="button" class="tab-btn active" data-tab-target="panel-inkomsten">Inkomsten</button>
            <button type="button" class="tab-btn" data-tab-target="panel-lasten">Lasten</button>
            <button type="button" class="tab-btn" data-tab-target="panel-kapitaal">Kapitaal</button>
        </div>

        <div class="tab-panel" id="panel-inkomsten">
            <table class="detail-table">
                <tbody>
                <tr>
                    <td>Begrote inkomen</td>
                    <td class="num positive"><?= View::money($incomeBudgeted) ?></td>
                </tr>
                <tr>
                    <td>Werkelijke inkomen</td>
                    <td class="num positive"><?= View::money($incomeActual) ?></td>
                </tr>
                <tr>
                    <td>Nog te ontvangen</td>
                    <td class="num positive"><?= View::money($incomeOutstanding) ?></td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <td>Totale inkomen</td>
                    <td class="num positive"><?= View::money($incomeTotal) ?></td>
                </tr>
                </tfoot>
            </table>
        </div>

        <div class="tab-panel" id="panel-lasten" hidden>
            <table class="detail-table">
                <tbody>
                <tr>
                    <td>Betaald werkelijk</td>
                    <td class="num negative"><?= View::money($paidActual) ?></td>
                </tr>
                <tr>
                    <td>Open begroot</td>
                    <td class="num negative"><?= View::money($openBudgeted) ?></td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <td>Totale betalingen</td>
                    <td class="num negative"><?= View::money($totalPayments) ?></td>
                </tr>
                </tfoot>
            </table>
        </div>

        <div class="tab-panel" id="panel-kapitaal" hidden>
            <table class="detail-table">
                <tbody>
                <tr>
                    <td>Losse rekening</td>
                    <td class="num <?= $balance !== null && $balance < 0 ? 'negative' : 'positive' ?>"><?= View::money($balance) ?></td>
                </tr>
                <tr>
                    <td>Leefpotjes</td>
                    <td class="num positive"><?= View::money($leefpotjesTotal) ?></td>
                </tr>
                <tr>
                    <td>Spaarpotjes</td>
                    <td class="num positive"><?= View::money($spaarpotjesTotal) ?></td>
                </tr>
                </tbody>
                <tfoot>
                <tr>
                    <td>Totaal kapitaal</td>
                    <td class="num <?= $totalKapitaal !== null && $totalKapitaal < 0 ? 'negative' : 'positive' ?>"><?= View::money($totalKapitaal) ?></td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php if (!empty($categorySpending)): ?>
        <div class="card">
            <h2 class="mt-0">Uitgaven per categorie</h2>
            <p class="text-muted" style="margin-top:-8px;">Werkelijk betaalde vaste lasten, verdeeld over de categorieën.</p>
            <div class="category-bars">
                <?php foreach ($categorySpending as $i => $cat): ?>
                    <?php
                        $catActual = (float) $cat['actual'];
                        $percent = $categorySpendingTotal > 0 ? round($catActual / $categorySpendingTotal * 100, 1) : 0;
                        $color = Charts::colorForIndex($i);
                    ?>
                    <a class="category-bar-row" href="<?= View::e(View::url('categorie', ['id' => $cat['category_id'], 'period' => $period['id']])) ?>">
                        <div class="category-bar-label">
                            <span><?= View::e($cat['category_name']) ?></span>
                            <span class="text-muted"><?= View::money($catActual) ?> · <?= View::e((string) $percent) ?>%</span>
                        </div>
                        <div class="category-bar-track">
                            <div class="category-bar-fill" style="width: <?= $percent ?>%; background: <?= $color ?>;"></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php endif; ?>
