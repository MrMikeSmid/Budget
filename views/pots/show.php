<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $pot */
/** @var array|null $period */
/** @var array $ledger */
/** @var array|null $editing */
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
    <h2 class="mt-0"><?= $editing ? 'Transactie bewerken' : 'Transactie toevoegen' ?></h2>
    <form class="inline-form" method="post" action="<?= View::e(View::url('potje-transactie-save')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <input type="hidden" name="pot_id" value="<?= (int) $pot['id'] ?>">
        <input type="hidden" name="period_id" value="<?= (int) ($period['id'] ?? 0) ?>">
        <div class="field-row">
            <div class="field">
                <label for="txn_date">Datum</label>
                <input type="date" id="txn_date" name="txn_date" required value="<?= View::e($editing['txn_date'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="field">
                <label for="amount">Bedrag (+ / -)</label>
                <input type="number" step="0.01" id="amount" name="amount" required value="<?= View::e((string) ($editing['amount'] ?? '')) ?>">
            </div>
        </div>
        <div class="field">
            <label for="description">Omschrijving</label>
            <input type="text" id="description" name="description" required value="<?= View::e($editing['description'] ?? '') ?>">
        </div>
        <?php if (!$editing && $period): ?>
            <p class="text-muted">Een storting (+) gaat van het saldo van periode "<?= View::e($period['name']) ?>" af, een opname (-) komt er weer bij.</p>
        <?php endif; ?>
        <button type="submit" class="btn"><?= $editing ? 'Opslaan' : 'Toevoegen' ?></button>
        <?php if ($editing): ?>
            <a class="btn secondary" href="<?= View::e(View::url('potje', ['id' => $pot['id']])) ?>">Annuleren</a>
        <?php endif; ?>
    </form>
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
                            <td>
                                <a class="btn small secondary" href="<?= View::e(View::url('potje', ['id' => $pot['id'], 'edit' => $t['id']])) ?>">Bewerken</a>
                            </td>
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
