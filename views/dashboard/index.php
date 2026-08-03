<?php

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

<?php View::render('partials/period-switcher', ['periods' => $periods, 'period' => $period, 'page' => 'dashboard'], null); ?>

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
<?php else: ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php endif; ?>
