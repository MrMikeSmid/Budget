<section class="settings-page admin-page" data-notification-push>
<header class="topbar"><div><span class="eyebrow">Pushnotificaties</span><h1>OneSignal</h1></div><a class="button button--soft button--small" href="<?= e(url('/admin')) ?>">Terug naar admin</a></header>
<div class="settings-stack admin-grid">
    <div class="settings-section admin-card">
        <span class="eyebrow">Stap 1</span><h2>OneSignal instellen</h2>
        <p class="section-intro">OneSignal verzorgt pushmeldingen op Android en desktop, plus iPhone/iPad vanaf iOS 16.4 wanneer Samen aan het beginscherm is toegevoegd. Maak een gratis Web Push-app aan en kopieer de App ID en REST API Key.</p>
        <form method="post" action="<?= e(url('/admin/notifications/settings')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>App ID</span><input name="app_id" value="<?= e($oneSignal->appId()) ?>" required autocomplete="off"><small>OneSignal → Settings → Keys & IDs → App ID.</small></label>
            <label class="field"><span>REST API Key</span><textarea name="rest_api_key" rows="3" placeholder="<?= $oneSignal->restApiKey() !== '' ? 'Opgeslagen — laat leeg om te behouden' : 'Plak hier de REST API Key' ?>"></textarea><small>Deze sleutel blijft uitsluitend server-side en wordt na opslaan niet teruggetoond.</small></label>
            <?php if ($oneSignal->restApiKey() !== ''): ?><label class="admin-check"><input type="checkbox" name="clear_rest_api_key" value="1"><span>Opgeslagen REST API Key wissen</span></label><?php endif; ?>
            <button class="button button--primary button--wide">OneSignal-instellingen opslaan</button>
        </form>
        <div class="admin-status <?= $oneSignal->isConfigured() ? 'admin-status--ok' : '' ?>"><strong><?= $oneSignal->isConfigured() ? 'OneSignal is actief' : 'Configuratie is nog niet compleet' ?></strong><span>Je hebt alleen een App ID en REST API Key nodig.</span></div>
    </div>

    <div class="settings-section admin-card">
        <span class="eyebrow">Stap 2</span><h2>Meldingen op dit apparaat</h2>
        <p class="section-intro" data-notification-status>Geef één keer browsertoestemming. Daarna houdt Samen de apparaatregistratie automatisch actief.</p>
        <button type="button" class="button button--primary button--wide" data-notification-subscribe <?= $oneSignal->isConfigured() ? '' : 'disabled' ?>>Meldingen op dit apparaat activeren</button>
        <button type="button" class="button button--outline button--wide" data-notification-unsubscribe hidden>Dit apparaat afmelden</button>
        <small>Push vereist HTTPS. Op iPhone/iPad is iOS 16.4 of nieuwer vereist en moet Samen eerst via Safari aan het beginscherm worden toegevoegd en van daaruit worden geopend.</small>
    </div>

    <div class="settings-section admin-card" id="testmelding">
        <span class="eyebrow">Stap 3</span><h2>Testmelding sturen</h2>
        <?php if (empty($subscriptions)): ?>
            <div class="admin-empty">Registreer eerst dit apparaat en vernieuw daarna eventueel de pagina.</div>
        <?php else: ?>
            <form method="post" action="<?= e(url('/admin/notifications/test')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <label class="field"><span>Apparaat</span><select name="subscription_id" required><?php foreach ($subscriptions as $subscription): ?><option value="<?= (int) $subscription['id'] ?>"><?= e($subscription['user_agent'] ?: 'Onbekende browser') ?> · <?= e($subscription['updated_at']) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Bericht</span><textarea name="message" maxlength="500" rows="3" required>Dit is een testmelding van Samen via OneSignal.</textarea></label>
                <button class="button button--soft button--wide">Stuur testmelding</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</section>
