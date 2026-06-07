<?php $profileImage = profile_image_url($user); ?>
<section class="settings-page">
<header class="topbar"><div><span class="eyebrow">Jouw plek</span><h1>Instellingen</h1></div><a class="icon-button" href="<?= e(url('/')) ?>">×</a></header>
<div class="profile-card">
    <div class="avatar avatar--large">
        <?php if ($profileImage): ?><img src="<?= e($profileImage) ?>" alt="Profielfoto van <?= e($user['name']) ?>"><?php else: ?><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?><?php endif; ?>
        <span></span>
    </div>
    <div><h2><?= e($user['name']) ?></h2><p><?= e($user['email']) ?></p></div>
    <span class="status-chip <?= $user['password_hash'] ? 'status-chip--safe' : '' ?>"><?= $user['password_hash'] ? 'Beveiligd' : 'Open account' ?></span>
</div>
<?php if(empty($user['password_hash'])): ?><div class="security-card" id="beveiliging"><div class="security-card__icon">♢</div><div><span class="eyebrow">Een kleine aanbeveling</span><h2>Bewaar je lijstjes veilig</h2><p>Je kunt Samen nu gewoon gebruiken. Zonder wachtwoord kan iedereen die jouw e-mailadres kent echter inloggen. Stel er één in voordat je sessie verloopt.</p></div></div><?php endif; ?>
<section class="install-card" data-pwa-install hidden>
    <div class="install-card__icon"><span class="brand-mark"><i></i><i></i><i></i></span></div>
    <div class="install-card__copy"><span class="eyebrow">Samen als app</span><h2>Installeer op je telefoon</h2><p data-install-copy>Zet Samen op je beginscherm en open je lijstjes zonder eerst je browser op te zoeken.</p></div>
    <button type="button" class="button button--primary" data-install-button>Installeren</button>
</section>
<dialog class="modal install-help" id="install-help">
    <div class="modal-card">
        <div class="modal-handle"></div>
        <div class="modal-heading"><div><span class="eyebrow">Op iPhone of iPad</span><h2>Zet Samen op je beginscherm</h2></div><button type="button" class="icon-button" data-close-modal aria-label="Sluiten">×</button></div>
        <ol class="install-steps"><li>Tik in Safari op de knop <strong>Delen</strong> <span aria-hidden="true">⇧</span>.</li><li>Kies <strong>Zet op beginscherm</strong>.</li><li>Tik rechtsboven op <strong>Voeg toe</strong>.</li></ol>
        <button type="button" class="button button--soft button--wide" data-close-modal>Begrepen</button>
    </div>
</dialog>
<?php if (config('onesignal_app_id', '') !== ''): ?>
<div class="settings-section notification-settings" data-push-settings>
    <span class="eyebrow">Op de hoogte blijven</span><h2>Pushnotificaties</h2>
    <p class="section-intro" data-push-status>De notificatie-instellingen worden geladen…</p>
    <button type="button" class="button button--primary" data-push-toggle disabled>Meldingen aanzetten</button>
    <small class="notification-settings__hint">Je krijgt alleen een melding wanneer iemand anders iets wijzigt in een gedeeld lijstje.</small>
</div>
<?php endif; ?>
<div class="settings-section">
    <span class="eyebrow">Over jou</span><h2>Profiel</h2>
    <form method="post" action="<?= e(url('/settings/profile')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <div class="profile-image-field">
            <div class="avatar avatar--large avatar--preview" data-avatar-preview>
                <img src="<?= e($profileImage ?? '') ?>" alt="Voorbeeld van je profielfoto" <?= $profileImage ? '' : 'hidden' ?>>
                <strong <?= $profileImage ? 'hidden' : '' ?>><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></strong>
            </div>
            <div><label class="button button--soft profile-image-button">Kies afbeelding<input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp,image/gif" data-avatar-input></label><small>JPG, PNG, WebP of GIF, maximaal 5 MB.</small></div>
        </div>
        <label class="field"><span>Naam</span><input name="name" value="<?= e($user['name']) ?>" maxlength="60" required></label>
        <label class="field field--disabled"><span>E-mailadres</span><input value="<?= e($user['email']) ?>" disabled><small>Je e-mailadres is ook je unieke ingang tot Samen.</small></label>
        <button class="button button--soft">Wijzigingen bewaren</button>
    </form>
</div>
<div class="settings-section" id="wachtwoord"><span class="eyebrow">Beveiliging</span><h2><?= $user['password_hash'] ? 'Wachtwoord wijzigen' : 'Wachtwoord aanmaken' ?></h2><p class="section-intro"><?= $user['password_hash'] ? 'Kies een nieuw wachtwoord van minimaal 8 tekens.' : 'Hierna vragen we bij iedere nieuwe login naast je e-mailadres ook om dit wachtwoord.' ?></p><form method="post" action="<?= e(url('/settings/password')) ?>"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><label class="field"><span>Nieuw wachtwoord</span><input type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Minimaal 8 tekens" required></label><label class="field"><span>Nog een keer</span><input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" placeholder="Herhaal je wachtwoord" required></label><button class="button button--primary"><?= $user['password_hash'] ? 'Wachtwoord wijzigen' : 'Account beveiligen' ?> <span class="ui-icon ui-icon--arrow-right" aria-hidden="true"></span></button></form></div>
<form method="post" action="<?= e(url('/logout')) ?>" class="logout-form" data-logout-form><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><button class="button button--outline button--wide">Uitloggen</button></form>
</section>
