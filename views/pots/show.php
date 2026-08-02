<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $pot */
/** @var array $ledger */
?>
<p><a href="<?= View::e(View::url('potjes')) ?>">&larr; Alle potjes</a></p>

<div class="card">
    <div class="section-header">
        <h2 class="mt-0"><?= View::e($pot['icon'] ?: '💶') ?> <?= View::e($pot['name']) ?></h2>
        <div class="value"><?= View::money((float) $pot['resolved_amount']) ?></div>
    </div>
    <?php if ($pot['linked_period_name']): ?>
        <p class="text-muted">Gekoppeld aan periode: <?= View::e($pot['linked_period_name']) ?> (basisbedrag volgt automatisch uit de kasstroom van die periode).</p>
    <?php elseif ($pot['note']): ?>
        <p class="text-muted"><?= View::e($pot['note']) ?></p>
    <?php endif; ?>
</div>

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
                    <th>Datum</th>
                    <th>Omschrijving</th>
                    <th>Bron</th>
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
                        <td><?= View::e($t['txn_date']) ?></td>
                        <td><?= View::e($t['description']) ?></td>
                        <td>
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
