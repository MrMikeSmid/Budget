<div class="print-header">
    <div>
        <span class="eyebrow"><?= e($playbook['department_name']) ?><?= $playbook['park_name'] ? ' · ' . e($playbook['park_name']) : '' ?></span>
        <h1><?= e($playbook['title']) ?></h1>
        <div>Leiding: <?= e($playbook['leader_name']) ?></div>
    </div>
</div>

<?php if ($playbook['description']): ?>
    <div class="print-section"><p style="white-space:pre-wrap"><?= e($playbook['description']) ?></p></div>
<?php endif; ?>

<div class="print-section">
    <h2>Tijdlijn</h2>
    <?php if (empty($steps)): ?>
        <p>Nog geen stappen vastgelegd.</p>
    <?php else: ?>
        <?php foreach ($steps as $step): ?>
            <div class="print-row">
                <strong><?= e(schedule_type_label($step['schedule_type'])) ?> <?= e(format_date($step['date'])) ?>: <?= e($step['title']) ?></strong>
                <?php if ($step['body']): ?><div style="white-space:pre-wrap"><?= e($step['body']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
