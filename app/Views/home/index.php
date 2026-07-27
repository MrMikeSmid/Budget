<header class="topbar">
    <div><span class="eyebrow">Welkom terug</span><h1><?= e($user['name']) ?></h1></div>
</header>

<div class="section-heading"><h2>Snel vervallend</h2></div>
<?php if (empty($dueSoon)): ?>
    <div class="empty">Niets dat binnenkort vervalt. Mooi rustig.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($dueSoon as $item): $overdue = is_overdue($item['due_date'], $item['status']); ?>
            <a class="card card-link" href="<?= e(url('/items/' . $item['id'] . '/bewerken')) ?>">
                <div class="card-row">
                    <h3><?= e($item['title']) ?></h3>
                    <span class="badge <?= $overdue ? 'badge--danger' : 'badge--warn' ?>"><?= $overdue ? 'Vervallen' : e(format_date($item['due_date'])) ?></span>
                </div>
                <small><?= e($item['park_name']) ?><?= $item['person_name'] ? ' · ' . e($item['person_name']) : '' ?> · <?= e(item_type_label($item['type'])) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="section-heading"><h2>Parken</h2><a class="button button--soft button--small" href="<?= e(url('/parken/nieuw')) ?>">+ Nieuw park</a></div>
<?php if (empty($parks)): ?>
    <div class="empty">Nog geen parken toegevoegd.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($parks as $park): ?>
            <a class="card card-link" href="<?= e(url('/parken/' . $park['id'])) ?>">
                <div class="card-row">
                    <h3><?= e($park['name']) ?></h3>
                    <?php $count = $openCounts[(int) $park['id']] ?? 0; ?>
                    <span class="badge <?= $count > 0 ? 'badge--warn' : 'badge--muted' ?>"><?= $count ?> open</span>
                </div>
                <?php if ($park['location']): ?><small><?= e($park['location']) ?></small><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
