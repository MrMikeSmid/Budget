<section class="settings-page admin-page">
<header class="topbar"><div><span class="eyebrow">Beheer</span><h1>Admin</h1></div><a class="icon-button" href="<?= e(url('/')) ?>">×</a></header>
<div class="admin-grid">
    <div class="settings-section admin-card admin-card--email" id="uitnodigingsmail">
        <span class="eyebrow">E-mail</span><h2>Uitnodigingsmail</h2>
        <p class="section-intro">Pas de afzender en het persoonlijke bericht aan. De herkenbare header, knop en footer worden automatisch toegevoegd.</p>
        <form method="post" action="<?= e(url('/admin/invitation-email')) ?>" data-email-editor-form>
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <div class="admin-field-row">
                <label class="field"><span>Naam afzender</span><input name="invitation_sender_name" value="<?= e($invitation_sender_name ?? '') ?>" maxlength="100" required autocomplete="organization"></label>
                <label class="field"><span>E-mailadres afzender</span><input type="email" name="invitation_sender_email" value="<?= e($invitation_sender_email ?? '') ?>" required autocomplete="email"></label>
            </div>
            <label class="field rich-field">
                <span>Bericht</span>
                <div class="rich-editor" data-rich-editor>
                    <div class="rich-editor__toolbar" role="toolbar" aria-label="Tekstopmaak">
                        <button type="button" data-editor-command="bold" aria-label="Vet"><strong>B</strong></button>
                        <button type="button" data-editor-command="italic" aria-label="Cursief"><em>I</em></button>
                        <button type="button" data-editor-command="underline" aria-label="Onderstrepen"><u>U</u></button>
                        <span></span>
                        <button type="button" data-editor-command="formatBlock" data-editor-value="h2" aria-label="Kop">Kop</button>
                        <button type="button" data-editor-command="insertUnorderedList" aria-label="Opsomming">• Lijst</button>
                        <button type="button" data-editor-link aria-label="Link toevoegen">Link</button>
                    </div>
                    <div class="rich-editor__content" contenteditable="true" role="textbox" aria-multiline="true" data-editor-content><?= $invitation_message_html ?? '' ?></div>
                </div>
                <textarea name="invitation_message_html" data-editor-input hidden><?= e($invitation_message_html ?? '') ?></textarea>
                <small>De paarse knop naar het lijstje staat altijd onder dit bericht.</small>
            </label>
            <div class="editor-tokens">
                <span>Voeg een persoonlijk veld in:</span>
                <?php foreach (($invitation_tokens ?? []) as $token): ?><button type="button" data-editor-token="<?= e($token) ?>"><code><?= e($token) ?></code></button><?php endforeach; ?>
            </div>
            <button class="button button--primary button--wide">Uitnodigingsmail opslaan</button>
        </form>
        <details class="email-preview" open>
            <summary>Voorbeeld bekijken</summary>
            <iframe title="Voorbeeld van de uitnodigingsmail" data-email-preview srcdoc="<?= e($invitation_preview_html ?? '') ?>"></iframe>
        </details>
    </div>

    <div class="settings-section admin-card" id="pushnotificaties">
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
        <?php if (!empty($onesignal_configured)): ?>
            <form method="post" action="<?= e(url('/admin/onesignal/test')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <button class="button button--soft button--wide">Stuur testmelding naar dit account</button>
                <small>Dit controleert de API key én of OneSignal een actief abonnement aan jouw account heeft gekoppeld.</small>
            </form>
        <?php endif; ?>
    </div>
</div>
</section>
