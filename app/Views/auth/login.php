<div class="auth-page">
    <div class="auth-card">
        <span class="eyebrow">Regie</span>
        <h1>Inloggen</h1>
        <p class="section-intro">Log in met je e-mailadres en wachtwoord.</p>
        <?php if (!empty($error)): ?><div class="toast toast--error"><span><?= e($error) ?></span></div><?php endif; ?>
        <?php foreach (pull_flashes() as $type => $message): ?>
            <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
        <?php endforeach; ?>
        <form method="post" action="<?= e(url('/login')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>E-mailadres</span><input type="email" name="email" value="<?= e($email ?? '') ?>" required autofocus></label>
            <label class="field"><span>Wachtwoord</span><input type="password" name="password" required></label>
            <button class="button button--primary button--wide" type="submit">Inloggen</button>
        </form>
    </div>
</div>
