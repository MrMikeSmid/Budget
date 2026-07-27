<?php $isEdit = $playbook !== null; ?>
<header class="topbar">
    <div><span class="eyebrow"><?= $isEdit ? 'Bewerken' : 'Nieuw' ?></span><h1><?= $isEdit ? e($playbook['title']) : 'Nieuw draaiboek' ?></h1></div>
    <a class="icon-button" href="<?= e(url($isEdit ? '/draaiboeken/' . $playbook['id'] : '/draaiboeken')) ?>">×</a>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<?php if (empty($departments)): ?>
    <p style="font-size:13px;color:var(--muted)">Je hebt nog geen afdelingen. <a href="<?= e(url('/afdelingen/nieuw')) ?>">Maak eerst een afdeling aan</a>.</p>
<?php else: ?>
<form method="post" action="<?= e(url($isEdit ? '/draaiboeken/' . $playbook['id'] . '/update' : '/draaiboeken')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <label class="field"><span>Titel</span><input name="title" maxlength="120" value="<?= e($playbook['title'] ?? '') ?>" required autofocus placeholder="Bijv. Openingsprocedure zomerseizoen"></label>
    <div class="field-row">
        <label class="field"><span>Afdeling</span>
            <select name="department_id" required>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= (int) $department['id'] ?>" <?= (int) ($playbook['department_id'] ?? 0) === (int) $department['id'] ? 'selected' : '' ?>><?= e($department['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field"><span>Park (optioneel)</span>
            <select name="park_id">
                <option value="">Geen specifiek park</option>
                <?php foreach ($parks as $park): ?>
                    <option value="<?= (int) $park['id'] ?>" <?= (int) ($playbook['park_id'] ?? 0) === (int) $park['id'] ? 'selected' : '' ?>><?= e($park['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <fieldset>
        <legend>Leidinggevende</legend>
        <div class="field-row">
            <label class="field"><span>Bestaande medewerker</span>
                <select name="leader_person_id" id="leaderPersonSelect">
                    <option value="">Vrije tekst hiernaast</option>
                    <?php foreach ($people as $person): ?>
                        <option value="<?= (int) $person['id'] ?>" data-name="<?= e($person['name']) ?>" <?= (int) ($playbook['leader_person_id'] ?? 0) === (int) $person['id'] ? 'selected' : '' ?>><?= e($person['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field"><span>Naam</span><input name="leader_name" id="leaderNameInput" value="<?= e($playbook['leader_name'] ?? '') ?>" placeholder="Verplicht zonder gekozen medewerker" <?= !empty($playbook['leader_person_id']) ? 'disabled' : '' ?>></label>
        </div>
    </fieldset>
    <label class="field"><span>Toelichting</span><textarea name="description" placeholder="Waar gaat dit draaiboek over?"><?= e($playbook['description'] ?? '') ?></textarea></label>
    <button class="button button--primary button--wide" type="submit"><?= $isEdit ? 'Opslaan' : 'Draaiboek aanmaken' ?></button>
</form>

<?php if ($isEdit): ?>
    <form method="post" action="<?= e(url('/draaiboeken/' . $playbook['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('Dit draaiboek en alle stappen worden verwijderd. Doorgaan?')">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <button class="button button--danger button--wide" type="submit">Draaiboek verwijderen</button>
    </form>
<?php endif; ?>

<script>
(() => {
    const select = document.getElementById('leaderPersonSelect');
    const input = document.getElementById('leaderNameInput');
    if (!select || !input) return;
    select.addEventListener('change', () => {
        const option = select.options[select.selectedIndex];
        if (select.value) {
            input.value = option.dataset.name || '';
            input.disabled = true;
        } else {
            input.disabled = false;
        }
    });
})();
</script>
<?php endif; ?>
