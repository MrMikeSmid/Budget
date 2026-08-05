<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $categories */
/** @var array|null $editing */
?>
<p><a href="<?= View::e(View::url('instellingen')) ?>">&larr; Instellingen</a></p>

<button type="button" class="fab-button" data-toggle-target="add-form-panel" aria-label="Categorie toevoegen">+</button>

<div class="form-panel" id="add-form-panel" <?= $editing ? '' : 'hidden' ?>>
    <div class="card">
        <h2 class="mt-0"><?= $editing ? 'Categorie bewerken' : 'Nieuwe categorie' ?></h2>
        <form class="inline-form" method="post" action="<?= View::e(View::url('categorieen-save')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <div class="field">
                <label for="name">Naam</label>
                <input type="text" id="name" name="name" required value="<?= View::e($editing['name'] ?? '') ?>" placeholder="Bijv. Boodschappen">
            </div>
            <button type="submit" class="btn"><?= $editing ? 'Opslaan' : 'Toevoegen' ?></button>
            <?php if ($editing): ?>
                <a class="btn secondary" href="<?= View::e(View::url('categorieen')) ?>">Annuleren</a>
            <?php endif; ?>
        </form>
        <?php if ($editing): ?>
            <form method="post" action="<?= View::e(View::url('categorieen-delete')) ?>" onsubmit="return confirm('Categorie verwijderen? Regels die deze categorie hebben, komen zonder categorie te staan.');" style="margin-top:10px;">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                <button type="submit" class="btn small danger">Verwijderen</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2 class="mt-0">Alle categorieën</h2>
    <?php if (empty($categories)): ?>
        <p class="text-muted">Nog geen categorieën aangemaakt.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr class="row-clickable" data-href="<?= View::e(View::url('categorieen', ['edit' => $c['id']])) ?>">
                        <td><?= View::e($c['name']) ?></td>
                        <td>
                            <a class="btn small secondary" href="<?= View::e(View::url('categorie', ['id' => $c['id']])) ?>" onclick="event.stopPropagation();">Bekijken</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
