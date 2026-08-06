<?php

use App\Support\BrandIcons;
use App\Support\Csrf;
use App\Support\View;

/** @var array $mappings */
/** @var array $icons */
?>
<button type="button" class="fab-button" data-toggle-target="add-form-panel" aria-label="Icoon koppelen">+</button>

<div class="form-panel" id="add-form-panel" hidden>
    <div class="card">
        <h2 class="mt-0">Icoon koppelen</h2>
        <p class="text-muted">Vul de exacte omschrijving in zoals je die bij een vaste last of inkomst gebruikt (bijv. "Netflix") — elke regel met die omschrijving toont dan automatisch dit icoon.</p>
        <form class="inline-form" method="post" action="<?= View::e(View::url('iconen-save')) ?>">
            <?= Csrf::field() ?>
            <div class="field">
                <label for="description">Omschrijving</label>
                <input type="text" id="description" name="description" required placeholder="bijv. Netflix">
            </div>
            <div class="field">
                <label for="icon-search">Zoek icoon</label>
                <input type="text" id="icon-search" data-filter-target="icon-grid" data-filter-min="2" data-filter-hint="icon-grid-hint" placeholder="typ minimaal 2 tekens, bijv. netflix" autocomplete="off">
            </div>
            <p id="icon-grid-hint" class="text-muted">Typ om te zoeken in <?= count($icons) ?> beschikbare merken.</p>
            <div id="icon-grid" class="icon-grid">
                <?php foreach ($icons as $slug => $icon): ?>
                    <label class="icon-grid-item" data-filter="<?= View::e(mb_strtolower($icon['title'] . ' ' . $slug)) ?>" hidden>
                        <input type="radio" name="icon_slug" value="<?= View::e($slug) ?>" required>
                        <img src="<?= View::e(View::asset('icons/brands/' . $slug . '.' . BrandIcons::extension($slug))) ?>" alt="" width="26" height="26" loading="lazy">
                        <span><?= View::e($icon['title']) ?></span>
                    </label>
                <?php endforeach; ?>
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
                            <?php if (isset($icons[$m['icon_slug']])): ?>
                                <img src="<?= View::e(View::asset('icons/brands/' . $m['icon_slug'] . '.' . BrandIcons::extension($m['icon_slug']))) ?>" alt="" width="24" height="24">
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
