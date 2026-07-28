<div class="print-header">
    <div>
        <span class="eyebrow"><?= $parks ? e(implode(', ', array_column($parks, 'name'))) . ' · ' : '' ?><?= $person['type'] === 'staff' ? 'Medewerker' : 'Gast' ?></span>
        <h1><?= e($person['name']) ?></h1>
        <?php if ($person['role']): ?><div><?= e($person['role']) ?></div><?php endif; ?>
        <?php if ($person['email'] || $person['phone']): ?><small><?= e($person['email']) ?><?= $person['email'] && $person['phone'] ? ' · ' : '' ?><?= e($person['phone']) ?></small><?php endif; ?>
    </div>
    <small><?= e(date('d-m-Y')) ?></small>
</div>

<?php if ($person['notes']): ?>
    <div class="print-section"><h2>Algemene notitie</h2><p style="white-space:pre-wrap"><?= e($person['notes']) ?></p></div>
<?php endif; ?>

<div class="print-section">
    <h2>Notities, afspraken &amp; taken</h2>
    <?php if (empty($items)): ?>
        <p>Niets vastgelegd.</p>
    <?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="print-row">
                <strong><?= e($item['title']) ?></strong> — <?= e(item_type_label($item['type'])) ?>, <?= e(status_label($item['status'])) ?><?= $item['due_date'] ? ', vervalt ' . e(format_date($item['due_date'])) : '' ?>
                <?php if ($item['body']): ?><div style="white-space:pre-wrap"><?= e($item['body']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($person['type'] === 'staff'): ?>
    <div class="print-section">
        <h2>Verzuim</h2>
        <?php if (empty($absences)): ?>
            <p>Geen verzuimregistraties.</p>
        <?php else: ?>
            <?php foreach ($absences as $absence): ?>
                <div class="print-row">
                    <strong><?= e(format_date($absence['start_date'])) ?><?= $absence['end_date'] ? ' – ' . e(format_date($absence['end_date'])) : ' (loopt nog)' ?></strong> — <?= e(absence_status_label($absence['status'])) ?><?= $absence['reason'] ? ', ' . e($absence['reason']) : '' ?>
                    <?php if ($absence['notes']): ?><div style="white-space:pre-wrap"><?= e($absence['notes']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="print-section">
        <h2>Functioneringsgesprekken</h2>
        <?php if (empty($reviews)): ?>
            <p>Nog geen gesprekken vastgelegd.</p>
        <?php else: ?>
            <?php foreach ($reviews as $review): ?>
                <div class="print-row">
                    <strong><?= e(format_date($review['review_date'])) ?></strong> — <?= e(review_type_label($review['type'])) ?><?= $review['follow_up_date'] ? ', vervolg ' . e(format_date($review['follow_up_date'])) : '' ?>
                    <?php if ($review['summary']): ?><div style="white-space:pre-wrap"><?= e($review['summary']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
