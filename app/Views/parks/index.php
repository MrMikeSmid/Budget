<header class="topbar">
    <div><span class="eyebrow">Overzicht</span><h1>Parken</h1></div>
    <a class="button button--primary button--small" href="<?= e(url('/parken/nieuw')) ?>">+ Nieuw park</a>
</header>

<?php if (empty($parks)): ?>
    <div class="empty">Nog geen parken toegevoegd. Maak het eerste park aan.</div>
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
