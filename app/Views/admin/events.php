<?php
$formatDate = static function (string $value): array {
    try {
        $date = (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone((string) config('timezone', 'Europe/Amsterdam')));
        return ['date' => $date->format('d-m-Y'), 'time' => $date->format('H:i:s'), 'iso' => $date->format(DateTimeInterface::ATOM)];
    } catch (Exception) {
        return ['date' => 'Onbekend', 'time' => '', 'iso' => ''];
    }
};
$categoryLabels = ['' => 'Alle gebeurtenissen', 'account' => 'Accounts', 'list' => 'Lijsten', 'member' => 'Leden', 'task' => 'Taken'];
$audit = $audit ?? ['logs' => [], 'total' => 0, 'page' => 1, 'pages' => 1];
$pageUrl = static function (int $page) use ($search, $category): string {
    return url('/admin/events') . '?' . http_build_query(array_filter(['q' => $search, 'category' => $category, 'page' => $page], static fn($value) => $value !== ''));
};
?>
<section class="settings-page admin-page events-page">
    <header class="topbar">
        <div><span class="eyebrow">Beheer</span><h1>Gebeurtenissen</h1></div>
        <div class="admin-header-actions"><a class="button button--soft button--small" href="<?= e(url('/admin/accounts')) ?>">Accounts</a><a class="button button--soft button--small" href="<?= e(url('/admin')) ?>">Admin</a><a class="icon-button" href="<?= e(url('/')) ?>">×</a></div>
    </header>

    <div class="settings-section events-card">
        <div class="accounts-heading">
            <div><span class="eyebrow">Auditlog</span><h2>Gebruikersactiviteit</h2></div>
            <span class="accounts-count"><?= (int) $audit['total'] ?> <?= (int) $audit['total'] === 1 ? 'gebeurtenis' : 'gebeurtenissen' ?></span>
        </div>
        <p class="section-intro">Een chronologisch en blijvend overzicht van wie wat deed, waar dat gebeurde en op welk exact moment. Ook het IP-adres en gebruikte apparaat worden bewaard.</p>

        <form class="events-filters" method="get" action="<?= e(url('/admin/events')) ?>">
            <label><span>Zoeken</span><input type="search" name="q" value="<?= e($search ?? '') ?>" placeholder="Naam, e-mail, gebeurtenis, lijst of IP-adres"></label>
            <label><span>Categorie</span><select name="category"><?php foreach ($categoryLabels as $value => $label): ?><option value="<?= e($value) ?>" <?= ($category ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
            <button class="button button--primary button--small">Filteren</button>
            <?php if (($search ?? '') !== '' || ($category ?? '') !== ''): ?><a class="button button--soft button--small" href="<?= e(url('/admin/events')) ?>">Wissen</a><?php endif; ?>
        </form>

        <?php if (empty($audit['logs'])): ?>
            <div class="accounts-empty">Er zijn geen gebeurtenissen gevonden.</div>
        <?php else: ?>
            <div class="accounts-table-wrap">
                <table class="accounts-table events-table">
                    <thead><tr><th>Gebruiker</th><th>Gebeurtenis</th><th>Waar</th><th>Datum</th><th>Tijd</th></tr></thead>
                    <tbody>
                    <?php foreach ($audit['logs'] as $log): $moment = $formatDate((string) $log['created_at']); ?>
                        <tr>
                            <td data-label="Gebruiker"><strong><?= e($log['user_name']) ?></strong><span><?= e($log['user_email']) ?></span></td>
                            <td data-label="Gebeurtenis"><strong><?= e($log['description']) ?></strong><span class="event-code"><?= e($log['event']) ?></span></td>
                            <td data-label="Waar"><strong><?= e($log['location']) ?></strong><span><?= e($log['request_path']) ?> · IP <?= e($log['ip_address']) ?></span><?php if ($log['user_agent'] !== ''): ?><small title="<?= e($log['user_agent']) ?>"><?= e(mb_strimwidth($log['user_agent'], 0, 70, '…')) ?></small><?php endif; ?></td>
                            <td data-label="Datum"><time datetime="<?= e($moment['iso']) ?>"><?= e($moment['date']) ?></time></td>
                            <td data-label="Tijd"><time datetime="<?= e($moment['iso']) ?>"><?= e($moment['time']) ?></time></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ((int) $audit['pages'] > 1): ?>
            <nav class="events-pagination" aria-label="Paginering">
                <?php if ((int) $audit['page'] > 1): ?><a class="button button--soft button--small" href="<?= e($pageUrl((int) $audit['page'] - 1)) ?>">← Vorige</a><?php endif; ?>
                <span>Pagina <?= (int) $audit['page'] ?> van <?= (int) $audit['pages'] ?></span>
                <?php if ((int) $audit['page'] < (int) $audit['pages']): ?><a class="button button--soft button--small" href="<?= e($pageUrl((int) $audit['page'] + 1)) ?>">Volgende →</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
