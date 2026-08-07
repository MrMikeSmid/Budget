<?php

use App\Models\IconMapping;
use App\Support\Csrf;
use App\Support\View;

/** @var array $mappings */
?>
<button type="button" class="fab-button" data-toggle-target="add-form-panel" aria-label="Icoon koppelen">+</button>

<div class="form-panel" id="add-form-panel" hidden>
    <div class="card">
        <h2 class="mt-0">Icoon koppelen</h2>
        <p class="text-muted">Vul een woord in dat in de omschrijving van een vaste last of inkomst voorkomt (bijv. "Netflix" of "belasting") en upload een afbeelding — elke regel waar dat woord ergens in de titel staat toont dan automatisch dat icoon. Geldt voor alle huishoudens in de app, niet alleen dat van jou. PNG, JPG, GIF, WEBP of SVG, tot 2 MB.</p>
        <form class="inline-form" method="post" action="<?= View::e(View::url('iconen-save')) ?>" enctype="multipart/form-data">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="description">Omschrijving</label>
                <input type="text" id="description" name="description" required placeholder="bijv. Netflix">
            </div>
            <div class="field">
                <label for="icon">Afbeelding</label>
                <input type="file" id="icon" name="icon" accept=".png,.jpg,.jpeg,.gif,.webp,.svg,image/*" required>
            </div>
            <button type="submit" class="btn">Koppelen</button>
        </form>
    </div>
</div>

<div class="card">
    <h2 class="mt-0">Gekoppelde iconen</h2>
    <?php if (empty($mappings)): ?>
        <p class="text-muted">Nog geen iconen gekoppeld. Klik op "+" om te beginnen.</p>
    <?php else: ?>
        <div class="table-scroll">
            <table>
                <thead><tr><th></th><th class="nowrap">Omschrijving</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($mappings as $m): ?>
                    <tr>
                        <td style="width:36px;">
                            <?php if (IconMapping::absolutePath($m['icon_path']) !== null): ?>
                                <img src="<?= View::e(View::url('icoon-afbeelding', ['id' => $m['id']])) ?>" alt="" width="24" height="24">
                            <?php endif; ?>
                        </td>
                        <td class="nowrap"><?= View::e($m['description']) ?></td>
                        <td>
                            <form method="post" action="<?= View::e(View::url('iconen-delete')) ?>" onsubmit="return confirm('Koppeling verwijderen?');">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                <button type="submit" class="btn small danger">Verwijderen</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
