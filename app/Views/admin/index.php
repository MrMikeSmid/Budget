<section class="settings-page admin-page">
<header class="topbar"><div><span class="eyebrow">Beheer</span><h1>Admin</h1></div><div class="admin-header-actions"><a class="button button--soft button--small" href="<?= e(url('/admin/events')) ?>">Gebeurtenissen</a><a class="button button--soft button--small" href="<?= e(url('/admin/accounts')) ?>">Accounts</a><a class="icon-button" href="<?= e(url('/')) ?>">×</a></div></header>
<div class="admin-grid">
    <div class="settings-section admin-card">
        <span class="eyebrow">E-mail</span><h2>SMTP en uitnodigingen</h2>
        <p class="section-intro">Configureer een beveiligde SMTP-server, verstuur een testmail en beheer de afzender en inhoud van uitnodigingsmails.</p>
        <a class="button button--primary button--wide" href="<?= e(url('/admin/email')) ?>">Open e-mailinstellingen</a>
    </div>
    <div class="settings-section admin-card">
        <span class="eyebrow">Pushnotificaties</span><h2>OneSignal-testomgeving</h2>
        <p class="section-intro">Stel OneSignal eenmalig in, registreer dit apparaat en stuur een handmatige testmelding naar iOS, Android of desktop.</p>
        <a class="button button--primary button--wide" href="<?= e(url('/admin/notifications')) ?>">Open notificatietest</a>
    </div>
</div>
</section>
