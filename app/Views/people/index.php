<header class="topbar">
    <div><span class="eyebrow">Overzicht</span><h1>Personen</h1></div>
    <a class="button button--primary button--small" href="<?= e(url('/personen/nieuw')) ?>">+ Nieuw persoon</a>
</header>

<div class="chip-row">
    <a class="chip <?= $selectedParkId === null ? 'active' : '' ?>" href="<?= e(url('/personen')) ?>">Alle parken</a>
    <?php foreach ($parks as $park): ?>
        <a class="chip <?= $selectedParkId === (int) $park['id'] ? 'active' : '' ?>" href="<?= e(url('/personen?park=' . $park['id'] . ($selectedType ? '&type=' . $selectedType : ''))) ?>"><?= e($park['name']) ?></a>
    <?php endforeach; ?>
</div>
<div class="chip-row">
    <a class="chip <?= $selectedType === null ? 'active' : '' ?>" href="<?= e(url('/personen' . ($selectedParkId ? '?park=' . $selectedParkId : ''))) ?>">Alle</a>
    <a class="chip <?= $selectedType === 'staff' ? 'active' : '' ?>" href="<?= e(url('/personen?type=staff' . ($selectedParkId ? '&park=' . $selectedParkId : ''))) ?>">Medewerkers</a>
    <a class="chip <?= $selectedType === 'guest' ? 'active' : '' ?>" href="<?= e(url('/personen?type=guest' . ($selectedParkId ? '&park=' . $selectedParkId : ''))) ?>">Gasten</a>
</div>

<?php if (empty($people)): ?>
    <div class="empty">Niemand gevonden. <a href="<?= e(url('/personen/nieuw')) ?>">Voeg iemand toe</a>.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($people as $person): ?>
            <a class="card card-link" href="<?= e(url('/personen/' . $person['id'])) ?>">
                <div class="card-row">
                    <h3><?= e($person['name']) ?></h3>
                    <span class="badge <?= $person['type'] === 'staff' ? 'badge--muted' : 'badge--warn' ?>"><?= $person['type'] === 'staff' ? 'Medewerker' : 'Gast' ?></span>
                </div>
                <small><?= $person['park_names'] ? e($person['park_names']) : 'Geen park gekoppeld' ?><?= $person['role'] ? ' · ' . e($person['role']) : '' ?></small>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
