<header class="topbar">
    <div><span class="eyebrow">Overzicht</span><h1>Draaiboeken</h1></div>
    <div style="display:flex;gap:8px">
        <a class="button button--soft button--small" href="<?= e(url('/afdelingen')) ?>">Afdelingen</a>
        <a class="button button--primary button--small" href="<?= e(url('/draaiboeken/nieuw')) ?>">+ Nieuw</a>
    </div>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<div class="chip-row">
    <a class="chip <?= $selectedDepartmentId === null ? 'active' : '' ?>" href="<?= e(url('/draaiboeken')) ?>">Alle afdelingen</a>
    <?php foreach ($departments as $department): ?>
        <a class="chip <?= $selectedDepartmentId === (int) $department['id'] ? 'active' : '' ?>" href="<?= e(url('/draaiboeken?department=' . $department['id'])) ?>"><?= e($department['name']) ?></a>
    <?php endforeach; ?>
</div>

<?php if (empty($playbooks)): ?>
    <div class="empty">Nog geen draaiboeken gevonden.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($playbooks as $playbook): ?>
            <a class="card card-link" href="<?= e(url('/draaiboeken/' . $playbook['id'])) ?>">
                <h3><?= e($playbook['title']) ?></h3>
                <small><?= e($playbook['department_name']) ?> · Leiding: <?= e($playbook['leader_name']) ?></small>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
