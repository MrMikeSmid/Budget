<header class="topbar">
    <div><span class="eyebrow"><?= e($playbook['department_name']) ?></span><h1><?= e($playbook['title']) ?></h1></div>
    <a class="icon-button" href="<?= e(url('/draaiboeken/' . $playbook['id'] . '/bewerken')) ?>" title="Bewerken">✎</a>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<div class="card">
    <div><strong>Leiding:</strong> <?= e($playbook['leader_name']) ?></div>
    <?php if ($playbook['description']): ?><p style="margin:10px 0 0;font-size:13px;white-space:pre-wrap"><?= e($playbook['description']) ?></p><?php endif; ?>
</div>

<div class="card" style="background:var(--brand-soft)">
    <div class="card-row">
        <div><strong>Deelbare link</strong><br><small>Iedereen met deze link kan het draaiboek bekijken, zonder in te loggen.</small></div>
    </div>
    <div class="field-row" style="margin-top:10px;align-items:flex-end">
        <label class="field" style="margin-bottom:0"><input readonly onclick="this.select()" value="<?= e($shareUrl) ?>"></label>
        <form method="post" action="<?= e(url('/draaiboeken/' . $playbook['id'] . '/vernieuw-link')) ?>" onsubmit="return confirm('De huidige link stopt direct met werken. Nieuwe link genereren?')">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <button class="button button--soft button--small" type="submit">Vernieuw link</button>
        </form>
    </div>
</div>

<div class="section-heading"><h2>Kalender</h2></div>
<?php include __DIR__ . '/_calendar.php'; ?>

<div class="section-heading"><h2>Tijdlijn</h2></div>
<div class="filter-row">
    <select class="filter-select" onchange="location.href=this.value">
        <option value="<?= e(url('/draaiboeken/' . $playbook['id'])) ?>" <?= $selectedParkId === null ? 'selected' : '' ?>>Alle parken</option>
        <?php foreach ($parks as $park): ?>
            <option value="<?= e(url('/draaiboeken/' . $playbook['id'] . '?park=' . $park['id'])) ?>" <?= $selectedParkId === (int) $park['id'] ? 'selected' : '' ?>><?= e($park['name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<?php if (empty($steps)): ?>
    <div class="empty">Nog geen stappen gevonden.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($steps as $step): $state = playbook_step_state($step); ?>
            <div class="card">
                <div class="card-row">
                    <h3><?= e(step_type_label($step['type'])) ?>: <?= e($step['title']) ?></h3>
                    <span class="badge <?= $state['class'] ?>"><?= e($state['label']) ?></span>
                </div>
                <small><?= e(playbook_step_schedule_label($step)) ?> · <?= $step['park_name'] ? e($step['park_name']) : 'Alle parken' ?></small>
                <?php if ($step['body']): ?><p style="margin:8px 0 0;font-size:13px;white-space:pre-wrap"><?= e($step['body']) ?></p><?php endif; ?>
                <div class="card-row" style="margin-top:10px;gap:8px">
                    <form method="post" action="<?= e(url('/stappen/' . $step['id'] . '/toggle')) ?>">
                        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                        <button class="button button--soft button--small" type="submit"><?= $step['status'] === 'afgerond' ? 'Heropenen' : 'Afronden' ?></button>
                    </form>
                    <details class="edit-toggle" style="flex:1">
                        <summary>Bewerken</summary>
                        <div class="card">
                            <form method="post" action="<?= e(url('/stappen/' . $step['id'] . '/update')) ?>">
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <label class="field"><span>Park</span>
                                    <select name="park_id">
                                        <option value="">Alle parken</option>
                                        <?php foreach ($parks as $park): ?>
                                            <option value="<?= (int) $park['id'] ?>" <?= (int) ($step['park_id'] ?? 0) === (int) $park['id'] ? 'selected' : '' ?>><?= e($park['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <fieldset>
                                    <legend>Type</legend>
                                    <div class="choice-row">
                                        <label><input type="radio" name="type" value="eenmalig" <?= $step['type'] === 'eenmalig' ? 'checked' : '' ?>> Eenmalig</label>
                                        <label><input type="radio" name="type" value="periodiek" <?= $step['type'] === 'periodiek' ? 'checked' : '' ?>> Periodiek</label>
                                    </div>
                                </fieldset>
                                <div class="field-row">
                                    <label class="field"><span>Startdatum</span><input type="date" name="start_date" value="<?= e($step['start_date']) ?>" required></label>
                                    <label class="field"><span>Einddatum</span><input type="date" name="end_date" value="<?= e($step['end_date']) ?>" required></label>
                                </div>
                                <label class="field recurrence-field"><span>Herhaling</span>
                                    <select name="recurrence_interval">
                                        <?php foreach (['dagelijks' => 'Dagelijks', 'wekelijks' => 'Wekelijks', 'maandelijks' => 'Maandelijks'] as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= $step['recurrence_interval'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label class="field"><span>Titel</span><input name="title" maxlength="160" value="<?= e($step['title']) ?>" required></label>
                                <label class="field"><span>Toelichting</span><textarea name="body"><?= e($step['body']) ?></textarea></label>
                                <button class="button button--primary button--wide" type="submit">Opslaan</button>
                            </form>
                            <form method="post" action="<?= e(url('/stappen/' . $step['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('Stap verwijderen?')">
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <button class="button button--danger button--wide" type="submit">Verwijderen</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<details class="edit-toggle">
    <summary>+ Nieuwe stap</summary>
    <div class="card">
        <form method="post" action="<?= e(url('/draaiboeken/' . $playbook['id'] . '/stappen')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Park</span>
                <select name="park_id">
                    <option value="" <?= $selectedParkId === null ? 'selected' : '' ?>>Alle parken</option>
                    <?php foreach ($parks as $park): ?>
                        <option value="<?= (int) $park['id'] ?>" <?= $selectedParkId === (int) $park['id'] ? 'selected' : '' ?>><?= e($park['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <fieldset>
                <legend>Type</legend>
                <div class="choice-row">
                    <label><input type="radio" name="type" value="eenmalig" checked> Eenmalig</label>
                    <label><input type="radio" name="type" value="periodiek"> Periodiek</label>
                </div>
            </fieldset>
            <div class="field-row">
                <label class="field"><span>Startdatum</span><input type="date" name="start_date" required></label>
                <label class="field"><span>Einddatum</span><input type="date" name="end_date" required></label>
            </div>
            <label class="field recurrence-field"><span>Herhaling</span>
                <select name="recurrence_interval">
                    <option value="dagelijks">Dagelijks</option>
                    <option value="wekelijks">Wekelijks</option>
                    <option value="maandelijks">Maandelijks</option>
                </select>
            </label>
            <label class="field"><span>Titel</span><input name="title" maxlength="160" required placeholder="Wat moet er gebeuren?"></label>
            <label class="field"><span>Toelichting</span><textarea name="body"></textarea></label>
            <button class="button button--primary button--wide" type="submit">Stap toevoegen</button>
        </form>
    </div>
</details>

<script>
(() => {
    const syncRecurrenceField = (form) => {
        const checked = form.querySelector('input[name="type"]:checked');
        const field = form.querySelector('.recurrence-field');
        if (checked && field) { field.style.display = checked.value === 'periodiek' ? '' : 'none'; }
    };
    document.querySelectorAll('form').forEach(syncRecurrenceField);
    document.addEventListener('change', (event) => {
        if (event.target.name === 'type') { syncRecurrenceField(event.target.closest('form')); }
    });
})();
</script>
