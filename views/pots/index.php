<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $pots */
/** @var array $periods */
/** @var array|null $editing */
?>
<div class="card">
    <h2 class="mt-0"><?= $editing ? 'Potje bewerken' : 'Nieuw potje' ?></h2>
    <form class="inline-form" method="post" action="<?= View::e(View::url('potjes-save')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <div class="field-row">
            <div class="field" style="flex:0 0 90px;">
                <label for="icon">Icoon</label>
                <input type="text" id="icon" name="icon" maxlength="4" value="<?= View::e($editing['icon'] ?? '💶') ?>">
            </div>
            <div class="field">
                <label for="name">Naam</label>
                <input type="text" id="name" name="name" required value="<?= View::e($editing['name'] ?? '') ?>">
            </div>
        </div>
        <div class="field">
            <label for="linked_period_id">Koppelen aan periode (optioneel)</label>
            <select id="linked_period_id" name="linked_period_id" onchange="document.getElementById('amount-field').style.display = this.value ? 'none' : 'block';">
                <option value="">Geen koppeling — handmatig bedrag</option>
                <?php foreach ($periods as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= !empty($editing['linked_period_id']) && (int) $editing['linked_period_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= View::e($p['name']) ?> (eindsaldo kasstroom)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" id="amount-field" style="<?= !empty($editing['linked_period_id']) ? 'display:none;' : '' ?>">
            <label for="amount">Huidige stand</label>
            <input type="number" step="0.01" id="amount" name="amount" value="<?= View::e($editing['amount'] ?? '0') ?>">
        </div>
        <div class="field">
            <label for="note">Opmerking</label>
            <input type="text" id="note" name="note" value="<?= View::e($editing['note'] ?? '') ?>">
        </div>
        <button type="submit" class="btn"><?= $editing ? 'Opslaan' : 'Toevoegen' ?></button>
        <?php if ($editing): ?>
            <a class="btn secondary" href="<?= View::e(View::url('potjes')) ?>">Annuleren</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2 class="mt-0">Alle potjes</h2>
    <?php if (empty($pots)): ?>
        <p class="text-muted">Nog geen potjes aangemaakt.</p>
    <?php else: ?>
        <div class="pots-grid">
            <?php foreach ($pots as $pot): ?>
                <div class="pot-card">
                    <div class="pot-card-info">
                        <span class="pot-icon"><?= View::e($pot['icon'] ?: '💶') ?></span>
                        <div>
                            <div class="pot-name"><?= View::e($pot['name']) ?></div>
                            <?php if ($pot['linked_period_name']): ?>
                                <div class="pot-note">Gekoppeld: <?= View::e($pot['linked_period_name']) ?></div>
                            <?php elseif ($pot['note']): ?>
                                <div class="pot-note"><?= View::e($pot['note']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="pot-card-actions">
                        <div class="pot-amount"><?= View::money((float) $pot['resolved_amount']) ?></div>
                        <div class="row-actions">
                            <a class="btn small" href="<?= View::e(View::url('potje', ['id' => $pot['id']])) ?>">Transacties</a>
                            <a class="btn small secondary" href="<?= View::e(View::url('potjes', ['edit' => $pot['id']])) ?>">Bewerken</a>
                            <form method="post" action="<?= View::e(View::url('potjes-delete')) ?>" onsubmit="return confirm('Potje verwijderen?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $pot['id'] ?>">
                                <button type="submit" class="btn small danger">Verwijderen</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
