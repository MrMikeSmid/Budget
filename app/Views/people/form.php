<?php $isEdit = $person !== null; ?>
<header class="topbar">
    <div><span class="eyebrow"><?= e(person_type_label($type)) ?></span><h1><?= $isEdit ? e($person['name']) : 'Nieuw persoon' ?></h1></div>
    <a class="icon-button" href="<?= e(url($isEdit ? '/personen/' . $person['id'] : '/personen')) ?>">×</a>
</header>

<?php foreach (pull_flashes() as $t => $message): ?>
    <div class="toast toast--<?= e($t) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<form method="post" action="<?= e(url($isEdit ? '/personen/' . $person['id'] . '/update' : '/personen')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <fieldset>
        <legend>Type</legend>
        <div class="choice-row">
            <?php foreach (['staff', 'guest', 'candidate'] as $t): ?>
                <label><input type="radio" name="type" value="<?= $t ?>" <?= $type === $t ? 'checked' : '' ?>> <?= e(person_type_label($t)) ?></label>
            <?php endforeach; ?>
        </div>
    </fieldset>
    <label class="field candidate-field"><span>Sollicitatiestatus</span>
        <select name="application_status">
            <?php foreach (['nieuw', 'gesprek_gepland', 'afgewezen', 'aangenomen'] as $s): ?>
                <option value="<?= $s ?>" <?= ($person['application_status'] ?? 'nieuw') === $s ? 'selected' : '' ?>><?= e(application_status_label($s)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="field"><span>Naam</span><input name="name" maxlength="100" value="<?= e($person['name'] ?? '') ?>" required autofocus></label>
    <label class="field"><span>Functie / rol</span><input name="role" maxlength="100" value="<?= e($person['role'] ?? '') ?>" placeholder="Bijv. Receptie, Housekeeping"></label>
    <div class="field-row">
        <label class="field"><span>E-mail</span><input type="email" name="email" value="<?= e($person['email'] ?? '') ?>"></label>
        <label class="field"><span>Telefoon</span><input name="phone" value="<?= e($person['phone'] ?? '') ?>"></label>
    </div>
    <fieldset>
        <legend>Werkt bij</legend>
        <?php if (empty($parks)): ?>
            <p style="margin:0;font-size:12px;color:var(--muted)">Nog geen parken aangemaakt. <a href="<?= e(url('/parken/nieuw')) ?>">Maak eerst een park aan</a>.</p>
        <?php else: ?>
            <div class="choice-row">
                <?php foreach ($parks as $park): ?>
                    <label><input type="checkbox" name="park_ids[]" value="<?= (int) $park['id'] ?>" <?= in_array((int) $park['id'], $personParkIds, true) ? 'checked' : '' ?>> <?= e($park['name']) ?></label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </fieldset>
    <label class="field"><span>Standaard notitie</span><textarea name="notes" placeholder="Korte, blijvende opmerking over deze persoon"><?= e($person['notes'] ?? '') ?></textarea></label>
    <?php if ($isEdit): ?>
        <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= ($person['is_active'] ?? 1) ? 'checked' : '' ?>> Actief</label>
    <?php endif; ?>
    <button class="button button--primary button--wide" type="submit"><?= $isEdit ? 'Opslaan' : 'Toevoegen' ?></button>
</form>

<?php if ($isEdit): ?>
    <form method="post" action="<?= e(url('/personen/' . $person['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('Deze persoon en alle bijbehorende notities, verzuim- en gespreksgegevens worden verwijderd. Doorgaan?')">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <button class="button button--danger button--wide" type="submit">Verwijderen</button>
    </form>
<?php endif; ?>

<script>
(() => {
    const syncCandidateField = (form) => {
        const checked = form.querySelector('input[name="type"]:checked');
        const field = form.querySelector('.candidate-field');
        if (checked && field) { field.style.display = checked.value === 'candidate' ? '' : 'none'; }
    };
    document.querySelectorAll('form').forEach(syncCandidateField);
    document.addEventListener('change', (event) => {
        if (event.target.name === 'type') { syncCandidateField(event.target.closest('form')); }
    });
})();
</script>
