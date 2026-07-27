<header class="topbar">
    <div><span class="eyebrow"><?= $park['location'] ? e($park['location']) : 'Park' ?></span><h1><?= e($park['name']) ?></h1></div>
    <div style="display:flex;gap:8px">
        <a class="icon-button" href="<?= e(url('/parken/' . $park['id'] . '/print')) ?>" title="Print">⎙</a>
        <a class="icon-button" href="<?= e(url('/parken/' . $park['id'] . '/bewerken')) ?>" title="Bewerken">✎</a>
    </div>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<?php if ($park['attention_points']): ?>
    <div class="attention-box"><?= e($park['attention_points']) ?></div>
<?php endif; ?>

<div class="section-heading">
    <h2>Medewerkers</h2>
    <a class="button button--soft button--small" href="<?= e(url('/parken/' . $park['id'] . '/personen/nieuw?type=staff')) ?>">+ Toevoegen</a>
</div>
<?php if (empty($staff)): ?>
    <div class="empty">Nog geen medewerkers toegevoegd.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($staff as $person): ?>
            <a class="card card-link" href="<?= e(url('/personen/' . $person['id'])) ?>">
                <div class="card-row"><h3><?= e($person['name']) ?></h3><?php if (!$person['is_active']): ?><span class="badge badge--muted">Inactief</span><?php endif; ?></div>
                <?php if ($person['role']): ?><small><?= e($person['role']) ?></small><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="section-heading">
    <h2>Gasten</h2>
    <a class="button button--soft button--small" href="<?= e(url('/parken/' . $park['id'] . '/personen/nieuw?type=guest')) ?>">+ Toevoegen</a>
</div>
<?php if (empty($guests)): ?>
    <div class="empty">Geen gastdossiers.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($guests as $person): ?>
            <a class="card card-link" href="<?= e(url('/personen/' . $person['id'])) ?>">
                <h3><?= e($person['name']) ?></h3>
                <?php if ($person['role']): ?><small><?= e($person['role']) ?></small><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="section-heading"><h2>Notities, afspraken &amp; taken</h2></div>
<div class="chip-row">
    <a class="chip <?= $category === null ? 'active' : '' ?>" href="<?= e(url('/parken/' . $park['id'])) ?>">Alle</a>
    <?php foreach (['personeel', 'park', 'gasten', 'taken'] as $cat): ?>
        <a class="chip <?= $category === $cat ? 'active' : '' ?>" href="<?= e(url('/parken/' . $park['id'] . '?category=' . $cat)) ?>"><?= e(category_label($cat)) ?></a>
    <?php endforeach; ?>
</div>
<a class="button button--primary button--wide" style="margin-bottom:14px" href="<?= e(url('/parken/' . $park['id'] . '/items/nieuw' . ($category ? '?category=' . $category : ''))) ?>">+ Nieuwe notitie / afspraak / taak</a>

<?php if (empty($items)): ?>
    <div class="empty">Niets gevonden in deze categorie.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($items as $item): $overdue = is_overdue($item['due_date'], $item['status']); ?>
            <div class="card">
                <div class="card-row">
                    <h3><?= e($item['title']) ?></h3>
                    <span class="badge <?= $item['status'] === 'afgerond' ? 'badge--ok' : ($overdue ? 'badge--danger' : 'badge--muted') ?>"><?= $overdue ? 'Vervallen' : e(status_label($item['status'])) ?></span>
                </div>
                <small><?= e(category_label($item['category'])) ?> · <?= e(item_type_label($item['type'])) ?><?= $item['person_name'] ? ' · ' . e($item['person_name']) : '' ?><?= $item['due_date'] ? ' · ' . e(format_date($item['due_date'])) : '' ?></small>
                <?php if ($item['body']): ?><p style="margin:8px 0 0;font-size:13px;white-space:pre-wrap"><?= e($item['body']) ?></p><?php endif; ?>
                <div class="card-row" style="margin-top:10px;gap:8px">
                    <form method="post" action="<?= e(url('/items/' . $item['id'] . '/toggle')) ?>">
                        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                        <button class="button button--soft button--small" type="submit"><?= $item['status'] === 'afgerond' ? 'Heropenen' : 'Afronden' ?></button>
                    </form>
                    <a class="button button--ghost button--small" href="<?= e(url('/items/' . $item['id'] . '/bewerken')) ?>">Bewerken</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
