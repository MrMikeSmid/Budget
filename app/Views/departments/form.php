<?php $isEdit = $department !== null; ?>
<header class="topbar">
    <div><span class="eyebrow"><?= $isEdit ? 'Bewerken' : 'Nieuw' ?></span><h1><?= $isEdit ? e($department['name']) : 'Nieuwe afdeling' ?></h1></div>
    <a class="icon-button" href="<?= e(url('/afdelingen')) ?>">×</a>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<form method="post" action="<?= e(url($isEdit ? '/afdelingen/' . $department['id'] . '/update' : '/afdelingen')) ?>">
    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
    <label class="field"><span>Naam</span><input name="name" maxlength="80" value="<?= e($department['name'] ?? '') ?>" required autofocus></label>
    <label class="field"><span>Omschrijving</span><textarea name="description" placeholder="Waar is deze afdeling verantwoordelijk voor?"><?= e($department['description'] ?? '') ?></textarea></label>
    <button class="button button--primary button--wide" type="submit"><?= $isEdit ? 'Opslaan' : 'Afdeling aanmaken' ?></button>
</form>

<?php if ($isEdit): ?>
    <form method="post" action="<?= e(url('/afdelingen/' . $department['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('Deze afdeling en alle bijbehorende draaiboeken worden verwijderd. Doorgaan?')">
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <button class="button button--danger button--wide" type="submit">Afdeling verwijderen</button>
    </form>
<?php endif; ?>
