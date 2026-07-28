<?php
$qs = function (array $overrides) use ($selectedParkId, $selectedCategory, $selectedType, $selectedStatus): string {
    $params = array_filter([
        'park' => $overrides['park'] ?? $selectedParkId,
        'category' => $overrides['category'] ?? $selectedCategory,
        'type' => $overrides['type'] ?? $selectedType,
        'status' => $overrides['status'] ?? $selectedStatus,
    ], static fn($v) => $v !== null && $v !== '');
    return $params ? '?' . http_build_query($params) : '';
};
?>
<header class="topbar">
    <div><span class="eyebrow">Overzicht</span><h1>Taken &amp; afspraken</h1></div>
</header>

<div class="filter-row">
    <select class="filter-select" onchange="location.href=this.value">
        <option value="<?= e(url('/items' . $qs(['park' => null]))) ?>" <?= $selectedParkId === null ? 'selected' : '' ?>>Alle parken</option>
        <?php foreach ($parks as $park): ?>
            <option value="<?= e(url('/items' . $qs(['park' => $park['id']]))) ?>" <?= $selectedParkId === (int) $park['id'] ? 'selected' : '' ?>><?= e($park['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" onchange="location.href=this.value">
        <option value="<?= e(url('/items' . $qs(['category' => null]))) ?>" <?= $selectedCategory === null ? 'selected' : '' ?>>Alle categorieën</option>
        <?php foreach (['personeel', 'park', 'gasten', 'taken'] as $cat): ?>
            <option value="<?= e(url('/items' . $qs(['category' => $cat]))) ?>" <?= $selectedCategory === $cat ? 'selected' : '' ?>><?= e(category_label($cat)) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" onchange="location.href=this.value">
        <option value="<?= e(url('/items' . $qs(['type' => null]))) ?>" <?= $selectedType === null ? 'selected' : '' ?>>Alle types</option>
        <?php foreach (['notitie', 'afspraak', 'taak', 'klacht', 'controle'] as $t): ?>
            <option value="<?= e(url('/items' . $qs(['type' => $t]))) ?>" <?= $selectedType === $t ? 'selected' : '' ?>><?= e(item_type_label($t)) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" onchange="location.href=this.value">
        <option value="<?= e(url('/items' . $qs(['status' => 'open']))) ?>" <?= $selectedStatus === 'open' ? 'selected' : '' ?>>Open</option>
        <option value="<?= e(url('/items' . $qs(['status' => 'alle']))) ?>" <?= $selectedStatus === 'alle' ? 'selected' : '' ?>>Alle</option>
        <option value="<?= e(url('/items' . $qs(['status' => 'afgerond']))) ?>" <?= $selectedStatus === 'afgerond' ? 'selected' : '' ?>>Afgerond</option>
    </select>
</div>

<?php if (empty($items)): ?>
    <div class="empty">Niets gevonden.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($items as $item): $overdue = is_overdue($item['due_date'], $item['status']); ?>
            <div class="card">
                <div class="card-row">
                    <h3><?= e($item['title']) ?></h3>
                    <span class="badge <?= in_array($item['status'], ['afgerond', 'omgezet_compliment'], true) ? 'badge--ok' : ($overdue ? 'badge--danger' : 'badge--muted') ?>"><?= $overdue ? 'Vervallen' : e(status_label($item['status'])) ?></span>
                </div>
                <small><?= e($item['park_name']) ?> · <?= e(category_label($item['category'])) ?> · <?= e(item_type_label($item['type'])) ?><?= $item['person_name'] ? ' · ' . e($item['person_name']) : (!empty($item['guest_name']) ? ' · ' . e($item['guest_name']) : '') ?><?= $item['due_date'] ? ' · ' . e(format_date($item['due_date'])) : '' ?></small>
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
