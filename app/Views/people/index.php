<header class="topbar">
    <div><span class="eyebrow">Overzicht</span><h1>Personen</h1></div>
    <a class="button button--primary button--small" href="<?= e(url('/personen/nieuw')) ?>">+ Nieuw persoon</a>
</header>

<div class="filter-row">
    <select class="filter-select" onchange="location.href=this.value">
        <option value="<?= e(url('/personen' . ($selectedType ? '?type=' . $selectedType : ''))) ?>" <?= $selectedParkId === null ? 'selected' : '' ?>>Alle parken</option>
        <?php foreach ($parks as $park): ?>
            <option value="<?= e(url('/personen?park=' . $park['id'] . ($selectedType ? '&type=' . $selectedType : ''))) ?>" <?= $selectedParkId === (int) $park['id'] ? 'selected' : '' ?>><?= e($park['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="filter-select" onchange="location.href=this.value">
        <option value="<?= e(url('/personen' . ($selectedParkId ? '?park=' . $selectedParkId : ''))) ?>" <?= $selectedType === null ? 'selected' : '' ?>>Alle types</option>
        <option value="<?= e(url('/personen?type=staff' . ($selectedParkId ? '&park=' . $selectedParkId : ''))) ?>" <?= $selectedType === 'staff' ? 'selected' : '' ?>>Medewerkers</option>
        <option value="<?= e(url('/personen?type=guest' . ($selectedParkId ? '&park=' . $selectedParkId : ''))) ?>" <?= $selectedType === 'guest' ? 'selected' : '' ?>>Gasten</option>
        <option value="<?= e(url('/personen?type=candidate' . ($selectedParkId ? '&park=' . $selectedParkId : ''))) ?>" <?= $selectedType === 'candidate' ? 'selected' : '' ?>>Sollicitanten</option>
    </select>
</div>

<?php if (empty($people)): ?>
    <div class="empty">Niemand gevonden. <a href="<?= e(url('/personen/nieuw')) ?>">Voeg iemand toe</a>.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($people as $person): ?>
            <a class="card card-link" href="<?= e(url('/personen/' . $person['id'])) ?>">
                <div class="card-row">
                    <h3><?= e($person['name']) ?></h3>
                    <?php if ($person['type'] === 'candidate'): ?>
                        <span class="badge <?= $person['application_status'] === 'aangenomen' ? 'badge--ok' : ($person['application_status'] === 'afgewezen' ? 'badge--danger' : 'badge--warn') ?>"><?= e(application_status_label($person['application_status'])) ?></span>
                    <?php else: ?>
                        <span class="badge <?= $person['type'] === 'staff' ? 'badge--muted' : 'badge--warn' ?>"><?= e(person_type_label($person['type'])) ?></span>
                    <?php endif; ?>
                </div>
                <small><?= $person['park_names'] ? e($person['park_names']) : 'Geen park gekoppeld' ?><?= $person['role'] ? ' · ' . e($person['role']) : '' ?></small>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
