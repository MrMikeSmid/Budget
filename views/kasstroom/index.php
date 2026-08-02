<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $periods */
/** @var array|null $period */
/** @var array $transactions */
/** @var float|null $expectedBalance */
/** @var array $pots */
/** @var array|null $editing */
?>
<?php View::render('partials/period-switcher', ['periods' => $periods, 'period' => $period, 'page' => 'kasstroom'], null); ?>

<?php if ($period): ?>
    <div class="card">
        <h2 class="mt-0"><?= $editing ? 'Mutatie bewerken' : 'Mutatie toevoegen' ?></h2>
        <form class="inline-form" method="post" action="<?= View::e(View::url('kasstroom-save')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
            <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
            <div class="field-row">
                <div class="field">
                    <label for="txn_date">Datum</label>
                    <input type="date" id="txn_date" name="txn_date" required value="<?= View::e($editing['txn_date'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="field">
                    <label for="amount">Mutatie (+ of -)</label>
                    <input type="number" step="0.01" id="amount" name="amount" required value="<?= View::e((string) ($editing['amount'] ?? '')) ?>">
                </div>
            </div>
            <div class="field">
                <label for="description">Omschrijving</label>
                <input type="text" id="description" name="description" required value="<?= View::e($editing['description'] ?? '') ?>">
            </div>
            <div class="field">
                <label for="pot_id">Potje (optioneel — uitgave/inkomst die van dit potje af/bij gaat, niet van het losse saldo)</label>
                <select id="pot_id" name="pot_id">
                    <option value="">Geen koppeling</option>
                    <?php foreach ($pots as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= !empty($editing['pot_id']) && (int) $editing['pot_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= View::e($p['icon'] ?: '💶') ?> <?= View::e($p['name']) ?> (<?= View::money((float) $p['resolved_amount']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="checkbox-field">
                <input type="checkbox" id="is_settled" name="is_settled" <?= !empty($editing['is_settled']) ? 'checked' : '' ?>>
                <label for="is_settled">Al daadwerkelijk afgeschreven/bijgeschreven</label>
            </div>
            <button type="submit" class="btn"><?= $editing ? 'Opslaan' : 'Toevoegen' ?></button>
            <?php if ($editing): ?>
                <a class="btn secondary" href="<?= View::e(View::url('kasstroom', ['period' => $period['id']])) ?>">Annuleren</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h2 class="mt-0">Overboeken</h2>
        <p class="text-muted">Geld verplaatsen tussen het losse saldo en een potje, of tussen twee potjes. Dit is geen uitgave: er verdwijnt niets uit het systeem, het geld verhuist alleen.</p>
        <form class="inline-form" method="post" action="<?= View::e(View::url('potje-overboeking-save')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
            <div class="field-row">
                <div class="field">
                    <label for="from_pot_id">Van</label>
                    <select id="from_pot_id" name="from_pot_id">
                        <option value="">Los saldo</option>
                        <?php foreach ($pots as $p): ?>
                            <option value="<?= (int) $p['id'] ?>">
                                <?= View::e($p['icon'] ?: '💶') ?> <?= View::e($p['name']) ?> (<?= View::money((float) $p['resolved_amount']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="to_pot_id">Naar</label>
                    <select id="to_pot_id" name="to_pot_id">
                        <option value="">Los saldo</option>
                        <?php foreach ($pots as $p): ?>
                            <option value="<?= (int) $p['id'] ?>">
                                <?= View::e($p['icon'] ?: '💶') ?> <?= View::e($p['name']) ?> (<?= View::money((float) $p['resolved_amount']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="transfer_date">Datum</label>
                    <input type="date" id="transfer_date" name="txn_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="field">
                    <label for="transfer_amount">Bedrag</label>
                    <input type="number" step="0.01" min="0.01" id="transfer_amount" name="amount" required>
                </div>
            </div>
            <div class="field">
                <label for="transfer_description">Omschrijving (optioneel)</label>
                <input type="text" id="transfer_description" name="description">
            </div>
            <button type="submit" class="btn">Overboeken</button>
        </form>
    </div>

    <div class="card">
        <div class="section-header">
            <h2 class="mt-0">Saldo</h2>
            <div class="value <?= $expectedBalance !== null && $expectedBalance < 0 ? 'negative' : 'positive' ?>"><?= View::money($expectedBalance) ?></div>
        </div>
        <?php if (empty($transactions)): ?>
            <p class="text-muted">Nog geen mutaties voor deze periode.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Omschrijving</th>
                        <th class="num">Mutatie</th>
                        <th class="num">Saldo</th>
                        <th></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <tr style="<?= $t['is_settled'] ? 'opacity:.65;' : '' ?>">
                            <td><?= View::e($t['txn_date']) ?></td>
                            <td>
                                <?= View::e($t['description']) ?>
                                <?php if ($t['is_settled']): ?> <span class="badge paid">verwerkt</span><?php endif; ?>
                                <?php if ($t['pot_name']): ?> <span class="badge neutral"><?= View::e($t['pot_icon'] ?: '💶') ?> <?= View::e($t['pot_name']) ?></span><?php endif; ?>
                            </td>
                            <td class="num <?= $t['amount'] < 0 ? 'negative' : 'positive' ?>"><?= View::money((float) $t['amount']) ?></td>
                            <td class="num"><?= View::money((float) $t['balance']) ?></td>
                            <td>
                                <a class="btn small secondary" href="<?= View::e(View::url('kasstroom', ['period' => $period['id'], 'edit' => $t['id']])) ?>">Bewerken</a>
                            </td>
                            <td>
                                <form method="post" action="<?= View::e(View::url('kasstroom-delete')) ?>" onsubmit="return confirm('Mutatie verwijderen?');">
                                    <?= Csrf::field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                    <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
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
<?php endif; ?>
