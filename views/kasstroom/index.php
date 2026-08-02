<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $periods */
/** @var array|null $period */
/** @var array $transactions */
/** @var array $filters */
/** @var float|null $expectedBalance */
/** @var array $pots */
/** @var array|null $editing */
?>
<?php View::render('partials/period-switcher', ['periods' => $periods, 'period' => $period, 'page' => 'kasstroom'], null); ?>

<?php if ($period): ?>
    <button type="button" class="fab-button" data-toggle-target="add-form-panel" aria-label="Mutatie toevoegen">+</button>

    <div class="form-panel" id="add-form-panel" <?= $editing ? '' : 'hidden' ?>>
    <div class="card">
        <div class="tab-switch" role="tablist">
            <button type="button" class="tab-btn active" data-tab-target="panel-uitgave">💸 Uitgave</button>
            <button type="button" class="tab-btn" data-tab-target="panel-overboeken">🔁 Overboeken</button>
        </div>

        <div class="tab-panel" id="panel-uitgave">
            <p class="text-muted">Alleen voor uitgaven: het bedrag gaat af van het losse saldo, of — als je een potje als bron kiest — van dat potje. Nieuw geld voeg je toe bij Inkomen.</p>
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
                        <label for="amount">Bedrag</label>
                        <input type="number" step="0.01" min="0.01" id="amount" name="amount" required value="<?= View::e($editing ? (string) abs((float) $editing['amount']) : '') ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="description">Omschrijving</label>
                    <input type="text" id="description" name="description" required value="<?= View::e($editing['description'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="pot_id">Bron</label>
                    <select id="pot_id" name="pot_id">
                        <option value="">Los saldo</option>
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

        <div class="tab-panel" id="panel-overboeken" hidden>
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
    </div>
    </div>

    <div class="card">
        <div class="section-header">
            <h2 class="mt-0">Saldo</h2>
            <div class="value <?= $expectedBalance !== null && $expectedBalance < 0 ? 'negative' : 'positive' ?>"><?= View::money($expectedBalance) ?></div>
        </div>

        <form class="inline-form filter-bar" method="get" action="index.php">
            <input type="hidden" name="page" value="kasstroom">
            <input type="hidden" name="period" value="<?= (int) $period['id'] ?>">
            <div class="field-row">
                <div class="field">
                    <label for="filter_type">Type</label>
                    <select id="filter_type" name="type" onchange="this.form.submit()">
                        <option value="alle" <?= $filters['type'] === 'alle' ? 'selected' : '' ?>>Alles</option>
                        <option value="uitgaven" <?= $filters['type'] === 'uitgaven' ? 'selected' : '' ?>>Uitgaven</option>
                        <option value="overboekingen" <?= $filters['type'] === 'overboekingen' ? 'selected' : '' ?>>Overboekingen</option>
                    </select>
                </div>
                <div class="field">
                    <label for="filter_pot">Potje</label>
                    <select id="filter_pot" name="pot_id" onchange="this.form.submit()">
                        <option value="">Alle potjes</option>
                        <?php foreach ($pots as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (string) $filters['pot_id'] === (string) $p['id'] ? 'selected' : '' ?>>
                                <?= View::e($p['icon'] ?: '💶') ?> <?= View::e($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="filter_sort">Sorteren op</label>
                    <select id="filter_sort" name="sort" onchange="this.form.submit()">
                        <option value="datum" <?= $filters['sort'] === 'datum' ? 'selected' : '' ?>>Datum</option>
                        <option value="bedrag" <?= $filters['sort'] === 'bedrag' ? 'selected' : '' ?>>Bedrag</option>
                        <option value="omschrijving" <?= $filters['sort'] === 'omschrijving' ? 'selected' : '' ?>>Omschrijving</option>
                    </select>
                </div>
                <div class="field">
                    <label for="filter_dir">Richting</label>
                    <select id="filter_dir" name="dir" onchange="this.form.submit()">
                        <option value="asc" <?= $filters['dir'] === 'asc' ? 'selected' : '' ?>>Oplopend</option>
                        <option value="desc" <?= $filters['dir'] === 'desc' ? 'selected' : '' ?>>Aflopend</option>
                    </select>
                </div>
            </div>
        </form>

        <?php if (empty($transactions)): ?>
            <p class="text-muted">Geen mutaties voor deze periode (of dit filter).</p>
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
                        <?php $isTransfer = $t['source'] === 'overboeking'; ?>
                        <tr style="<?= !empty($t['is_settled']) ? 'opacity:.65;' : '' ?>">
                            <td><?= View::e($t['txn_date']) ?></td>
                            <td>
                                <?= View::e($t['description']) ?>
                                <?php if ($isTransfer): ?> <span class="badge neutral">🔁 overboeking</span><?php endif; ?>
                                <?php if (!empty($t['is_settled'])): ?> <span class="badge paid">verwerkt</span><?php endif; ?>
                                <?php if ($t['pot_name']): ?> <span class="badge neutral"><?= View::e($t['pot_icon'] ?: '💶') ?> <?= View::e($t['pot_name']) ?></span><?php endif; ?>
                            </td>
                            <td class="num <?= $t['amount'] < 0 ? 'negative' : 'positive' ?>"><?= View::money((float) $t['amount']) ?></td>
                            <td class="num"><?= View::money((float) $t['balance']) ?></td>
                            <?php if ($isTransfer): ?>
                                <td></td>
                                <td>
                                    <form method="post" action="<?= View::e(View::url('potje-transactie-delete')) ?>" onsubmit="return confirm('Mutatie verwijderen?');">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                                        <input type="hidden" name="pot_id" value="<?= (int) $t['pot_id'] ?>">
                                        <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
                                        <input type="hidden" name="return" value="kasstroom">
                                        <button type="submit" class="btn small danger">Verwijderen</button>
                                    </form>
                                </td>
                            <?php else: ?>
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
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
