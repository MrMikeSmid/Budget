<?php
$qs = function (array $overrides) use ($selectedParkId, $selectedCategory, $selectedStatus): string {
    $params = array_filter([
        'park' => $overrides['park'] ?? $selectedParkId,
        'category' => $overrides['category'] ?? $selectedCategory,
        'status' => $overrides['status'] ?? $selectedStatus,
    ], static fn($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '';
};
?>
<header class="topbar">
    <div><span class="eyebrow">Overzicht</span><h1>Taken &amp; afspraken</h1></div>
</header>

<div class="chip-row">
    <a class="chip <?= $selectedParkId === null ? 'active' : '' ?>" href="<?= e(url('/items' . $qs(['park' => null]))) ?>">Alle parken</a>
    <?php foreach ($parks as $park): ?>
        <a class="chip <?= $selectedParkId === (int) $park['id'] ? 'active' : '' ?>" href="<?= e(url('/items' . $qs(['park' => $park['id']]))) ?>"><?= e($park['name']) ?></a>
    <?php endforeach; ?>
</div>
<div class="chip-row">
    <a class="chip <?= $selectedCategory === null ? 'active' : '' ?>" href="<?= e(url('/items' . $qs(['category' => null]))) ?>">Alle categorieën</a>
    <?php foreach (['personeel', 'park', 'gasten', 'taken'] as $cat): ?>
        <a class="chip <?= $selectedCategory === $cat ? 'active' : '' ?>" href="<?= e(url('/items' . $qs(['category' => $cat]))) ?>"><?= e(category_label($cat)) ?></a>
    <?php endforeach; ?>
</div>
<div class="chip-row">
    <a class="chip <?= $selectedStatus === 'open' ? 'active' : '' ?>" href="<?= e(url('/items' . $qs(['status' => 'open']))) ?>">Open</a>
    <a class="chip <?= $selectedStatus === 'alle' ? 'active' : '' ?>" href="<?= e(url('/items' . $qs(['status' => 'alle']))) ?>">Alle</a>
    <a class="chip <?= $selectedStatus === 'afgerond' ? 'active' : '' ?>" href="<?= e(url('/items' . $qs(['status' => 'afgerond']))) ?>">Afgerond</a>
</div>

<?php if (empty($items)): ?>
    <div class="empty">Niets gevonden.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($items as $item): $overdue = is_overdue($item['due_date'], $item['status']); ?>
            <div class="card">
                <div class="card-row">
                    <h3><?= e($item['title']) ?></h3>
                    <span class="badge <?= $item['status'] === 'afgerond' ? 'badge--ok' : ($overdue ? 'badge--danger' : 'badge--muted') ?>"><?= $overdue ? 'Vervallen' : e(status_label($item['status'])) ?></span>
                </div>
                <small><?= e($item['park_name']) ?> · <?= e(category_label($item['category'])) ?> · <?= e(item_type_label($item['type'])) ?><?= $item['person_name'] ? ' · ' . e($item['person_name']) : '' ?><?= $item['due_date'] ? ' · ' . e(format_date($item['due_date'])) : '' ?></small>
                <div class="card-row" style="margin-top:10px;gap:8px">
                    <form method="post" action="<?= e(url('/items/' . $item['id'] . '/toggle')) ?>">
                        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                        <button class="button button--soft button--small" type="submit"><?= $item['status'] === 'afgerond' ? 'Heropenen' : 'Afronden' ?></button>
                    </form>
                    <a class="button button--ghost button--small" href="<?= e(url('/items/' . $item['id'] . '/bewerken')) ?>">Bewerken</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
