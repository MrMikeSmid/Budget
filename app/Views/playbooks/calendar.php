<header class="topbar">
    <div><span class="eyebrow"><?= e($playbook['department_name']) ?></span><h1><?= e($playbook['title']) ?></h1></div>
    <a class="icon-button" href="<?= e($backUrl) ?>">×</a>
</header>

<?php include __DIR__ . '/_calendar.php'; ?>
