<section class="settings-page admin-page" data-beams-push>
<header class="topbar"><div><span class="eyebrow">Pushnotificaties</span><h1>Pusher Beams</h1></div><a class="button button--soft button--small" href="<?= e(url('/admin')) ?>">Terug naar admin</a></header>
<div class="settings-stack admin-grid">
    <div class="settings-section admin-card">
        <span class="eyebrow">Stap 1</span><h2>Pusher Beams instellen</h2>
        <p class="section-intro">Samen gebruikt Pusher Beams voor automatische meldingen bij wijzigingen in gedeelde lijstjes. Maak gratis een Beams-instance aan en kopieer alleen de Instance ID en Secret Key uit de Credentials-sectie.</p>
        <form method="post" action="<?= e(url('/admin/notifications/settings')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Instance ID</span><input name="instance_id" value="<?= e($beams->instanceId()) ?>" required autocomplete="off"><small>Pusher Dashboard → Beams → jouw instance → Credentials.</small></label>
            <label class="field"><span>Secret Key</span><textarea name="secret_key" rows="3" placeholder="<?= $beams->secretKey() !== '' ? 'Opgeslagen — laat leeg om te behouden' : 'Plak hier de Secret Key' ?>"></textarea><small>Deze sleutel blijft uitsluitend server-side en wordt na opslaan niet teruggetoond.</small></label>
            <?php if ($beams->secretKey() !== ''): ?><label class="admin-check"><input type="checkbox" name="clear_secret_key" value="1"><span>Opgeslagen Secret Key wissen</span></label><?php endif; ?>
            <button class="button button--primary button--wide">Pusher Beams-instellingen opslaan</button>
        </form>
        <div class="admin-status <?= $beams->isConfigured() ? 'admin-status--ok' : '' ?>"><strong><?= $beams->isConfigured() ? 'Pusher Beams is actief' : 'Configuratie is nog niet compleet' ?></strong><span>Je hebt alleen een Instance ID en Secret Key nodig.</span></div>
    </div>

    <div class="settings-section admin-card">
        <span class="eyebrow">Stap 2</span><h2>Meldingen op dit apparaat</h2>
        <p class="section-intro" data-beams-status>Geef één keer browsertoestemming. Daarna houdt Samen de apparaatregistratie automatisch actief.</p>
        <button type="button" class="button button--primary button--wide" data-beams-subscribe <?= $beams->isConfigured() ? '' : 'disabled' ?>>Meldingen op dit apparaat activeren</button>
        <button type="button" class="button button--outline button--wide" data-beams-unsubscribe hidden>Dit apparaat afmelden</button>
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
                <label class="field"><span>Bericht</span><textarea name="message" maxlength="500" rows="3" required>Dit is een testmelding van Samen via Pusher Beams.</textarea></label>
                <button class="button button--soft button--wide">Stuur testmelding</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</section>
