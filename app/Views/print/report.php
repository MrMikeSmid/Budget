<div class="print-header">
    <div>
        <span class="eyebrow">Rapportage voor de Parkmanager</span>
        <h1><?= e($park['name']) ?></h1>
    </div>
    <small><?= e(date('d-m-Y')) ?></small>
</div>

<div class="print-section">
    <h2>Openstaande zaken</h2>
    <?php if (empty($openItems)): ?><p>Niets openstaand.</p><?php else: ?>
        <?php foreach ($openItems as $item): $overdue = is_overdue($item['due_date'], $item['status']); ?>
            <div class="print-row">
                <strong><?= e($item['title']) ?></strong> — <?= e(item_type_label($item['type'])) ?>, <?= $overdue ? 'vervallen' : e(status_label($item['status'])) ?><?= $item['due_date'] ? ', vervalt ' . e(format_date($item['due_date'])) : '' ?><?= $item['person_name'] ? ' · ' . e($item['person_name']) : '' ?>
                <?php if ($item['body']): ?><div style="white-space:pre-wrap"><?= e($item['body']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="print-section">
    <h2>Actief verzuim</h2>
    <?php if (empty($activeAbsences)): ?><p>Geen lopend verzuim.</p><?php else: ?>
        <?php foreach ($activeAbsences as $absence): ?>
            <div class="print-row">
                <strong><?= e($absence['person_name']) ?></strong> — <?= e(absence_status_label($absence['status'])) ?>, sinds <?= e(format_date($absence['start_date'])) ?><?= $absence['end_date'] ? ' t/m ' . e(format_date($absence['end_date'])) : ' (loopt nog)' ?>
                <?php if ($absence['reason']): ?><div><?= e($absence['reason']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="print-section">
    <h2>Aankomende vervolggesprekken</h2>
    <?php if (empty($upcomingReviews)): ?><p>Geen vervolggesprekken gepland.</p><?php else: ?>
        <?php foreach ($upcomingReviews as $review): ?>
            <div class="print-row">
                <strong><?= e($review['person_name']) ?></strong> — <?= e(review_type_label($review['type'])) ?>, vervolg op <?= e(format_date($review['follow_up_date'])) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
