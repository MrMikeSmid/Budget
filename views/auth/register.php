<?php

use App\Support\Csrf;
use App\Support\View;
?>
<p class="text-muted">Maak een account aan. Je krijgt meteen je eigen huishouden waar je later anderen voor kan uitnodigen.</p>
<form class="inline-form" method="post" action="<?= View::e(View::url('registreren')) ?>">
    <?= Csrf::field() ?>
    <div class="field">
        <label for="name">Naam</label>
        <input type="text" id="name" name="name" required autofocus>
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
<p class="text-muted"><a href="<?= View::e(View::url('login')) ?>">Heb je al een account? Log in</a></p>
