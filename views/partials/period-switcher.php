<?php

use App\Support\View;

/** @var array $periods */
/** @var array|null $period */
/** @var string $page */
?>
<?php if (empty($periods)): ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php else: ?>
    <div class="period-switcher">
        <form method="get" action="index.php">
            <input type="hidden" name="page" value="<?= View::e($page) ?>">
            <select name="period" onchange="this.form.submit()">
                <?php foreach ($periods as $p): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= $period && (int) $period['id'] === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= View::e($p['name']) ?><?= $p['is_active'] ? ' (actief)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
<?php endif; ?>
