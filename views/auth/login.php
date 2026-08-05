<?php

use App\Support\Csrf;
use App\Support\View;
?>
<form class="inline-form" method="post" action="<?= View::e(View::url('login')) ?>">
    <?= Csrf::field() ?>
    <div class="field">
        <label for="email">E-mailadres</label>
        <input type="email" id="email" name="email" required autofocus>
    </div>
    <div class="field">
        <label for="password">Wachtwoord</label>
        <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn">Inloggen</button>
</form>
<p class="text-muted"><a href="<?= View::e(View::url('registreren')) ?>">Nog geen account? Registreer hier</a></p>
