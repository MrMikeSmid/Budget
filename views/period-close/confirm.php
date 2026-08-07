<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $period */
/** @var array $otherPeriods */
/** @var int|null $defaultTargetId */
/** @var array $outstandingItems */
/** @var array $pots */
/** @var float $endingBalance */
/** @var bool $hasLinkedPot */
/** @var bool $showBalanceQuestion */
?>
<div style="margin-bottom:16px;">
    <a class="btn secondary" href="<?= View::e(View::url('vaste-lasten', ['period' => $period['id']])) ?>">&larr; Terug</a>
</div>

<div class="card">
    <h2 class="mt-0">Periode afsluiten: <?= View::e($period['name']) ?></h2>
    <p class="text-muted">Controleer hieronder wat je meeneemt naar een andere periode. Potjes hoeven nooit aangevinkt te worden — die lopen altijd gewoon door.</p>
</div>

<form method="post" action="<?= View::e(View::url('periode-afsluiten-uitvoeren')) ?>" id="close-period-form">
    <?= Csrf::field() ?>
    <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">

    <div class="card">
        <h2 class="mt-0">Openstaande &amp; niet volledig betaalde lasten</h2>
        <?php if (empty($outstandingItems)): ?>
            <p class="text-muted">Alles is al volledig betaald — niets om mee te nemen.</p>
        <?php else: ?>
            <p class="text-muted">Vink aan welke lasten (met het openstaande bedrag) meemoeten naar de volgende periode.</p>
            <div class="table-scroll">
                <table>
                    <thead><tr><th></th><th class="nowrap">Omschrijving</th><th class="num">Openstaand</th></tr></thead>
                    <tbody>
                    <?php foreach ($outstandingItems as $item): ?>
                        <tr>
                            <td style="width:36px;">
                                <input type="checkbox" name="carry_fixed_costs[]" value="<?= (int) $item['id'] ?>" id="carry-<?= (int) $item['id'] ?>">
                            </td>
                            <td class="nowrap">
                                <label for="carry-<?= (int) $item['id'] ?>" style="font-weight:normal; margin:0; display:inline;">
                                    <?= View::e($item['description']) ?>
                                    <?php if (!empty($item['is_recurring'])): ?> <span title="Terugkerend" class="text-muted">&#8635;</span><?php endif; ?>
                                </label>
                            </td>
                            <td class="num"><?= View::money((float) $item['outstanding_amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="mt-0">Potjes</h2>
        <?php if (empty($pots)): ?>
            <p class="text-muted">Nog geen potjes aangemaakt.</p>
        <?php else: ?>
            <p class="text-muted">Lopen automatisch door, geen actie nodig:</p>
            <ul style="margin:0; padding-left:20px;">
                <?php foreach ($pots as $pot): ?>
                    <li><?= View::e($pot['name']) ?> — <?= View::money((float) $pot['resolved_amount']) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="mt-0">Resterend saldo</h2>
        <?php if ($hasLinkedPot): ?>
            <p class="text-muted">Het saldo van deze periode (<?= View::money($endingBalance) ?>) wordt al automatisch verwerkt via een gekoppeld potje — geen actie nodig.</p>
        <?php elseif ($showBalanceQuestion): ?>
            <p class="text-muted">Er staat nog <?= View::money($endingBalance) ?> los saldo, zonder gekoppeld potje.</p>
            <div class="checkbox-field">
                <input type="checkbox" id="carry_balance" name="carry_balance" value="1">
                <label for="carry_balance">Neem dit saldo mee als inkomst ("Meegenomen saldo <?= View::e($period['name']) ?>") in de doelperiode</label>
            </div>
        <?php else: ?>
            <p class="text-muted">Geen resterend saldo om mee te nemen.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="mt-0">Naar welke periode?</h2>
        <?php if (empty($otherPeriods)): ?>
            <p class="text-muted">Er is nog geen andere periode om naar over te zetten. <a href="<?= View::e(View::url('periods')) ?>">Maak eerst een nieuwe periode aan</a>.</p>
        <?php else: ?>
            <div class="field">
                <label for="target_period_id">Doelperiode</label>
                <select id="target_period_id" name="target_period_id" required>
                    <?php foreach ($otherPeriods as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $defaultTargetId ? 'selected' : '' ?>><?= View::e($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn danger">Periode afsluiten</button>
        <?php endif; ?>
    </div>
</form>

<script>
document.getElementById('close-period-form')?.addEventListener('submit', function (e) {
    var anyChecked = this.querySelectorAll('input[name="carry_fixed_costs[]"]:checked').length > 0;
    var hasOutstanding = this.querySelectorAll('input[name="carry_fixed_costs[]"]').length > 0;
    if (hasOutstanding && !anyChecked) {
        var ok = confirm('Je neemt geen enkele openstaande last mee — die kan hierdoor vergeten worden. Toch doorgaan?');
        if (!ok) {
            e.preventDefault();
        }
    }
});
</script>
