<div class="auth-page">
    <div class="auth-card">
        <span class="eyebrow">Welkom</span>
        <h1>Stel je account in</h1>
        <p class="section-intro">Dit is een eenmalige stap. Er is maar één account voor deze app.</p>
        <?php foreach (pull_flashes() as $type => $message): ?>
            <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('/setup')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Naam</span><input name="name" required autofocus></label>
            <label class="field"><span>E-mailadres</span><input type="email" name="email" required></label>
            <label class="field"><span>Wachtwoord</span><input type="password" name="password" minlength="8" required></label>
            <label class="field"><span>Herhaal wachtwoord</span><input type="password" name="password_confirmation" minlength="8" required></label>
            <button class="button button--primary button--wide" type="submit">Account aanmaken</button>
        </form>
    </div>
</div>
