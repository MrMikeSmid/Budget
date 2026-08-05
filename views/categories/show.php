<?php

use App\Models\Category;
use App\Support\Csrf;
use App\Support\View;

/** @var array $category */
/** @var bool $isIncome */
/** @var array $periods */
/** @var array|null $period */
/** @var array $incomeItems */
/** @var array $fixedCosts */
/** @var float $budgeted */
/** @var float $actual */
/** @var float $outstanding */
/** @var bool $openForm */

$openForm = $openForm ?? false;
?>
<div class="hero-balance">
    <a class="hero-back" href="<?= View::e(View::url('categorieen')) ?>" aria-label="Alle categorieën">&larr;</a>
    <button type="button" class="hero-edit" data-toggle-target="edit-form-panel" aria-label="Categorie bewerken">✎</button>
    <div class="hero-balance-label"><?= View::e($category['name']) ?></div>
    <div class="hero-balance-amount"><?= View::money($actual) ?></div>
</div>

<div class="form-panel" id="edit-form-panel" <?= $openForm ? '' : 'hidden' ?>>
    <div class="card">
        <h2 class="mt-0">Categorie bewerken</h2>
        <form class="inline-form" method="post" action="<?= View::e(View::url('categorieen-save')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
            <input type="hidden" name="return" value="categorie">
            <div class="field">
                <label for="name">Naam</label>
                <input type="text" id="name" name="name" required value="<?= View::e($category['name']) ?>">
            </div>
            <div class="field">
                <label for="type">Soort categorie</label>
                <select id="type" name="type">
                    <?php foreach (Category::TYPES as $key => $label): ?>
                        <option value="<?= View::e($key) ?>" <?= $category['type'] === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn">Opslaan</button>
        </form>
        <form method="post" action="<?= View::e(View::url('categorieen-delete')) ?>" onsubmit="return confirm('Categorie verwijderen? Regels die deze categorie hebben, komen zonder categorie te staan.');" style="margin-top:10px;">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
            <button type="submit" class="btn small danger">Verwijderen</button>
        </form>
    </div>
</div>

<?php if ($period): ?>
    <div class="stats-overlap">
        <div class="stat-overlap-item">
            <div class="label">Begroot</div>
            <div class="value"><?= View::money($budgeted) ?></div>
        </div>
        <div class="stat-overlap-item">
            <div class="label"><?= $isIncome ? 'Nog te ontvangen' : 'Nog openstaand' ?></div>
            <div class="value negative"><?= View::money($outstanding) ?></div>
        </div>
    </div>

    <?php if ($isIncome): ?>
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
                            <td class="num"><?= View::money($budgeted) ?></td>
                            <td class="num"><?= View::money($actual) ?></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
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
                            <td class="num"><?= View::money($budgeted) ?></td>
                            <td class="num"><?= View::money($actual) ?></td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php endif; ?>
