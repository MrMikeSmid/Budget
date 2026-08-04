<?php

use App\Models\Pot;
use App\Support\View;

/** @var array $periods */
/** @var array|null $editing */
?>
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
