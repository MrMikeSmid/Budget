<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $pot */
/** @var array|null $period */
$period = $period ?? null;
?>
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
            <form method="post" action="<?= View::e(View::url('potjes-delete')) ?>" onsubmit="return confirm('Potje verwijderen? Het verdwijnt vanaf de huidig gekozen periode, maar blijft in eerdere periodes gewoon bestaan.');">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $pot['id'] ?>">
                <input type="hidden" name="period_id" value="<?= (int) ($period['id'] ?? 0) ?>">
                <button type="submit" class="btn small danger">Verwijderen</button>
            </form>
        </div>
    </div>
</div>
