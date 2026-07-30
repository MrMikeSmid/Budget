<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $periods */
/** @var array|null $editing */
?>
<div class="card">
    <h2 class="mt-0"><?= $editing ? 'Periode bewerken' : 'Nieuwe periode' ?></h2>
    <form class="inline-form" method="post" action="<?= View::e(View::url('periods-save')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
        <div class="field">
            <label for="name">Naam</label>
            <input type="text" id="name" name="name" required value="<?= View::e($editing['name'] ?? '') ?>" placeholder="Bijv. Juli - augustus 2026">
        </div>
        <div class="field-row">
            <div class="field">
                <label for="start_date">Startdatum</label>
                <input type="date" id="start_date" name="start_date" required value="<?= View::e($editing['start_date'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="end_date">Einddatum</label>
                <input type="date" id="end_date" name="end_date" required value="<?= View::e($editing['end_date'] ?? '') ?>">
            </div>
        </div>
        <div class="field">
            <label for="opening_balance">Beginstand</label>
            <input type="number" step="0.01" id="opening_balance" name="opening_balance" value="<?= View::e((string) ($editing['opening_balance'] ?? '0')) ?>">
        </div>
        <div class="checkbox-field">
            <input type="checkbox" id="is_active" name="is_active" <?= !empty($editing['is_active']) ? 'checked' : '' ?>>
            <label for="is_active">Actieve periode</label>
        </div>
        <button type="submit" class="btn"><?= $editing ? 'Opslaan' : 'Toevoegen' ?></button>
        <?php if ($editing): ?>
            <a class="btn secondary" href="<?= View::e(View::url('periods')) ?>">Annuleren</a>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2 class="mt-0">Alle periodes</h2>
    <?php if (empty($periods)): ?>
        <p class="text-muted">Nog geen periodes aangemaakt.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Naam</th><th>Periode</th><th class="num">Beginstand</th><th></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($periods as $p): ?>
                    <tr>
                        <td><?= View::e($p['name']) ?> <?php if ($p['is_active']): ?><span class="badge paid">actief</span><?php endif; ?></td>
                        <td><?= View::e($p['start_date']) ?> t/m <?= View::e($p['end_date']) ?></td>
                        <td class="num"><?= View::money((float) $p['opening_balance']) ?></td>
                        <td>
                            <a class="btn small secondary" href="<?= View::e(View::url('periods', ['edit' => $p['id']])) ?>">Bewerken</a>
                        </td>
                        <td>
                            <div class="row-actions">
                                <?php if (!$p['is_active']): ?>
                                <form method="post" action="<?= View::e(View::url('periods-activate')) ?>">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="btn small secondary">Activeren</button>
                                </form>
                                <?php endif; ?>
                                <form method="post" action="<?= View::e(View::url('periods-delete')) ?>" onsubmit="return confirm('Periode en alle bijbehorende regels verwijderen?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
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
