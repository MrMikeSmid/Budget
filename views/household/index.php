<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $household */
/** @var array $members */
/** @var array $pendingInvites */
/** @var array $otherHouseholds */
/** @var array $currentUser */
?>
<button type="button" class="fab-button" data-toggle-target="invite-form-panel" aria-label="Lid uitnodigen">+</button>

<div class="form-panel" id="invite-form-panel" hidden>
    <div class="card">
        <h2 class="mt-0">Uitnodigen</h2>
        <form class="inline-form" method="post" action="<?= View::e(View::url('huishouden-uitnodigen')) ?>">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="email">E-mailadres</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" class="btn">Uitnodiging versturen</button>
        </form>
    </div>
</div>

<div class="card">
    <h2 class="mt-0">Huishouden hernoemen</h2>
    <form class="inline-form" method="post" action="<?= View::e(View::url('huishouden-hernoemen')) ?>">
        <?= Csrf::field() ?>
        <div class="field">
            <label for="name">Naam</label>
            <input type="text" id="name" name="name" value="<?= View::e($household['name']) ?>" required>
        </div>
        <button type="submit" class="btn secondary">Opslaan</button>
    </form>
</div>

<div class="card">
    <h2 class="mt-0">Leden</h2>
    <div class="table-scroll">
        <table>
            <thead><tr><th class="nowrap">Naam</th><th>E-mail</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td class="nowrap"><?= View::e($m['name']) ?><?= (int) $m['id'] === (int) $currentUser['id'] ? ' (jij)' : '' ?></td>
                    <td><?= View::e($m['email']) ?></td>
                    <td>
                        <form method="post" action="<?= View::e(View::url('huishouden-verwijderen')) ?>" onsubmit="return confirm('Dit lid uit het huishouden verwijderen?');">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                            <button type="submit" class="btn small danger">Verwijderen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($pendingInvites): ?>
<div class="card">
    <h2 class="mt-0">Openstaande uitnodigingen</h2>
    <div class="table-scroll">
        <table>
            <thead><tr><th>E-mail</th><th class="nowrap">Verloopt</th></tr></thead>
            <tbody>
            <?php foreach ($pendingInvites as $invite): ?>
                <tr>
                    <td><?= View::e($invite['email']) ?></td>
                    <td class="nowrap"><?= View::e($invite['expires_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($otherHouseholds): ?>
<div class="card">
    <h2 class="mt-0">Wissel van huishouden</h2>
    <p class="text-muted">Je bent lid van meerdere huishoudens.</p>
    <?php foreach ($otherHouseholds as $h): ?>
        <form class="inline-form" method="post" action="<?= View::e(View::url('huishouden-wisselen')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="household_id" value="<?= (int) $h['id'] ?>">
            <button type="submit" class="btn secondary"><?= View::e($h['name']) ?></button>
        </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>
