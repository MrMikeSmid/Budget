<?php $isEdit = $item !== null; ?>
<header class="topbar">
    <div><span class="eyebrow"><?= e($park['name']) ?></span><h1><?= $isEdit ? 'Bewerken' : 'Nieuw' ?></h1></div>
    <a class="icon-button" href="<?= e(url('/parken/' . $park['id'])) ?>">×</a>
</header>

<?php foreach (pull_flashes() as $t => $message): ?>
    <div class="toast toast--<?= e($t) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<form method="post" action="<?= e(url($isEdit ? '/items/' . $item['id'] . '/update' : '/parken/' . $park['id'] . '/items')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <?php if (!$isEdit): ?>
        <fieldset>
            <legend>Categorie</legend>
            <div class="choice-row">
                <?php foreach (['personeel', 'park', 'gasten', 'taken'] as $cat): ?>
                    <label><input type="radio" name="category" value="<?= $cat ?>" <?= $category === $cat ? 'checked' : '' ?>> <?= e(category_label($cat)) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <fieldset>
            <legend>Type</legend>
            <div class="choice-row">
                <?php foreach (['notitie', 'afspraak', 'taak', 'klacht', 'controle'] as $type): ?>
                    <label><input type="radio" name="type" value="<?= $type ?>" <?= $type === 'notitie' ? 'checked' : '' ?>> <?= e(item_type_label($type)) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?php if (!empty($people)): ?>
            <label class="field"><span>Persoon (optioneel)</span>
                <select name="person_id">
                    <option value="">Geen specifieke persoon</option>
                    <?php foreach ($people as $person): ?>
                        <option value="<?= $person['id'] ?>" <?= $personId === (int) $person['id'] ? 'selected' : '' ?>><?= e($person['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
    <?php endif; ?>
    <label class="field"><span>Titel</span><input name="title" maxlength="160" value="<?= e($item['title'] ?? '') ?>" required autofocus></label>
    <label class="field"><span>Toelichting</span><textarea name="body"><?= e($item['body'] ?? '') ?></textarea></label>
    <label class="field"><span>Gastnaam (optioneel, als er geen gekoppelde persoon is)</span><input name="guest_name" maxlength="100" value="<?= e($item['guest_name'] ?? '') ?>"></label>
    <label class="field"><span>Vervaldatum (optioneel)</span><input type="date" name="due_date" value="<?= e($item['due_date'] ?? '') ?>"></label>
    <?php if ($isEdit): ?>
        <label class="field"><span>Status</span><select name="status">
            <?php foreach (['open', 'in_uitvoering', 'afgerond', 'gearchiveerd', 'omgezet_compliment'] as $s): ?>
                <option value="<?= $s ?>" <?= $item['status'] === $s ? 'selected' : '' ?>><?= e(status_label($s)) ?></option>
            <?php endforeach; ?>
        </select></label>
    <?php endif; ?>
    <button class="button button--primary button--wide" type="submit"><?= $isEdit ? 'Opslaan' : 'Toevoegen' ?></button>
</form>

<?php if ($isEdit): ?>
    <form method="post" action="<?= e(url('/items/' . $item['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('Weet je zeker dat je dit wilt verwijderen?')">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <button class="button button--danger button--wide" type="submit">Verwijderen</button>
    </form>
<?php endif; ?>
