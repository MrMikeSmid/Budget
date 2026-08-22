<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $rows */
/** @var string $bank */
/** @var array $banks */
/** @var array $categories */

$duplicateCount = count(array_filter($rows, static fn (array $r): bool => !empty($r['is_duplicate'])));
$importableCount = count($rows) - $duplicateCount;
?>
<div class="card">
    <h2 class="mt-0">Uitgaven controleren</h2>
    <p class="text-muted">
        <?= (int) count($rows) ?> uitgave<?= count($rows) === 1 ? '' : 'n' ?> gevonden in het <?= View::e($banks[$bank] ?? $bank) ?>-bestand<?php if ($duplicateCount > 0): ?>, waarvan <?= $duplicateCount ?> al eerder geïmporteerd (grijs, wordt overgeslagen)<?php endif; ?>.
        Vink aan wat een vaste last is, en of die terugkerend is — de rest komt gewoon als losse mutatie in Kasstroom, met de gekozen categorie.
    </p>
</div>

<?php if ($importableCount === 0): ?>
    <div class="card">
        <p class="text-muted">Alle gevonden uitgaven zijn al eerder geïmporteerd — er is niets nieuws om te importeren.</p>
        <a class="btn secondary" href="<?= View::e(View::url('kasstroom-import')) ?>">Terug</a>
    </div>
<?php else: ?>
    <form method="post" action="<?= View::e(View::url('kasstroom-import-commit')) ?>">
        <?= Csrf::field() ?>
        <div class="expense-list">
            <?php foreach ($rows as $index => $row): ?>
                <?php $isDuplicate = !empty($row['is_duplicate']); ?>
                <div class="expense-list-item" style="<?= $isDuplicate ? 'opacity:.55;' : '' ?> align-items: flex-start; flex-wrap: wrap; cursor: default;">
                    <span class="expense-list-body" style="flex: 1 1 220px;">
                        <span class="expense-list-title"><?= View::e($row['description']) ?></span>
                        <span class="expense-list-amount"><?= View::e($row['date']) ?><?php if ($isDuplicate): ?> · <span class="badge neutral">Al geïmporteerd</span><?php endif; ?></span>
                    </span>
                    <span class="expense-list-value negative" style="flex: none;"><?= View::money((float) $row['amount']) ?></span>

                    <?php if (!$isDuplicate): ?>
                        <div class="field-row" style="flex-basis: 100%; margin-top: 8px;">
                            <div class="field">
                                <label for="category_<?= (int) $index ?>">Categorie</label>
                                <select id="category_<?= (int) $index ?>" name="category_id[<?= (int) $index ?>]">
                                    <option value="">Geen categorie</option>
                                    <?php foreach ($categories as $c): ?>
                                        <option value="<?= (int) $c['id'] ?>"><?= View::e($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="checkbox-field" style="flex-basis: 100%;">
                            <input type="checkbox" id="vaste_last_<?= (int) $index ?>" name="vaste_last[<?= (int) $index ?>]"
                                onchange="document.getElementById('terugkerend_wrap_<?= (int) $index ?>').style.display = this.checked ? 'flex' : 'none';">
                            <label for="vaste_last_<?= (int) $index ?>">Vaste last</label>
                        </div>
                        <div class="checkbox-field" id="terugkerend_wrap_<?= (int) $index ?>" style="flex-basis: 100%; display: none;">
                            <input type="checkbox" id="terugkerend_<?= (int) $index ?>" name="terugkerend[<?= (int) $index ?>]">
                            <label for="terugkerend_<?= (int) $index ?>">Terugkerend — automatisch overnemen bij een nieuwe periode</label>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <button type="submit" class="btn block">Importeren</button>
            <a class="btn secondary block" style="margin-top: 8px;" href="<?= View::e(View::url('kasstroom-import')) ?>">Annuleren</a>
        </div>
    </form>
<?php endif; ?>
