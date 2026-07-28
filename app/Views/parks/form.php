<?php $isEdit = $park !== null; ?>
<header class="topbar">
    <div><span class="eyebrow"><?= $isEdit ? 'Bewerken' : 'Nieuw' ?></span><h1><?= $isEdit ? e($park['name']) : 'Nieuw park' ?></h1></div>
    <a class="icon-button" href="<?= e(url($isEdit ? '/parken/' . $park['id'] : '/parken')) ?>">×</a>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<form method="post" action="<?= e(url($isEdit ? '/parken/' . $park['id'] . '/update' : '/parken')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <label class="field"><span>Naam</span><input name="name" maxlength="80" value="<?= e($park['name'] ?? '') ?>" required autofocus></label>
    <label class="field"><span>Locatie</span><input name="location" maxlength="160" value="<?= e($park['location'] ?? '') ?>" placeholder="Bijv. adres of plaats"></label>
    <label class="field"><span>Bijzonderheden &amp; aandachtspunten</span><textarea name="attention_points" placeholder="Wat is anders aan dit park, waar moet je op letten?"><?= e($park['attention_points'] ?? '') ?></textarea></label>
    <button class="button button--primary button--wide" type="submit"><?= $isEdit ? 'Opslaan' : 'Park aanmaken' ?></button>
</form>

<?php if ($isEdit): ?>
    <form method="post" action="<?= e(url('/parken/' . $park['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('Dit park en alle bijbehorende notities, afspraken en taken worden verwijderd. Gekoppelde medewerkers en gasten blijven bestaan, maar verliezen de koppeling met dit park. Doorgaan?')">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <button class="button button--danger button--wide" type="submit">Park verwijderen</button>
    </form>
<?php endif; ?>
