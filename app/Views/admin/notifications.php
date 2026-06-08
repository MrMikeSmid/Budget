<section class="settings-page admin-page" data-firebase-push
    data-firebase-config="<?= e(json_encode($firebase_public_config, JSON_UNESCAPED_SLASHES)) ?>"
    data-vapid-key="<?= e($firebase->vapidPublicKey()) ?>"
    data-subscribe-endpoint="<?= e(url('/admin/notifications/subscribe')) ?>"
    data-unsubscribe-endpoint="<?= e(url('/admin/notifications/unsubscribe')) ?>"
    data-csrf-token="<?= e(csrf_token()) ?>">
<header class="topbar"><div><span class="eyebrow">Pushnotificaties</span><h1>Firebase-test</h1></div><a class="button button--soft button--small" href="<?= e(url('/admin')) ?>">Terug naar admin</a></header>
<div class="settings-stack admin-grid">
    <div class="settings-section admin-card">
        <span class="eyebrow">Stap 1</span><h2>Firebase instellen</h2>
        <p class="section-intro">Deze eerste versie gebruikt Firebase Cloud Messaging alleen voor handmatige testmeldingen naar het huidige adminapparaat.</p>
        <form method="post" action="<?= e(url('/admin/notifications/settings')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Project ID</span><input name="project_id" value="<?= e($firebase->projectId()) ?>" required autocomplete="off"></label>
            <label class="field"><span>Web API key</span><input name="api_key" value="<?= e($firebase->apiKey()) ?>" required autocomplete="off"></label>
            <label class="field"><span>Messaging sender ID</span><input name="messaging_sender_id" value="<?= e($firebase->messagingSenderId()) ?>" required autocomplete="off"></label>
            <label class="field"><span>Web app ID</span><input name="app_id" value="<?= e($firebase->appId()) ?>" required autocomplete="off"></label>
            <label class="field"><span>Publieke VAPID-key</span><textarea name="vapid_public_key" rows="3" required><?= e($firebase->vapidPublicKey()) ?></textarea><small>Firebase Console → Project settings → Cloud Messaging → Web Push certificates.</small></label>
            <label class="field"><span>Serviceaccount JSON</span><textarea name="service_account_json" rows="8" placeholder="<?= $firebase->serviceAccountJson() !== '' ? 'Opgeslagen — laat leeg om te behouden' : 'Plak hier de volledige JSON-export' ?>"></textarea><small>Deze privésleutel blijft uitsluitend server-side en wordt na opslaan niet teruggetoond.</small></label>
            <?php if ($firebase->serviceAccountJson() !== ''): ?><label class="admin-check"><input type="checkbox" name="clear_service_account" value="1"><span>Opgeslagen serviceaccount wissen</span></label><?php endif; ?>
            <button class="button button--primary button--wide">Firebase-instellingen opslaan</button>
        </form>
        <div class="admin-status <?= $firebase->isConfigured() ? 'admin-status--ok' : '' ?>"><strong><?= $firebase->isConfigured() ? 'Firebase is klaar voor testen' : 'Configuratie is nog niet compleet' ?></strong><span>Client: <?= $firebase->isClientConfigured() ? 'gereed' : 'onvolledig' ?> · server: <?= $firebase->isServerConfigured() ? 'gereed' : 'onvolledig' ?></span></div>
    </div>

    <div class="settings-section admin-card">
        <span class="eyebrow">Stap 2</span><h2>Dit apparaat registreren</h2>
        <p class="section-intro" data-firebase-status>Controleer eerst de configuratie en geef daarna browsertoestemming.</p>
        <button type="button" class="button button--primary button--wide" data-firebase-subscribe <?= $firebase->isClientConfigured() ? '' : 'disabled' ?>>Meldingen op dit apparaat activeren</button>
        <button type="button" class="button button--outline button--wide" data-firebase-unsubscribe hidden>Dit apparaat afmelden</button>
        <small>Push vereist HTTPS. Op iPhone/iPad moet de PWA eerst via Safari aan het beginscherm zijn toegevoegd.</small>
    </div>

    <div class="settings-section admin-card" id="testmelding">
        <span class="eyebrow">Stap 3</span><h2>Testmelding sturen</h2>
        <?php if (empty($subscriptions)): ?>
            <div class="admin-empty">Registreer eerst dit apparaat en vernieuw daarna eventueel de pagina.</div>
        <?php else: ?>
            <form method="post" action="<?= e(url('/admin/notifications/test')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <label class="field"><span>Apparaat</span><select name="subscription_id" required><?php foreach ($subscriptions as $subscription): ?><option value="<?= (int) $subscription['id'] ?>"><?= e($subscription['user_agent'] ?: 'Onbekende browser') ?> · <?= e($subscription['updated_at']) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Bericht</span><textarea name="message" maxlength="500" rows="3" required>Dit is een testmelding van Samen via Firebase.</textarea></label>
                <button class="button button--soft button--wide">Stuur testmelding</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</section>
<?php if ($firebase->isClientConfigured()): ?>
<script src="https://www.gstatic.com/firebasejs/11.10.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/11.10.0/firebase-messaging-compat.js"></script>
<?php endif; ?>
