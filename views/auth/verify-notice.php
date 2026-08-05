<?php

use App\Support\View;

/** @var bool $invalid */
/** @var string|null $email */
/** @var bool|null $mailSent */
/** @var string|null $verifyUrl */
$invalid = $invalid ?? false;
?>
<?php if ($invalid): ?>
    <p class="text-muted">Deze bevestigingslink is ongeldig of verlopen. Vraag desnoods opnieuw een account aan, of neem contact op als je denkt dat dit een fout is.</p>
    <p><a class="btn secondary" href="<?= View::e(View::url('login')) ?>">Naar inloggen</a></p>
<?php elseif ($mailSent): ?>
    <p class="text-muted">We hebben een bevestigingsmail gestuurd naar <strong><?= View::e($email) ?></strong>. Klik op de link in die mail om je account te activeren.</p>
    <p><a class="btn secondary" href="<?= View::e(View::url('login')) ?>">Naar inloggen</a></p>
<?php else: ?>
    <p class="text-muted">Er is nog geen e-mail ingesteld op deze installatie, dus we konden geen bevestigingsmail sturen naar <strong><?= View::e($email) ?></strong>. Klik zelf op onderstaande link om je account te activeren:</p>
    <p><a class="btn" href="<?= View::e($verifyUrl) ?>">Account bevestigen</a></p>
<?php endif; ?>
