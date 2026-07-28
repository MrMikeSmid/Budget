<div class="print-header">
    <div>
        <span class="eyebrow"><?= e($playbook['department_name']) ?></span>
        <h1><?= e($playbook['title']) ?></h1>
        <div>Leiding: <?= e($playbook['leader_name']) ?></div>
    </div>
</div>

<?php if ($playbook['description']): ?>
    <div class="print-section"><p style="white-space:pre-wrap"><?= e($playbook['description']) ?></p></div>
<?php endif; ?>

<div class="print-actions">
    <a class="button button--soft" href="<?= e(url('/gedeeld/' . $token . '/kalender')) ?>">📅 Bekijk volledige kalender</a>
</div>

<div class="print-section">
    <h2>Tijdlijn</h2>
    <?php if (empty($steps)): ?>
        <p>Nog geen stappen vastgelegd.</p>
    <?php else: ?>
        <?php foreach ($steps as $step): ?>
            <div class="print-row">
                <strong><?= e(step_type_label($step['type'])) ?>: <?= e($step['title']) ?></strong>
                <div><?= e(playbook_step_schedule_label($step)) ?> · <?= $step['park_name'] ? e($step['park_name']) : 'Alle parken' ?></div>
                <?php if ($step['body']): ?><div style="white-space:pre-wrap"><?= e($step['body']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
