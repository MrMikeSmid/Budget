<header class="topbar">
    <div><span class="eyebrow">Beheer</span><h1>Afdelingen</h1></div>
    <a class="button button--primary button--small" href="<?= e(url('/afdelingen/nieuw')) ?>">+ Nieuwe afdeling</a>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<?php if (empty($departments)): ?>
    <div class="empty">Nog geen afdelingen toegevoegd.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($departments as $department): ?>
            <div class="card">
                <div class="card-row">
                    <h3><?= e($department['name']) ?></h3>
                    <a class="button button--ghost button--small" href="<?= e(url('/afdelingen/' . $department['id'] . '/bewerken')) ?>">Bewerken</a>
                </div>
                <?php if ($department['description']): ?><small><?= e($department['description']) ?></small><?php endif; ?>
                <div style="margin-top:8px">
                    <a class="button button--soft button--small" href="<?= e(url('/draaiboeken?department=' . $department['id'])) ?>">Draaiboeken bekijken</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
