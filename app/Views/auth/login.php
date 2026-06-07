<section class="auth-page">
    <div class="auth-brand"><span class="brand-mark"><i></i><i></i><i></i></span><span>Samen</span></div>
    <div class="auth-art" aria-hidden="true">
        <div class="orbit orbit-one"><span>✓</span></div><div class="orbit orbit-two"><span>♡</span></div>
        <div class="people"><div class="person person-a"><i></i></div><div class="person person-b"><i></i></div><div class="person person-c"><i></i></div></div>
        <div class="spark spark-a">✦</div><div class="spark spark-b">✦</div>
    </div>
    <div class="auth-copy">
        <span class="eyebrow">Samen krijg je meer gedaan</span>
        <h1><?= $step === 'password' ? 'Fijn dat je er weer bent.' : 'Van plan naar gedaan.' ?></h1>
        <p><?= $step === 'password' ? 'Je account is beveiligd. Vul je wachtwoord in om verder te gaan.' : 'Maak lijstjes, nodig je favoriete mensen uit en vink samen ieder plan af.' ?></p>
    </div>
    <?php if (!empty($error)): ?><div class="form-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($step === 'password'): ?>
        <form method="post" action="<?= e(url('/login/password')) ?>" class="auth-form">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Wachtwoord voor <?= e($email) ?></span><input type="password" name="password" autocomplete="current-password" required autofocus placeholder="Je wachtwoord"></label>
            <button class="button button--primary button--wide">Inloggen <span class="ui-icon ui-icon--arrow-right" aria-hidden="true"></span></button>
            <a class="text-link" href="<?= e(url('/login')) ?>">Ander e-mailadres gebruiken</a>
        </form>
    <?php else: ?>
        <form method="post" action="<?= e(url('/login')) ?>" class="auth-form">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Jouw e-mailadres</span><input type="email" name="email" value="<?= e($email ?? '') ?>" autocomplete="email" inputmode="email" required autofocus placeholder="jij@voorbeeld.nl"></label>
            <button class="button button--primary button--wide">Begin met Samen <span class="ui-icon ui-icon--arrow-right" aria-hidden="true"></span></button>
            <p class="microcopy"><span>⌁</span> Nog geen account? Dat maken we vanzelf voor je.</p>
        </form>
    <?php endif; ?>
</section>
