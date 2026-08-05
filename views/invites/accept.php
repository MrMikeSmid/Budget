<?php

use App\Support\Csrf;
use App\Support\View;

/** @var string $token */
/** @var array|null $invite */
/** @var array|null $household */
/** @var string $state */
?>
<?php if ($state === 'invalid'): ?>
    <p class="text-muted">Deze uitnodigingslink is niet geldig. Vraag degene die je uitnodigde om een nieuwe link.</p>
    <p><a class="btn secondary" href="<?= View::e(View::url('login')) ?>">Naar inloggen</a></p>
<?php elseif ($state === 'expired'): ?>
    <p class="text-muted">Deze uitnodiging is verlopen. Vraag iemand uit het huishouden om je opnieuw uit te nodigen.</p>
    <p><a class="btn secondary" href="<?= View::e(View::url('login')) ?>">Naar inloggen</a></p>
<?php elseif ($state === 'accepted'): ?>
    <p class="text-muted">Deze uitnodiging is al eerder geaccepteerd. Log gewoon in.</p>
    <p><a class="btn secondary" href="<?= View::e(View::url('login')) ?>">Naar inloggen</a></p>
<?php elseif ($state === 'login'): ?>
    <p class="text-muted">Je bent uitgenodigd voor <strong><?= View::e($household['name']) ?></strong>. Er bestaat al een account met <strong><?= View::e($invite['email']) ?></strong> — log in om lid te worden.</p>
    <form class="inline-form" method="post" action="<?= View::e(View::url('uitnodiging-inloggen')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="token" value="<?= View::e($token) ?>">
        <div class="field">
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" required autofocus>
        </div>
        <button type="submit" class="btn">Inloggen &amp; toevoegen</button>
    </form>
<?php elseif ($state === 'register'): ?>
    <p class="text-muted">Je bent uitgenodigd voor <strong><?= View::e($household['name']) ?></strong>. Maak een account aan met <strong><?= View::e($invite['email']) ?></strong> om lid te worden.</p>
    <form class="inline-form" method="post" action="<?= View::e(View::url('uitnodiging-registreren')) ?>">
        <?= Csrf::field() ?>
        <input type="hidden" name="token" value="<?= View::e($token) ?>">
        <div class="field">
            <label for="name">Naam</label>
            <input type="text" id="name" name="name" required autofocus>
        </div>
        <div class="field">
            <label for="password">Wachtwoord</label>
            <input type="password" id="password" name="password" minlength="8" required>
        </div>
        <button type="submit" class="btn">Account aanmaken &amp; toevoegen</button>
    </form>
<?php endif; ?>
