<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $pot */
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
            <form method="post" action="<?= View::e(View::url('potjes-delete')) ?>" onsubmit="return confirm('Potje verwijderen? Het verdwijnt uit je actieve potjes, maar de geschiedenis en de saldi van eerdere periodes blijven ongewijzigd.');">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $pot['id'] ?>">
                <button type="submit" class="btn small danger">Verwijderen</button>
            </form>
        </div>
    </div>
</div>
