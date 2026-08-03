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
<?php View::render('partials/period-switcher', ['periods' => $periods, 'period' => $period, 'page' => 'dashboard'], null); ?>

<?php if ($period): ?>
    <h2>Totaal kapitaal</h2>
    <div class="card">
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

    <h2>Inkomsten</h2>
    <div class="card">
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

    <h2>Lasten</h2>
    <div class="card">
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
<?php else: ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php endif; ?>
