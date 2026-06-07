<section class="settings-page admin-page">
<header class="topbar"><div><span class="eyebrow">Beheer</span><h1>Admin</h1></div><a class="icon-button" href="<?= e(url('/')) ?>">×</a></header>
<div class="settings-section admin-card">
    <span class="eyebrow">Pushnotificaties</span><h2>OneSignal</h2>
    <p class="section-intro">Bewaar de OneSignal-gegevens in de database. De browser krijgt alleen de App ID te zien; de API key blijft server-side.</p>
    <form method="post" action="<?= e(url('/admin/onesignal')) ?>">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <label class="field"><span>OneSignal App ID</span><input name="onesignal_app_id" value="<?= e($onesignal_app_id ?? '') ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" autocomplete="off"></label>
        <label class="field"><span>OneSignal REST API Key</span><input type="password" name="onesignal_api_key" value="" placeholder="<?= !empty($onesignal_configured) ? 'Opgeslagen — vul alleen in om te wijzigen' : 'Vul je API key in' ?>" autocomplete="off"><small>Laat leeg om de opgeslagen API key te behouden.</small></label>
        <?php if (!empty($onesignal_configured)): ?>
            <label class="admin-check"><input type="checkbox" name="clear_onesignal_api_key" value="1"><span>Opgeslagen API key wissen</span></label>
        <?php endif; ?>
        <button class="button button--primary button--wide">OneSignal opslaan</button>
    </form>
    <div class="admin-status <?= !empty($onesignal_configured) ? 'admin-status--ok' : '' ?>">
        <strong><?= !empty($onesignal_configured) ? 'OneSignal is actief' : 'OneSignal is nog niet compleet' ?></strong>
        <span><?= !empty($onesignal_configured) ? 'Meldingen gebruiken nu de waarden uit de database.' : 'Vul zowel de App ID als API key in om pushnotificaties te activeren.' ?></span>
    </div>
</div>
</section>
