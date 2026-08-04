<?php

use App\Models\LineItem;
use App\Support\Csrf;
use App\Support\View;

/** @var array $periods */
/** @var array|null $period */
/** @var array $transactions */
/** @var array $filters */
/** @var float|null $expectedBalance */
/** @var array $pots */
/** @var array $fixedCosts */
/** @var array $incomeItems */
/** @var array|null $editing */
/** @var array|null $editingOverboeking */
/** @var bool $openForm */
/** @var string $activeTab */
?>
<?php if ($period): ?>
    <div class="hero-balance">
        <div class="hero-balance-label">Saldo</div>
        <div class="hero-balance-amount"><?= View::money($expectedBalance) ?></div>
    </div>

    <div class="quick-actions">
        <a class="quick-action" href="<?= View::e(View::url('kasstroom', ['period' => $period['id'], 'open' => 1, 'tab' => 'uitgave'])) ?>">
            <span class="quick-action-icon"><?= View::navIcon('uitgave') ?></span>
            Uitgave
        </a>
        <a class="quick-action" href="<?= View::e(View::url('kasstroom', ['period' => $period['id'], 'open' => 1, 'tab' => 'overboeken'])) ?>">
            <span class="quick-action-icon"><?= View::navIcon('overboeking') ?></span>
            Overboeking
        </a>
    </div>
<?php endif; ?>

