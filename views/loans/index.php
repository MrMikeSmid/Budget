<?php

use App\Models\FixedCost;
use App\Support\Csrf;
use App\Support\View;

/** @var array $loans */
/** @var array|null $editing */
/** @var bool $hasActivePeriod */
?>
<button type="button" class="fab-button" data-toggle-target="add-form-panel" aria-label="Lening toevoegen">+</button>

<div class="form-panel" id="add-form-panel" <?= $editing ? '' : 'hidden' ?>>
    <div class="card">
        <h2 class="mt-0"><?= $editing ? 'Lening bewerken' : 'Nieuwe lening/schuld' ?></h2>
        <?php if (!$editing && !$hasActivePeriod): ?>
            <p class="text-muted">Maak eerst een <a href="<?= View::e(View::url('periods')) ?>">budgetperiode</a> aan — de eerste termijn van een nieuwe lening wordt automatisch als vaste last op de actieve periode gezet.</p>
        <?php endif; ?>
        <form class="inline-form" method="post" action="<?= View::e(View::url('leningen-save')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <div class="field">
                <label for="name">Naam</label>
                <input type="text" id="name" name="name" required value="<?= View::e($editing['name'] ?? '') ?>" placeholder="Bijv. Persoonlijke lening ABN">
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="total_amount">Totaalbedrag</label>
                    <input type="number" step="0.01" id="total_amount" name="total_amount" required value="<?= View::e((string) ($editing['total_amount'] ?? '')) ?>">
                </div>
                <div class="field">
                    <label for="monthly_payment">Termijnbedrag</label>
                    <input type="number" step="0.01" id="monthly_payment" name="monthly_payment" required value="<?= View::e((string) ($editing['monthly_payment'] ?? '')) ?>">
                </div>
            </div>
            <?php if (!$editing): ?>
                <div class="field">
                    <label for="recurrence_interval">Frequentie van de termijn</label>
                    <select id="recurrence_interval" name="recurrence_interval">
                        <?php foreach (FixedCost::INTERVALS as $key => $label): ?>
                            <option value="<?= View::e($key) ?>" <?= $key === 'maandelijks' ? 'selected' : '' ?>><?= View::e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <p class="text-muted">De frequentie van de termijn stel je in op de vaste last zelf (bij "Vaste lasten").</p>
            <?php endif; ?>
            <div class="field">
                <label for="note">Opmerking</label>
                <input type="text" id="note" name="note" value="<?= View::e($editing['note'] ?? '') ?>">
            </div>
            <button type="submit" class="btn"><?= $editing ? 'Opslaan' : 'Toevoegen' ?></button>
            <?php if ($editing): ?>
                <a class="btn secondary" href="<?= View::e(View::url('leningen')) ?>">Annuleren</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <h2 class="mt-0">Alle leningen</h2>
    <?php if (empty($loans)): ?>
        <p class="text-muted">Nog geen leningen aangemaakt.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead>
                <tr>
                    <th class="nowrap">Naam</th>
                    <th class="num">Totaal</th>
                    <th class="num">Afgelost</th>
                    <th class="num">Nog open</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($loans as $loan): ?>
                    <?php
                    $progress = $loan['total_amount'] > 0
                        ? min(100, round(($loan['paid_amount'] / $loan['total_amount']) * 100))
                        : 0;
                    $isPaidOff = $loan['remaining_amount'] <= 0.005;
                    ?>
                    <tr>
                        <td class="nowrap">
                            <?= View::e($loan['name']) ?>
                            <?php if ($isPaidOff): ?><span class="badge paid">Afgelost</span><?php endif; ?>
                            <div class="progress-bar"><div class="progress-bar-fill" style="width: <?= $progress ?>%"></div></div>
                        </td>
                        <td class="num"><?= View::money((float) $loan['total_amount']) ?></td>
                        <td class="num"><?= View::money((float) $loan['paid_amount']) ?></td>
                        <td class="num <?= $isPaidOff ? 'positive' : '' ?>"><?= View::money((float) $loan['remaining_amount']) ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="btn small secondary" href="<?= View::e(View::url('leningen', ['edit' => $loan['id']])) ?>">Bewerken</a>
                                <form method="post" action="<?= View::e(View::url('leningen-delete')) ?>" onsubmit="return confirm('Lening verwijderen? De al aangemaakte vaste lasten blijven staan, maar de koppeling verdwijnt.');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $loan['id'] ?>">
                                    <button type="submit" class="btn small danger">Verwijderen</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
