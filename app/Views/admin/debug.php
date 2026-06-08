<section class="admin-page debug-page" data-push-debug data-generated-at="<?= e($generated_at ?? '') ?>">
    <header class="page-header admin-header">
        <div>
            <a class="back-link" href="<?= e(url('/admin')) ?>"><span class="ui-icon ui-icon--arrow-left" aria-hidden="true"></span> Terug naar admin</a>
            <span class="eyebrow">Diagnostiek</span>
            <h1>Push debug</h1>
            <p>Een volledig overzicht van wat de server, browser en OneSignal op dit apparaat daadwerkelijk zien.</p>
        </div>
        <button type="button" class="button button--primary" data-debug-rerun>Controles opnieuw uitvoeren</button>
    </header>

    <div class="debug-summary" role="status" data-debug-summary>
        <span class="debug-summary__pulse" aria-hidden="true"></span>
        <div><strong>Browsercontroles worden uitgevoerd…</strong><span>Laat deze pagina open totdat alle regels een resultaat tonen.</span></div>
    </div>

    <div class="debug-grid">
        <section class="settings-section admin-card debug-card">
            <div class="debug-card__heading"><div><span class="eyebrow">Server</span><h2>Configuratie</h2></div><span class="debug-badge debug-badge--info">PHP</span></div>
            <div class="debug-check-list">
                <?php foreach (($debug_checks ?? []) as $check): ?>
                    <article class="debug-check debug-check--<?= e($check['status']) ?>">
                        <span class="debug-check__icon" aria-hidden="true"></span>
                        <div><strong><?= e($check['label']) ?></strong><code><?= e($check['value']) ?></code><small><?= e($check['help']) ?></small></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="settings-section admin-card debug-card">
            <div class="debug-card__heading"><div><span class="eyebrow">Dit apparaat</span><h2>Browser & PWA</h2></div><span class="debug-badge" data-debug-browser-badge>Bezig</span></div>
            <div class="debug-check-list" data-debug-browser-checks>
                <?php foreach (['secure-context' => 'Secure context', 'notification-api' => 'Notification API', 'permission' => 'Browsertoestemming', 'standalone' => 'PWA-installatie', 'service-worker' => 'Service worker', 'worker-endpoint' => 'OneSignal worker-endpoint'] as $key => $label): ?>
                    <article class="debug-check debug-check--pending" data-debug-check="<?= e($key) ?>"><span class="debug-check__icon" aria-hidden="true"></span><div><strong><?= e($label) ?></strong><code>Controleren…</code><small></small></div></article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="settings-section admin-card debug-card">
            <div class="debug-card__heading"><div><span class="eyebrow">SDK</span><h2>OneSignal runtime</h2></div><span class="debug-badge" data-debug-onesignal-badge>Wachten</span></div>
            <?php if (empty($onesignal_configured)): ?><div class="admin-status"><strong>Configuratie incompleet</strong><span>Vul eerst de App ID en REST API key in op de adminpagina.</span></div><?php endif; ?>
            <div class="debug-check-list">
                <?php foreach (['sdk' => 'SDK geladen', 'supported' => 'Push ondersteund', 'external-id' => 'Gekoppelde gebruiker', 'opted-in' => 'OneSignal opt-in', 'subscription-id' => 'Subscription ID', 'push-token' => 'Push token'] as $key => $label): ?>
                    <article class="debug-check debug-check--pending" data-debug-check="<?= e($key) ?>"><span class="debug-check__icon" aria-hidden="true"></span><div><strong><?= e($label) ?></strong><code>Wachten op OneSignal…</code><small></small></div></article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="settings-section admin-card debug-card debug-card--wide">
            <div class="debug-card__heading"><div><span class="eyebrow">Logboek</span><h2>Technische tijdlijn</h2></div><button type="button" class="button button--soft button--small" data-debug-copy>Kopieer rapport</button></div>
            <p class="section-intro">De API key en volledige push token worden niet opgenomen. Deel dit rapport om gericht te kunnen vergelijken wat er misgaat.</p>
            <ol class="debug-log" data-debug-log><li><time>—</time><span>Debugpagina geopend voor het ingelogde adminaccount.</span></li></ol>
            <textarea class="debug-report" data-debug-report readonly aria-label="Kopieerbaar debugrapport"></textarea>
        </section>
    </div>
</section>
