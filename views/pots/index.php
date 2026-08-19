<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $pots */
/** @var array $periods */
/** @var array|null $period */
/** @var bool $openForm */

$openForm = $openForm ?? false;
$leefpotjes = array_filter($pots, static fn ($p) => ($p['type'] ?? 'leefpotje') === 'leefpotje');
$spaarpotjes = array_filter($pots, static fn ($p) => ($p['type'] ?? 'leefpotje') === 'spaarpotje');
$totalPotjes = array_sum(array_column($leefpotjes, 'resolved_amount')) + array_sum(array_column($spaarpotjes, 'resolved_amount'));
?>
<?php if ($period): ?>
    <div class="hero-balance">
        <div class="hero-balance-label">Totaal in potjes</div>
        <div class="hero-balance-amount"><?= View::money($totalPotjes) ?></div>
    </div>

    <div class="quick-actions">
        <a class="quick-action" href="<?= View::e(View::url('potjes', ['period' => $period['id'], 'open' => 1])) ?>">
            <span class="quick-action-icon"><?= View::navIcon('potjes') ?></span>
            Potje aanmaken
        </a>
    </div>
<?php else: ?>
    <button type="button" class="fab-button" data-toggle-target="add-form-panel" aria-label="Potje toevoegen">+</button>
<?php endif; ?>

<div class="form-panel" id="add-form-panel" <?= $openForm ? '' : 'hidden' ?>>
    <div class="card">
        <h2 class="mt-0">Nieuw potje</h2>
        <?php if ($period): ?>
            <p class="text-muted">Dit potje bestaat vanaf periode "<?= View::e($period['name']) ?>" — in eerdere periodes blijft het onzichtbaar.</p>
        <?php endif; ?>
        <form class="inline-form" method="post" action="<?= View::e(View::url('potjes-save')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="0">
            <input type="hidden" name="period_id" value="<?= (int) ($period['id'] ?? 0) ?>">
            <?php View::render('partials/pot-form', ['periods' => $periods, 'editing' => null], null); ?>
            <button type="submit" class="btn">Toevoegen</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="section-header">
        <h2 class="mt-0">Leefpotjes</h2>
        <span class="text-muted"><?= View::money(array_sum(array_column($leefpotjes, 'resolved_amount'))) ?></span>
    </div>
    <?php if (empty($leefpotjes)): ?>
        <p class="text-muted">Nog geen leefpotjes aangemaakt.</p>
    <?php else: ?>
        <div class="expense-list">
            <?php foreach ($leefpotjes as $pot): ?>
                <a class="expense-list-item" href="<?= View::e(View::url('potje', ['id' => $pot['id']])) ?>">
                    <span class="expense-list-icon">
                        <span class="emoji"><?= View::e($pot['icon'] ?: '💶') ?></span>
                    </span>
                    <span class="expense-list-body">
                        <span class="expense-list-title"><?= View::e($pot['name']) ?></span>
                        <?php if ($pot['linked_period_name']): ?>
                            <span class="expense-list-amount">Gekoppeld: <?= View::e($pot['linked_period_name']) ?></span>
                        <?php elseif ($pot['note']): ?>
                            <span class="expense-list-amount"><?= View::e($pot['note']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="expense-list-value"><?= View::money((float) $pot['resolved_amount']) ?></span>
                </a>
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
        <div class="expense-list">
            <?php foreach ($spaarpotjes as $pot): ?>
                <a class="expense-list-item" href="<?= View::e(View::url('potje', ['id' => $pot['id']])) ?>">
                    <span class="expense-list-icon">
                        <span class="emoji"><?= View::e($pot['icon'] ?: '💶') ?></span>
                    </span>
                    <span class="expense-list-body">
                        <span class="expense-list-title"><?= View::e($pot['name']) ?></span>
                        <?php if ($pot['linked_period_name']): ?>
                            <span class="expense-list-amount">Gekoppeld: <?= View::e($pot['linked_period_name']) ?></span>
                        <?php elseif ($pot['note']): ?>
                            <span class="expense-list-amount"><?= View::e($pot['note']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span class="expense-list-value"><?= View::money((float) $pot['resolved_amount']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
