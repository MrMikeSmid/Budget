<section class="home-page">
    <header class="topbar">
        <div><span class="eyebrow">Goed je te zien</span><h1>Hoi, <?= e(explode(' ', $user['name'])[0]) ?> <span class="wave">👋</span></h1></div>
        <a class="avatar" href="<?= e(url('/settings')) ?>"><?php if ($profileImage = profile_image_url($user)): ?><img src="<?= e($profileImage) ?>" alt="Profielfoto van <?= e($user['name']) ?>"><?php else: ?><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?><?php endif; ?><span></span></a>
    </header>
    <?php $pendingInvitations = array_values(array_filter($lists, static fn(array $list): bool => (int) $list['invitation_pending'] === 1)); ?>
    <?php if ($pendingInvitations): ?>
        <section class="dashboard-invitations" aria-labelledby="dashboard-invitations-title">
            <div class="dashboard-invitations__heading"><span class="eyebrow">Openstaande uitnodigingen</span><h2 id="dashboard-invitations-title"><?= count($pendingInvitations) === 1 ? 'Je bent uitgenodigd' : 'Je hebt meerdere uitnodigingen' ?></h2></div>
            <?php foreach ($pendingInvitations as $invitation): ?>
                <article class="invitation-banner invitation-banner--dashboard">
                    <span class="invitation-banner__icon">♡</span>
                    <div><span class="eyebrow">Van <?= e($invitation['owner_name']) ?></span><h3><?= e($invitation['title']) ?></h3><p>Accepteer de uitnodiging om taken toe te voegen, af te vinken en reacties te plaatsen.</p></div>
                    <form method="post" action="<?= e(url('/lists/' . $invitation['id'] . '/accept')) ?>"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><button class="button button--primary">Uitnodiging accepteren</button></form>
                    <a class="invitation-banner__link" href="<?= e(url('/lists/' . $invitation['id'])) ?>">Bekijk lijstje</a>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
    <?php if (!$lists): ?>
        <section class="hero-card">
            <div class="hero-card__copy"><span class="pill pill--light">Jullie week</span><h2>Samen wordt alles net wat leuker.</h2><p>Nodig iemand uit en begin aan jullie volgende plan.</p><button type="button" class="button button--light" data-open-modal="new-list">Nieuw lijstje <span>＋</span></button></div>
            <div class="hero-card__art" aria-hidden="true"><div class="sun-shape"></div><div class="mini-card mini-card-a">✓</div><div class="mini-card mini-card-b">♥</div><div class="hero-person"></div></div>
        </section>
    <?php endif; ?>
    <?php $overdueTasks = $overdueTasks ?? []; ?>
    <?php if ($overdueTasks): ?>
        <section class="overdue-tasks" aria-labelledby="overdue-tasks-title">
            <header class="overdue-tasks__header">
                <span class="overdue-tasks__alert" aria-hidden="true">!</span>
                <div><span class="eyebrow">Even aandacht voor</span><h2 id="overdue-tasks-title">Vervallen taken</h2></div>
                <span class="overdue-tasks__count"><?= count($overdueTasks) ?></span>
            </header>
            <div class="overdue-tasks__list">
                <?php foreach ($overdueTasks as $task): ?>
                    <?php $dueDate = date_create_from_format('!Y-m-d', (string) $task['due_date']); ?>
                    <a class="overdue-task" href="<?= e(url('/lists/' . $task['list_id'])) ?>">
                        <span class="overdue-task__marker" aria-hidden="true"></span>
                        <span class="overdue-task__copy"><strong><?= e($task['title']) ?></strong><small><?= e($task['list_title']) ?></small></span>
                        <span class="overdue-task__date">Vervallen <?= e($dueDate ? $dueDate->format('d-m-Y') : $task['due_date']) ?></span>
                        <span class="ui-icon ui-icon--arrow-right" aria-hidden="true"></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <div class="section-heading"><div><span class="eyebrow">Alles bij elkaar</span><h2>Jouw lijstjes</h2></div><span class="count-badge"><?= count($lists) ?></span></div>
    <?php if ($lists): ?>
        <div class="list-grid">
        <?php foreach ($lists as $list): $total=(int)$list['item_count']; $done=(int)($list['completed_count'] ?? 0); $percent=$total ? round(($done/$total)*100) : 0; ?>
            <a class="list-card list-card--<?= e($list['color']) ?>" href="<?= e(url('/lists/' . $list['id'])) ?>">
                <div class="list-card__top"><span class="list-emoji"><?= render_list_mood_icon($list['emoji']) ?></span><span class="member-stack"><i><?= e(mb_strtoupper(mb_substr($list['owner_name'],0,1))) ?></i><?php if ((int)$list['member_count'] > 1): ?><i>+<?= (int)$list['member_count']-1 ?></i><?php endif; ?></span></div>
                <h3><?= e($list['title']) ?></h3><p><?= $total ? "$done van $total gedaan" : 'Klaar voor jullie eerste taak' ?></p>
                <div class="progress"><span style="width:<?= $percent ?>%"></span></div>
            </a>
        <?php endforeach; ?></div>
    <?php else: ?>
        <div class="empty-state"><div class="empty-illustration"><span>✦</span></div><h3>Je eerste lijstje wacht op je</h3><p>Maak iets moois en nodig daarna iemand uit om mee te doen.</p><button type="button" class="button button--primary" data-open-modal="new-list">Maak een lijstje</button></div>
    <?php endif; ?>
</section>
