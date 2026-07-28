<header class="topbar">
    <div><span class="eyebrow">Overzicht</span><h1>Agenda</h1></div>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<?php $steps = $entries; include __DIR__ . '/../playbooks/_calendar.php'; ?>
