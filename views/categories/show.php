<?php

use App\Support\View;

/** @var array $category */
/** @var array $periods */
/** @var array|null $period */
/** @var array $incomeItems */
/** @var array $fixedCosts */
/** @var float $incomeBudgeted */
/** @var float $incomeActual */
/** @var float $costsBudgeted */
/** @var float $costsActual */
?>
<p><a href="<?= View::e(View::url('categorieen')) ?>">&larr; Alle categorieën</a></p>

<?php if ($period): ?>
    <div class="hero-balance">
        <div class="hero-balance-label"><?= View::e($category['name']) ?></div>
        <div class="hero-balance-amount"><?= View::money($incomeActual - $costsActual) ?></div>
    </div>

    <div class="grid-stats" style="grid-template-columns: 1fr 1fr;">
        <div class="stat">
            <div class="label">Inkomsten (werkelijk / begroot)</div>
            <div class="value positive"><?= View::money($incomeActual) ?> / <?= View::money($incomeBudgeted) ?></div>
        </div>
        <div class="stat">
            <div class="label">Lasten (werkelijk / begroot)</div>
            <div class="value negative"><?= View::money($costsActual) ?> / <?= View::money($costsBudgeted) ?></div>
        </div>
    </div>

    <div class="card">
        <h2 class="mt-0">Inkomsten</h2>
        <?php if (empty($incomeItems)): ?>
            <p class="text-muted">Geen inkomsten in deze categorie voor deze periode.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th class="nowrap">Omschrijving</th>
                        <th class="num">Begroot</th>
                        <th class="num">Werkelijk</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($incomeItems as $item): ?>
                        <tr class="row-clickable" data-href="<?= View::e(View::url('inkomsten', ['period' => $period['id'], 'edit' => $item['id']])) ?>">
                            <td class="nowrap"><?= View::e($item['description']) ?></td>
                            <td class="num"><?= View::money((float) $item['budgeted']) ?></td>
                            <td class="num"><?= $item['actual'] !== null ? View::money((float) $item['actual']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td>Totaal</td>
                        <td class="num"><?= View::money($incomeBudgeted) ?></td>
                        <td class="num"><?= View::money($incomeActual) ?></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="mt-0">Lasten</h2>
        <?php if (empty($fixedCosts)): ?>
            <p class="text-muted">Geen lasten in deze categorie voor deze periode.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th class="nowrap">Omschrijving</th>
                        <th class="num">Begroot</th>
                        <th class="num">Werkelijk</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($fixedCosts as $item): ?>
                        <tr class="row-clickable" data-href="<?= View::e(View::url('vaste-lasten', ['period' => $period['id'], 'edit' => $item['id']])) ?>">
                            <td class="nowrap">
                                <?= View::e($item['description']) ?>
                                <?php if (!empty($item['loan_id'])): ?> <span class="badge neutral" title="Gekoppeld aan een lening">Lening</span><?php endif; ?>
                            </td>
                            <td class="num"><?= View::money((float) $item['budgeted']) ?></td>
                            <td class="num"><?= $item['actual'] !== null ? View::money((float) $item['actual']) : '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td>Totaal</td>
                        <td class="num"><?= View::money($costsBudgeted) ?></td>
                        <td class="num"><?= View::money($costsActual) ?></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php endif; ?>