<?php if ($period): ?>
    <div class="form-panel" id="add-form-panel" <?= ($editing || $openForm) ? '' : 'hidden' ?>>
    <div class="card">
        <div class="tab-switch" role="tablist">
            <button type="button" class="tab-btn <?= $activeTab === 'uitgave' ? 'active' : '' ?>" data-tab-target="panel-uitgave">💸 Uitgave</button>
            <button type="button" class="tab-btn <?= $activeTab === 'overboeken' ? 'active' : '' ?>" data-tab-target="panel-overboeken">🔁 Overboeken</button>
        </div>

        <?php
            $currentSource = '';
            if (!empty($editing['pot_id'])) {
                $currentSource = 'pot:' . (int) $editing['pot_id'];
            } elseif (!empty($editing['fixed_cost_id'])) {
                $currentSource = 'fixed_cost:' . (int) $editing['fixed_cost_id'];
            } elseif (!empty($editing['income_item_id'])) {
                $currentSource = 'income:' . (int) $editing['income_item_id'];
            }
            $linkedItem = null;
            if (!empty($editing['fixed_cost_id'])) {
                foreach ($fixedCosts as $fc) {
                    if ((int) $fc['id'] === (int) $editing['fixed_cost_id']) {
                        $linkedItem = $fc;
                        break;
                    }
                }
            } elseif (!empty($editing['income_item_id'])) {
                foreach ($incomeItems as $ii) {
                    if ((int) $ii['id'] === (int) $editing['income_item_id']) {
                        $linkedItem = $ii;
                        break;
                    }
                }
            }
        ?>
        <div class="tab-panel" id="panel-uitgave" <?= $activeTab === 'uitgave' ? '' : 'hidden' ?>>
            <p class="text-muted">Alleen voor uitgaven: het bedrag gaat af van het losse saldo, of — als je een potje, vaste last of inkomst als bron kiest — daarvandaan. Nieuw, nog niet begroot geld voeg je toe bij Inkomen.</p>
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
                    <input type="text" id="description" name="description" required data-sync-field="description" value="<?= View::e($editing['description'] ?? '') ?>">
                </div>
                <div class="field">
                    <label for="source">Bron</label>
                    <select id="source" name="source" data-sync-target="linked-item-fields">
                        <option value="">Los saldo</option>
                        <?php if (!empty($pots)): ?>
                            <optgroup label="Potjes">
                                <?php foreach ($pots as $p): ?>
                                    <option value="pot:<?= (int) $p['id'] ?>" <?= $currentSource === 'pot:' . $p['id'] ? 'selected' : '' ?>>
                                        <?= View::e($p['icon'] ?: '💶') ?> <?= View::e($p['name']) ?> (<?= View::money((float) $p['resolved_amount']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($fixedCosts)): ?>
                            <optgroup label="Vaste lasten">
                                <?php foreach ($fixedCosts as $fc): ?>
                                    <option value="fixed_cost:<?= (int) $fc['id'] ?>" <?= $currentSource === 'fixed_cost:' . $fc['id'] ? 'selected' : '' ?>
                                        data-linked="1"
                                        data-description="<?= View::e($fc['description']) ?>"
                                        data-budgeted="<?= View::e((string) $fc['budgeted']) ?>"
                                        data-recurring="<?= !empty($fc['is_recurring']) ? '1' : '0' ?>"
                                        data-interval="<?= View::e($fc['recurrence_interval'] ?? 'maandelijks') ?>"
                                        data-mode="<?= View::e($fc['recurrence_mode'] ?? 'periode') ?>"
                                        data-date="<?= View::e($fc['recurrence_date'] ?? '') ?>">
                                        <?= View::e($fc['description']) ?> (begroot <?= View::money((float) $fc['budgeted']) ?>)<?= !empty($fc['linked_transaction_id']) && (int) $fc['linked_transaction_id'] !== (int) ($editing['id'] ?? 0) ? ' — al gekoppeld' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <?php if (!empty($incomeItems)): ?>
                            <optgroup label="Inkomsten">
                                <?php foreach ($incomeItems as $ii): ?>
                                    <option value="income:<?= (int) $ii['id'] ?>" <?= $currentSource === 'income:' . $ii['id'] ? 'selected' : '' ?>
                                        data-linked="1"
                                        data-description="<?= View::e($ii['description']) ?>"
                                        data-budgeted="<?= View::e((string) $ii['budgeted']) ?>"
                                        data-recurring="<?= !empty($ii['is_recurring']) ? '1' : '0' ?>"
                                        data-interval="<?= View::e($ii['recurrence_interval'] ?? 'maandelijks') ?>"
                                        data-mode="<?= View::e($ii['recurrence_mode'] ?? 'periode') ?>"
                                        data-date="<?= View::e($ii['recurrence_date'] ?? '') ?>">
                                        <?= View::e($ii['description']) ?> (begroot <?= View::money((float) $ii['budgeted']) ?>)<?= !empty($ii['linked_transaction_id']) && (int) $ii['linked_transaction_id'] !== (int) ($editing['id'] ?? 0) ? ' — al gekoppeld' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="checkbox-field">
                    <input type="checkbox" id="is_settled" name="is_settled" <?= !empty($editing['is_settled']) ? 'checked' : '' ?>>
                    <label for="is_settled">Al daadwerkelijk afgeschreven/bijgeschreven</label>
                </div>
                <div id="linked-item-fields" <?= $linkedItem ? '' : 'hidden' ?>>
                    <p class="text-muted">Deze mutatie is gekoppeld: begroot/terugkerend van de vaste last of inkomst bewerk je hier, niet meer op de eigen pagina. Status wordt automatisch "Betaald" (last) of "Ontvangen" (inkomst) zodra je opslaat.</p>
                    <div class="field">
                        <label for="li_budgeted">Begroot</label>
                        <input type="number" step="0.01" id="li_budgeted" name="budgeted" data-sync-field="budgeted" value="<?= View::e((string) ($linkedItem['budgeted'] ?? '0')) ?>">
                    </div>
                    <div class="checkbox-field">
                        <input type="checkbox" id="li_is_recurring" name="is_recurring" data-sync-field="recurring"
                            <?= !empty($linkedItem['is_recurring']) ? 'checked' : '' ?>
                            onchange="document.getElementById('li-recurrence-options').style.display = this.checked ? 'block' : 'none';">
                        <label for="li_is_recurring">Terugkerend — automatisch overnemen bij een nieuwe periode</label>
                    </div>
                    <div id="li-recurrence-options" style="display: <?= !empty($linkedItem['is_recurring']) ? 'block' : 'none' ?>;">
                        <div class="field-row">
                            <div class="field">
                                <label for="li_recurrence_interval">Frequentie</label>
                                <select id="li_recurrence_interval" name="recurrence_interval" data-sync-field="interval" onchange="document.getElementById('li-recurrence-mode-wrap').style.display = this.value === 'maandelijks' ? 'none' : 'block';">
                                    <?php foreach (LineItem::INTERVALS as $key => $label): ?>
                                        <option value="<?= View::e($key) ?>" <?= ($linkedItem['recurrence_interval'] ?? 'maandelijks') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field" id="li-recurrence-mode-wrap" style="<?= ($linkedItem['recurrence_interval'] ?? 'maandelijks') === 'maandelijks' ? 'display:none;' : '' ?>">
                                <label for="li_recurrence_mode">Komt terug</label>
                                <select id="li_recurrence_mode" name="recurrence_mode" data-sync-field="mode" onchange="document.getElementById('li-recurrence-date-field').style.display = this.value === 'datum' ? 'block' : 'none';">
                                    <?php foreach (LineItem::MODES as $key => $label): ?>
                                        <option value="<?= View::e($key) ?>" <?= ($linkedItem['recurrence_mode'] ?? 'periode') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field" id="li-recurrence-date-field" style="<?= ($linkedItem['recurrence_mode'] ?? 'periode') === 'datum' ? '' : 'display:none;' ?>">
                            <label for="li_recurrence_date">Vaste datum</label>
                            <input type="date" id="li_recurrence_date" name="recurrence_date" data-sync-field="date" value="<?= View::e($linkedItem['recurrence_date'] ?? '') ?>">
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn"><?= $editing ? 'Opslaan' : 'Toevoegen' ?></button>
                <?php if ($editing): ?>
                    <a class="btn secondary" href="<?= View::e(View::url('kasstroom', ['period' => $period['id']])) ?>">Annuleren</a>
                <?php endif; ?>
            </form>
            <?php if ($editing): ?>
                <form method="post" action="<?= View::e(View::url('kasstroom-delete')) ?>" onsubmit="return confirm('Mutatie verwijderen?');" style="margin-top:10px;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                    <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
                    <button type="submit" class="btn small danger">Verwijderen</button>
                </form>
            <?php endif; ?>
        </div>

        <?php
            $overboekingFromPotId = null;
            $overboekingToPotId = null;
            if ($editingOverboeking) {
                if ((float) $editingOverboeking['amount'] < 0) {
                    $overboekingFromPotId = (int) $editingOverboeking['pot_id'];
                } else {
                    $overboekingToPotId = (int) $editingOverboeking['pot_id'];
                }
            }
        ?>
        <div class="tab-panel" id="panel-overboeken" <?= $activeTab === 'overboeken' ? '' : 'hidden' ?>>
            <p class="text-muted">Geld verplaatsen tussen het losse saldo en een potje, of tussen twee potjes. Dit is geen uitgave: er verdwijnt niets uit het systeem, het geld verhuist alleen.</p>
            <?php if ($editingOverboeking): ?>
                <p class="text-muted">Overboekingen kunnen niet bewerkt worden — verwijder deze en voeg hem eventueel opnieuw toe.</p>
            <?php endif; ?>
            <form class="inline-form" method="post" action="<?= View::e(View::url('potje-overboeking-save')) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
                <div class="field-row">
                    <div class="field">
                        <label for="from_pot_id">Van</label>
                        <select id="from_pot_id" name="from_pot_id">
                            <option value="">Los saldo</option>
                            <?php foreach ($pots as $p): ?>
                                <option value="<?= (int) $p['id'] ?>" <?= $overboekingFromPotId === (int) $p['id'] ? 'selected' : '' ?>>
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
                                <option value="<?= (int) $p['id'] ?>" <?= $overboekingToPotId === (int) $p['id'] ? 'selected' : '' ?>>
                                    <?= View::e($p['icon'] ?: '💶') ?> <?= View::e($p['name']) ?> (<?= View::money((float) $p['resolved_amount']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="transfer_date">Datum</label>
                        <input type="date" id="transfer_date" name="txn_date" required value="<?= View::e($editingOverboeking['txn_date'] ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="field">
                        <label for="transfer_amount">Bedrag</label>
                        <input type="number" step="0.01" min="0.01" id="transfer_amount" name="amount" required value="<?= $editingOverboeking ? View::e((string) abs((float) $editingOverboeking['amount'])) : '' ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="transfer_description">Omschrijving (optioneel)</label>
                    <input type="text" id="transfer_description" name="description" value="<?= View::e($editingOverboeking['description'] ?? '') ?>">
                </div>
                <button type="submit" class="btn">Overboeken</button>
            </form>
            <?php if ($editingOverboeking): ?>
                <form method="post" action="<?= View::e(View::url('potje-transactie-delete')) ?>" onsubmit="return confirm('Overboeking verwijderen?');" style="margin-top:10px;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $editingOverboeking['id'] ?>">
                    <input type="hidden" name="pot_id" value="<?= (int) $editingOverboeking['pot_id'] ?>">
                    <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
                    <input type="hidden" name="return" value="kasstroom">
                    <button type="submit" class="btn small danger">Verwijderen</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    </div>

    <?php
        $filtersActive = $filters['type'] !== 'alle' || $filters['pot_id'] !== '' || $filters['sort'] !== 'datum' || $filters['dir'] !== 'asc';
    ?>
    <div class="card">
        <div class="section-header">
            <h2 class="mt-0">Mutaties</h2>
            <button type="button" class="btn small secondary" data-toggle-target="filter-panel">Filteren</button>
        </div>

        <div id="filter-panel" <?= $filtersActive ? '' : 'hidden' ?>>
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
        </div>

        <?php if (empty($transactions)): ?>
            <p class="text-muted">Geen mutaties voor deze periode (of dit filter).</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th class="nowrap">Datum</th>
                        <th class="nowrap">Omschrijving</th>
                        <th class="num">Mutatie</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($transactions as $t): ?>
                        <?php
                            $isTransfer = $t['source'] === 'overboeking';
                            if ($isTransfer) {
                                $rowHref = View::url('kasstroom', ['period' => $period['id'], 'open' => 1, 'tab' => 'overboeken', 'edit_overboeking' => $t['id']]);
                            } elseif (!empty($t['fixed_cost_id'])) {
                                $rowHref = View::url('vaste-lasten', ['period' => $period['id'], 'edit' => $t['fixed_cost_id']]);
                            } elseif (!empty($t['income_item_id'])) {
                                $rowHref = View::url('inkomsten', ['period' => $period['id'], 'edit' => $t['income_item_id']]);
                            } else {
                                $rowHref = View::url('kasstroom', ['period' => $period['id'], 'edit' => $t['id']]);
                            }
                        ?>
                        <tr class="row-clickable" data-href="<?= View::e($rowHref) ?>" style="<?= !empty($t['is_settled']) ? 'opacity:.65;' : '' ?>">
                            <td class="nowrap"><?= View::e($t['txn_date']) ?></td>
                            <td class="nowrap">
                                <?= View::e($t['description']) ?>
                                <?php if ($isTransfer): ?> <span class="badge neutral">🔁 overboeking</span><?php endif; ?>
                                <?php if (!empty($t['is_settled'])): ?> <span class="badge paid">verwerkt</span><?php endif; ?>
                                <?php if ($t['pot_name']): ?> <span class="badge neutral"><?= View::e($t['pot_icon'] ?: '💶') ?> <?= View::e($t['pot_name']) ?></span><?php endif; ?>
                                <?php if (!empty($t['fixed_cost_id'])): ?> <span class="badge neutral" title="Gekoppeld aan een vaste last">Last</span><?php endif; ?>
                                <?php if (!empty($t['income_item_id'])): ?> <span class="badge neutral" title="Gekoppeld aan een inkomst">Inkomst</span><?php endif; ?>
                            </td>
                            <td class="num <?= $t['amount'] < 0 ? 'negative' : 'positive' ?>"><?= $t['amount'] > 0 ? '+ ' : '' ?><?= View::money((float) $t['amount']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php endif; ?>
