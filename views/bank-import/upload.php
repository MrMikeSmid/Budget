<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $banks */
?>
<div class="card">
    <h2 class="mt-0">Bestand importeren</h2>
    <p class="text-muted">Upload een export van je bank. Alleen de uitgaven komen in de reviewlijst — daar kies je per regel of het een vaste last is, of die terugkerend is, en welke categorie erbij hoort. Er wordt pas iets opgeslagen ná die review.</p>
    <form class="inline-form" method="post" action="<?= View::e(View::url('kasstroom-import-upload')) ?>" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <div class="field">
            <label for="bank">Bank</label>
            <select id="bank" name="bank" required>
                <option value="">Kies je bank</option>
                <?php foreach ($banks as $key => $label): ?>
                    <option value="<?= View::e($key) ?>"><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="bestand">Bestand</label>
            <input type="file" id="bestand" name="bestand" accept=".csv,.xml,.sta,.swi,.txt,.940" required>
        </div>
        <button type="submit" class="btn">Inlezen</button>
    </form>
</div>
