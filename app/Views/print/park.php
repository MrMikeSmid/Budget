<div class="print-header">
    <div>
        <span class="eyebrow">Park</span>
        <h1><?= e($park['name']) ?></h1>
        <?php if ($park['location']): ?><div><?= e($park['location']) ?></div><?php endif; ?>
    </div>
    <small><?= e(date('d-m-Y')) ?></small>
</div>

<?php if ($park['attention_points']): ?>
    <div class="print-section"><h2>Bijzonderheden &amp; aandachtspunten</h2><p style="white-space:pre-wrap"><?= e($park['attention_points']) ?></p></div>
<?php endif; ?>

<div class="print-section">
    <h2>Medewerkers</h2>
    <?php if (empty($staff)): ?><p>Geen medewerkers.</p><?php else: ?>
        <?php foreach ($staff as $person): ?><div class="print-row"><strong><?= e($person['name']) ?></strong><?= $person['role'] ? ' — ' . e($person['role']) : '' ?></div><?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="print-section">
    <h2>Gasten</h2>
    <?php if (empty($guests)): ?><p>Geen gastdossiers.</p><?php else: ?>
        <?php foreach ($guests as $person): ?><div class="print-row"><strong><?= e($person['name']) ?></strong><?= $person['role'] ? ' — ' . e($person['role']) : '' ?></div><?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="print-section">
    <h2>Notities, afspraken &amp; taken</h2>
    <?php if (empty($items)): ?><p>Niets vastgelegd.</p><?php else: ?>
        <?php foreach ($items as $item): ?>
            <div class="print-row">
                <strong><?= e($item['title']) ?></strong> — <?= e(category_label($item['category'])) ?>, <?= e(item_type_label($item['type'])) ?>, <?= e(status_label($item['status'])) ?><?= $item['due_date'] ? ', vervalt ' . e(format_date($item['due_date'])) : '' ?>
                <?php if ($item['body']): ?><div style="white-space:pre-wrap"><?= e($item['body']) ?></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
