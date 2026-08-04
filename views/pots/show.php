<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $pot */
/** @var array $ledger */
/** @var array $periods */
/** @var bool $openForm */

$openForm = $openForm ?? false;
?>
<div class="hero-balance">
    <a class="hero-back" href="<?= View::e(View::url('potjes')) ?>" aria-label="Alle potjes">&larr;</a>
    <div class="hero-balance-label"><?= View::e($pot['icon'] ?: '💶') ?> <?= View::e($pot['name']) ?></div>
    <div class="hero-balance-amount"><?= View::money((float) $pot['resolved_amount']) ?></div>
</div>

<div class="quick-actions">
    <button type="button" class="quick-action" data-toggle-target="edit-form-panel" aria-label="Potje bewerken">
        <span class="quick-action-icon" style="font-size:24px;">+</span>
        Potje bewerken
    </button>
</div>

<div class="form-panel" id="edit-form-panel" <?= $openForm ? '' : 'hidden' ?>>
    <div class="card">
        <h2 class="mt-0">Potje bewerken</h2>
        <form class="inline-form" method="post" action="<?= View::e(View::url('potjes-save')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $pot['id'] ?>">
            <input type="hidden" name="period_id" value="0">
            <input type="hidden" name="return" value="potje">
            <?php View::render('partials/pot-form', ['periods' => $periods, 'editing' => $pot], null); ?>
            <button type="submit" class="btn">Opslaan</button>
        </form>
        <form method="post" action="<?= View::e(View::url('potjes-delete')) ?>" onsubmit="return confirm('Potje verwijderen? Het verdwijnt vanaf de huidig gekozen periode, maar blijft in eerdere periodes gewoon bestaan.');" style="margin-top:10px;">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) $pot['id'] ?>">
            <input type="hidden" name="period_id" value="0">
            <button type="submit" class="btn small danger">Verwijderen</button>
        </form>
    </div>
</div>

<?php if ($pot['linked_period_name']): ?>
    <p class="text-muted">Gekoppeld aan periode: <?= View::e($pot['linked_period_name']) ?> (basisbedrag volgt automatisch uit de kasstroom van die periode).</p>
<?php elseif ($pot['note']): ?>
    <p class="text-muted"><?= View::e($pot['note']) ?></p>
<?php endif; ?>

<div class="card">
    <p class="text-muted">Geld in of uit dit potje boeken doe je op de kasstroompagina, via "Uitgave" (met dit potje als bron) of "Overboeken".</p>
    <a class="btn small secondary" href="<?= View::e(View::url('kasstroom')) ?>">Naar kasstroom</a>
</div>

<div class="card">
    <div class="section-header">
        <h2 class="mt-0">Basisbedrag</h2>
        <div class="value"><?= View::money((float) $pot['base_amount']) ?></div>
    </div>
    <?php if (empty($ledger)): ?>
        <p class="text-muted">Nog geen transacties voor dit potje.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th class="nowrap">Datum</th>
                    <th class="nowrap">Omschrijving</th>
                    <th class="nowrap">Bron</th>
                    <th class="num">Bedrag</th>
                    <th class="num">Saldo</th>
                    <th></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($ledger as $t): ?>
                    <?php $isKasstroom = $t['source'] === 'kasstroom'; ?>
                    <tr>
                        <td class="nowrap"><?= View::e($t['txn_date']) ?></td>
                        <td class="nowrap"><?= View::e($t['description']) ?></td>
                        <td class="nowrap">
                            <?php if ($isKasstroom): ?>
                                <a href="<?= View::e(View::url('kasstroom', ['period' => $t['period_id']])) ?>">💳 <?= View::e($t['period_name'] ?? 'Kasstroom') ?></a>
                            <?php else: ?>
                                <?= View::e($t['user_name'] ?? '-') ?>
                            <?php endif; ?>
                        </td>
                        <td class="num <?= $t['amount'] < 0 ? 'negative' : 'positive' ?>"><?= View::money((float) $t['amount']) ?></td>
                        <td class="num"><?= View::money((float) $t['balance']) ?></td>
                        <?php if ($isKasstroom): ?>
                            <td>
                                <a class="btn small secondary" href="<?= View::e(View::url('kasstroom', ['period' => $t['period_id'], 'edit' => $t['id']])) ?>">Bewerken</a>
                            </td>
                            <td>
                                <form method="post" action="<?= View::e(View::url('kasstroom-delete')) ?>" onsubmit="return confirm('Mutatie verwijderen?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="period_id" value="<?= (int) $t['period_id'] ?>">
                                    <button type="submit" class="btn small danger">Verwijderen</button>
                                </form>
                            </td>
                        <?php else: ?>
                            <td></td>
                            <td>
                                <form method="post" action="<?= View::e(View::url('potje-transactie-delete')) ?>" onsubmit="return confirm('Transactie verwijderen?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="pot_id" value="<?= (int) $pot['id'] ?>">
                                    <button type="submit" class="btn small danger">Verwijderen</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
