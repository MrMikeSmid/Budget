<section class="settings-page admin-page">
<header class="topbar"><div><span class="eyebrow">Beheer</span><h1>E-mailinstellingen</h1></div><a class="button button--soft button--small" href="<?= e(url('/admin')) ?>">Terug naar admin</a></header>
<div class="settings-stack admin-grid">
    <div class="settings-section admin-card" id="smtp">
        <span class="eyebrow">Stap 1</span><h2>SMTP-server</h2>
        <p class="section-intro">Verstuur e-mail via de geauthenticeerde mailserver van je provider. Gebruik bij voorkeur STARTTLS op poort 587; neem de exacte gegevens over van je mailprovider.</p>
        <form method="post" action="<?= e(url('/admin/email/settings')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>SMTP-host</span><input name="smtp_host" value="<?= e($smtp->host()) ?>" maxlength="255" placeholder="smtp.voorbeeld.nl" required autocomplete="off"><small>Alleen de hostnaam, zonder <code>https://</code>.</small></label>
            <div class="admin-field-row">
                <label class="field"><span>Poort</span><input type="number" name="smtp_port" value="<?= $smtp->port() ?>" min="1" max="65535" required inputmode="numeric"></label>
                <label class="field"><span>Beveiliging</span><select name="smtp_encryption" required>
                    <option value="starttls" <?= $smtp->encryption() === 'starttls' ? 'selected' : '' ?>>STARTTLS (meestal poort 587)</option>
                    <option value="tls" <?= $smtp->encryption() === 'tls' ? 'selected' : '' ?>>TLS/SSL (meestal poort 465)</option>
                    <option value="none" <?= $smtp->encryption() === 'none' ? 'selected' : '' ?>>Geen versleuteling</option>
                </select></label>
            </div>
            <label class="field"><span>Gebruikersnaam</span><input name="smtp_username" value="<?= e($smtp->username()) ?>" maxlength="255" autocomplete="username" placeholder="meestal het volledige e-mailadres"><small>Laat alleen leeg wanneer je provider expliciet geen authenticatie vereist.</small></label>
            <label class="field"><span>Wachtwoord of app-wachtwoord</span><input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= $smtp->password() !== '' ? 'Opgeslagen — laat leeg om te behouden' : 'SMTP-wachtwoord' ?>"><small>Het opgeslagen wachtwoord blijft server-side en wordt nooit teruggetoond. Gebruik bij providers met 2FA een app-wachtwoord.</small></label>
            <?php if ($smtp->password() !== ''): ?><label class="admin-check"><input type="checkbox" name="clear_smtp_password" value="1"><span>Opgeslagen SMTP-wachtwoord wissen</span></label><?php endif; ?>
            <label class="field"><span>Verbindingstime-out</span><input type="number" name="smtp_timeout" value="<?= $smtp->timeout() ?>" min="5" max="60" required inputmode="numeric"><small>In seconden; 15 is voor de meeste providers geschikt.</small></label>
            <button class="button button--primary button--wide">SMTP-instellingen opslaan</button>
        </form>
        <div class="admin-status <?= $smtp->isConfigured() ? 'admin-status--ok' : '' ?>"><strong><?= $smtp->isConfigured() ? 'SMTP-configuratie opgeslagen' : 'SMTP is nog niet geconfigureerd' ?></strong><span><?= $smtp->isConfigured() ? 'Stuur een testmail om authenticatie en aflevering te controleren.' : 'Vul minimaal host, poort en beveiliging in.' ?></span></div>
    </div>

    <div class="settings-section admin-card" id="testmail">
        <span class="eyebrow">Stap 2</span><h2>Testmail versturen</h2>
        <p class="section-intro">Hiermee controleer je de verbinding, TLS-beveiliging, inloggegevens en acceptatie door de SMTP-server.</p>
        <form method="post" action="<?= e(url('/admin/email/test')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Ontvanger testmail</span><input type="email" name="test_email" value="<?= e($user['email'] ?? '') ?>" required autocomplete="email"></label>
            <button class="button button--soft button--wide" <?= $smtp->isConfigured() ? '' : 'disabled' ?>>SMTP-testmail versturen</button>
        </form>
    </div>

    <div class="settings-section admin-card admin-card--email" id="uitnodigingsmail">
        <span class="eyebrow">Stap 3</span><h2>Uitnodigingsmail</h2>
        <p class="section-intro">Pas de zichtbare afzender en het persoonlijke bericht aan. Gebruik een afzenderadres op hetzelfde geverifieerde domein als je SMTP-account.</p>
        <form method="post" action="<?= e(url('/admin/invitation-email')) ?>" data-email-editor-form>
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <div class="admin-field-row">
                <label class="field"><span>Naam afzender</span><input name="invitation_sender_name" value="<?= e($invitation_sender_name ?? '') ?>" maxlength="100" required autocomplete="organization"></label>
                <label class="field"><span>E-mailadres afzender</span><input type="email" name="invitation_sender_email" value="<?= e($invitation_sender_email ?? '') ?>" required autocomplete="email"></label>
            </div>
            <label class="field rich-field"><span>Bericht</span>
                <div class="rich-editor" data-rich-editor>
                    <div class="rich-editor__toolbar" role="toolbar" aria-label="Tekstopmaak">
                        <button type="button" data-editor-command="bold" aria-label="Vet"><strong>B</strong></button><button type="button" data-editor-command="italic" aria-label="Cursief"><em>I</em></button><button type="button" data-editor-command="underline" aria-label="Onderstrepen"><u>U</u></button><span></span><button type="button" data-editor-command="formatBlock" data-editor-value="h2" aria-label="Kop">Kop</button><button type="button" data-editor-command="insertUnorderedList" aria-label="Opsomming">• Lijst</button><button type="button" data-editor-link aria-label="Link toevoegen">Link</button>
                    </div>
                    <div class="rich-editor__content" contenteditable="true" role="textbox" aria-multiline="true" data-editor-content><?= $invitation_message_html ?? '' ?></div>
                </div>
                <textarea name="invitation_message_html" data-editor-input hidden><?= e($invitation_message_html ?? '') ?></textarea><small>De knop naar het lijstje staat altijd onder dit bericht.</small>
            </label>
            <div class="editor-tokens"><span>Voeg een persoonlijk veld in:</span><?php foreach (($invitation_tokens ?? []) as $token): ?><button type="button" data-editor-token="<?= e($token) ?>"><code><?= e($token) ?></code></button><?php endforeach; ?></div>
            <button class="button button--primary button--wide">Uitnodigingsmail opslaan</button>
        </form>
        <details class="email-preview"><summary>Voorbeeld bekijken</summary><iframe title="Voorbeeld van de uitnodigingsmail" data-email-preview srcdoc="<?= e($invitation_preview_html ?? '') ?>"></iframe></details>
    </div>

    <div class="settings-section admin-card">
        <span class="eyebrow">Afleverbaarheid</span><h2>Voorkom ongewenste e-mail</h2>
        <p class="section-intro">SMTP-authenticatie is belangrijk, maar goede aflevering vereist ook correcte DNS-instellingen voor het domein van de afzender.</p>
        <ul class="admin-guidance-list">
            <li><strong>SPF:</strong> sta de gekozen mailprovider toe om namens je domein te verzenden.</li>
            <li><strong>DKIM:</strong> activeer ondertekening bij de provider en plaats de aangeleverde DNS-records.</li>
            <li><strong>DMARC:</strong> publiceer eerst een controlerend beleid en maak dit later strenger.</li>
            <li><strong>Afzender:</strong> gebruik een bestaand, geverifieerd adres op hetzelfde domein; vermijd <code>noreply@localhost</code>.</li>
            <li><strong>Reputatie:</strong> stuur alleen verwachte uitnodigingen en controleer bounces en spamklachten bij je provider.</li>
        </ul>
    </div>
</div>
</section>
