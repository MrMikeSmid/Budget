<?php

use App\Models\Pot;
use App\Support\Csrf;
use App\Support\View;

/** @var array $pots */
/** @var array $periods */
/** @var array|null $editing */

$leefpotjes = array_filter($pots, static fn ($p) => ($p['type'] ?? 'leefpotje') === 'leefpotje');
$spaarpotjes = array_filter($pots, static fn ($p) => ($p['type'] ?? 'leefpotje') === 'spaarpotje');
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
            <label for="type">Soort potje</label>
            <select id="type" name="type">
                <?php foreach (Pot::TYPES as $key => $label): ?>
                    <option value="<?= View::e($key) ?>" <?= ($editing['type'] ?? 'leefpotje') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
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
    <div class="section-header">
        <h2 class="mt-0">Leefpotjes</h2>
        <span class="text-muted"><?= View::money(array_sum(array_column($leefpotjes, 'resolved_amount'))) ?></span>
    </div>
    <?php if (empty($leefpotjes)): ?>
        <p class="text-muted">Nog geen leefpotjes aangemaakt.</p>
    <?php else: ?>
        <div class="pots-grid">
            <?php foreach ($leefpotjes as $pot): ?>
                <?php View::render('partials/pot-card', ['pot' => $pot], null); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="section-header">
        <h2 class="mt-0">Spaarpotjes</h2>
        <span class="text-muted"><?= View::money(array_sum(array_column($spaarpotjes, 'resolved_amount'))) ?></span>
    </div>
    <?php if (empty($spaarpotjes)): ?>
        <p class="text-muted">Nog geen spaarpotjes aangemaakt.</p>
    <?php else: ?>
        <div class="pots-grid">
            <?php foreach ($spaarpotjes as $pot): ?>
                <?php View::render('partials/pot-card', ['pot' => $pot], null); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
