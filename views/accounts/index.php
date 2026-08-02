<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $users */
/** @var array $currentUser */
?>
<button type="button" class="fab-button" data-toggle-target="add-form-panel" aria-label="Account toevoegen">+</button>

<div class="form-panel" id="add-form-panel" hidden>
    <div class="card">
        <h2 class="mt-0">Nieuw account</h2>
        <form class="inline-form" method="post" action="<?= View::e(View::url('accounts-save')) ?>">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="name">Naam</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="field">
                <label for="email">E-mailadres</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="field">
                <label for="password">Wachtwoord</label>
                <input type="password" id="password" name="password" minlength="8" required>
            </div>
            <button type="submit" class="btn">Account aanmaken</button>
        </form>
    </div>
</div>

<div class="card">
    <h2 class="mt-0">Accounts</h2>
    <div class="table-scroll">
        <table>
            <thead><tr><th>Naam</th><th>E-mail</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= View::e($u['name']) ?><?= (int) $u['id'] === (int) $currentUser['id'] ? ' (jij)' : '' ?></td>
                    <td><?= View::e($u['email']) ?></td>
                    <td>
                        <?php if ((int) $u['id'] !== (int) $currentUser['id']): ?>
                            <form method="post" action="<?= View::e(View::url('accounts-delete')) ?>" onsubmit="return confirm('Account verwijderen?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn small danger">Verwijderen</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
