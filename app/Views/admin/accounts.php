<?php
$formatDateTime = static function (?string $value): string {
    if (!$value) {
        return 'Nog niet geregistreerd';
    }

    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone((string) config('timezone', 'Europe/Amsterdam')))
            ->format('d-m-Y H:i');
    } catch (Exception) {
        return 'Onbekend';
    }
};
?>
<section class="settings-page admin-page accounts-page">
    <header class="topbar">
        <div><span class="eyebrow">Beheer</span><h1>Accounts</h1></div>
        <div class="admin-header-actions"><a class="button button--soft button--small" href="<?= e(url('/admin')) ?>">Admin</a><a class="icon-button" href="<?= e(url('/')) ?>">×</a></div>
    </header>

    <div class="settings-section accounts-card">
        <div class="accounts-heading">
            <div><span class="eyebrow">Geregistreerde gebruikers</span><h2>Alle accounts</h2></div>
            <span class="accounts-count"><?= count($accounts ?? []) ?> <?= count($accounts ?? []) === 1 ? 'account' : 'accounts' ?></span>
        </div>
        <p class="section-intro">Bekijk per gebruiker van welke lijsten diegene actief lid is, of een wachtwoord en pushnotificaties actief zijn, en wanneer diegene voor het laatst heeft ingelogd.</p>

        <?php if (empty($accounts)): ?>
            <div class="accounts-empty">Er zijn nog geen accounts geregistreerd.</div>
        <?php else: ?>
            <div class="accounts-table-wrap">
                <table class="accounts-table">
                    <thead><tr><th>Account</th><th>Lijsten</th><th>Wachtwoord</th><th>Notificaties</th><th>Laatst ingelogd</th></tr></thead>
                    <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <?php $notificationsEnabled = (int) $account['notification_subscription_count'] > 0; ?>
                        <tr>
                            <td data-label="Account"><strong><?= e($account['name']) ?><?= (int) $account['is_admin'] === 1 ? ' · Admin' : '' ?></strong><span><?= e($account['email']) ?></span></td>
                            <td data-label="Lijsten">
                                <?php if (empty($account['lists'])): ?>
                                    <span class="account-lists-empty">Geen lijsten</span>
                                <?php else: ?>
                                    <div class="account-lists">
                                        <?php foreach ($account['lists'] as $list): ?>
                                            <span class="account-list" title="<?= (int) $list['is_owner'] === 1 ? 'Eigenaar' : 'Lid' ?>"><?= e($list['emoji']) ?> <?= e($list['title']) ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Wachtwoord"><span class="account-status <?= (int) $account['has_password'] === 1 ? 'account-status--on' : 'account-status--off' ?>"><?= (int) $account['has_password'] === 1 ? 'Ingesteld' : 'Niet ingesteld' ?></span></td>
                            <td data-label="Notificaties"><span class="account-status <?= $notificationsEnabled ? 'account-status--on' : 'account-status--off' ?>"><?= $notificationsEnabled ? 'Aan' : 'Uit' ?></span><?php if ($notificationsEnabled): ?><small><?= (int) $account['notification_subscription_count'] ?> <?= (int) $account['notification_subscription_count'] === 1 ? 'apparaat' : 'apparaten' ?></small><?php endif; ?></td>
                            <td data-label="Laatst ingelogd"><time datetime="<?= e($account['last_login_at'] ?? '') ?>"><?= e($formatDateTime($account['last_login_at'] ?? null)) ?></time></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
