<?php

use App\Models\LineItem;
use App\Support\Csrf;
use App\Support\View;

/** @var array $periods */
/** @var array|null $period */
/** @var array $items */
/** @var array $totals */
/** @var float $outstanding */
/** @var array|null $editing */
/** @var string $listPage */
/** @var string $savePage */
/** @var string $deletePage */
/** @var string[] $statusSuggestions */
/** @var string $outstandingLabel */
/** @var bool $showRecurrenceOptions */
/** @var bool $openForm */
/** @var string $defaultStatus */
/** @var bool $showHero */
/** @var string $heroLabel */
/** @var float $heroValue */
/** @var string $quickActionIcon */
/** @var string $quickActionLabel */

$showRecurrenceOptions = $showRecurrenceOptions ?? false;
$openForm = $openForm ?? false;
$defaultStatus = $defaultStatus ?? '';
$showHero = $showHero ?? false;
$heroLabel = $heroLabel ?? '';
$heroValue = $heroValue ?? $outstanding;
$quickActionIcon = $quickActionIcon ?? 'dashboard';
$quickActionLabel = $quickActionLabel ?? 'Regel toevoegen';
?>
<?php if ($period): ?>
    <?php if ($showHero): ?>
        <div class="hero-balance">
            <div class="hero-balance-label"><?= View::e($heroLabel) ?></div>
            <div class="hero-balance-amount"><?= View::money($heroValue) ?></div>
        </div>

        <div class="quick-actions">
            <a class="quick-action" href="<?= View::e(View::url($listPage, ['period' => $period['id'], 'open' => 1])) ?>">
                <span class="quick-action-icon"><?= View::navIcon($quickActionIcon) ?></span>
                <?= View::e($quickActionLabel) ?>
            </a>
        </div>
    <?php else: ?>
        <button type="button" class="fab-button" data-toggle-target="add-form-panel" aria-label="Regel toevoegen">+</button>
    <?php endif; ?>

    <div class="form-panel" id="add-form-panel" <?= ($editing || $openForm) ? '' : 'hidden' ?>>
        <div class="card">
            <h2 class="mt-0"><?= $editing ? 'Regel bewerken' : 'Regel toevoegen' ?></h2>
            <form class="inline-form" method="post" action="<?= View::e(View::url($savePage)) ?>">
                <?= Csrf::field() ?>
                <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">
                <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
                <div class="field">
                    <label for="description">Omschrijving</label>
                    <input type="text" id="description" name="description" required value="<?= View::e($editing['description'] ?? '') ?>">
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="budgeted">Begroot</label>
                        <input type="number" step="0.01" id="budgeted" name="budgeted" value="<?= View::e((string) ($editing['budgeted'] ?? '0')) ?>">
                    </div>
                    <div class="field">
                        <label for="actual">Werkelijk</label>
                        <input type="number" step="0.01" id="actual" name="actual" value="<?= View::e($editing['actual'] ?? null) ?>">
                    </div>
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <input type="text" id="status" name="status" list="status-suggestions" value="<?= View::e($editing['status'] ?? $defaultStatus) ?>">
                    <datalist id="status-suggestions">
                        <?php foreach ($statusSuggestions as $s): ?>
                            <option value="<?= View::e($s) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="checkbox-field">
                    <input type="checkbox" id="is_recurring" name="is_recurring"
                        <?= !empty($editing['is_recurring']) ? 'checked' : '' ?>
                        <?php if ($showRecurrenceOptions): ?>onchange="document.getElementById('recurrence-options').style.display = this.checked ? 'block' : 'none';"<?php endif; ?>>
                    <label for="is_recurring">Terugkerend — automatisch overnemen bij een nieuwe periode</label>
                </div>
                <?php if ($showRecurrenceOptions): ?>
                    <div id="recurrence-options" style="display: <?= !empty($editing['is_recurring']) ? 'block' : 'none' ?>;">
                        <div class="field-row">
                            <div class="field">
                                <label for="recurrence_interval">Frequentie</label>
                                <select id="recurrence_interval" name="recurrence_interval" onchange="document.getElementById('recurrence-mode-wrap').style.display = this.value === 'maandelijks' ? 'none' : 'block';">
                                    <?php foreach (LineItem::INTERVALS as $key => $label): ?>
                                        <option value="<?= View::e($key) ?>" <?= ($editing['recurrence_interval'] ?? 'maandelijks') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="field" id="recurrence-mode-wrap" style="<?= ($editing['recurrence_interval'] ?? 'maandelijks') === 'maandelijks' ? 'display:none;' : '' ?>">
                                <label for="recurrence_mode">Komt terug</label>
                                <select id="recurrence_mode" name="recurrence_mode" onchange="document.getElementById('recurrence-date-field').style.display = this.value === 'datum' ? 'block' : 'none';">
                                    <?php foreach (LineItem::MODES as $key => $label): ?>
                                        <option value="<?= View::e($key) ?>" <?= ($editing['recurrence_mode'] ?? 'periode') === $key ? 'selected' : '' ?>><?= View::e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="field" id="recurrence-date-field" style="<?= ($editing['recurrence_mode'] ?? 'periode') === 'datum' ? '' : 'display:none;' ?>">
                            <label for="recurrence_date">Vaste datum</label>
                            <input type="date" id="recurrence_date" name="recurrence_date" value="<?= View::e($editing['recurrence_date'] ?? '') ?>">
                            <p class="text-muted" style="font-size:12px; margin:4px 0 0;">Komt terug op deze datum, en daarna telkens weer na de gekozen frequentie (bijv. jaarlijks op dezelfde dag).</p>
                        </div>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn"><?= $editing ? 'Opslaan' : 'Toevoegen' ?></button>
                <?php if ($editing): ?>
                    <a class="btn secondary" href="<?= View::e(View::url($listPage, ['period' => $period['id']])) ?>">Annuleren</a>
                <?php endif; ?>
            </form>
            <?php if ($editing): ?>
                <form method="post" action="<?= View::e(View::url($deletePage)) ?>" onsubmit="return confirm('Regel verwijderen?');" style="margin-top:10px;">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                    <input type="hidden" name="period_id" value="<?= (int) $period['id'] ?>">
                    <button type="submit" class="btn small danger">Verwijderen</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-stats" style="grid-template-columns: 1fr 1fr;">
        <div class="stat">
            <div class="label">Totaal begroot</div>
            <div class="value"><?= View::money((float) $totals['budgeted']) ?></div>
        </div>
        <div class="stat">
            <div class="label"><?= View::e($outstandingLabel) ?></div>
            <div class="value negative"><?= View::money($outstanding) ?></div>
        </div>
    </div>

    <div class="card">
        <?php if (empty($items)): ?>
            <p class="text-muted">Nog geen regels voor deze periode.</p>
        <?php else: ?>
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th class="nowrap">Omschrijving</th>
                        <th class="num">Begroot</th>
                        <th class="num">Werkelijk</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                            $rowHref = !empty($item['linked_transaction_id'])
                                ? View::url('kasstroom', ['period' => $period['id'], 'edit' => $item['linked_transaction_id']])
                                : View::url($listPage, ['period' => $period['id'], 'edit' => $item['id']]);
                        ?>
                        <tr class="row-clickable" data-href="<?= View::e($rowHref) ?>">
                            <td class="nowrap">
                                <?= View::e($item['description']) ?>
                                <?php if (!empty($item['is_recurring'])): ?> <span title="Terugkerend (<?= View::e(LineItem::INTERVALS[$item['recurrence_interval'] ?? 'maandelijks'] ?? 'Maandelijks') ?>)" class="text-muted">&#8635;</span><?php endif; ?>
                                <?php if (!empty($item['loan_id'])): ?> <span class="badge neutral" title="Gekoppeld aan een lening">Lening</span><?php endif; ?>
                                <?php if (!empty($item['linked_transaction_id'])): ?> <span class="badge neutral" title="Gekoppeld aan een kasstroommutatie">Kasstroom</span><?php endif; ?>
                                <?php if ($item['status']): ?>
                                    <div class="line-item-status">
                                        <span class="badge <?= View::badgeClass($item['status']) ?>"><?= View::e($item['status']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= View::money((float) $item['budgeted']) ?></td>
                            <td class="num"><?= $item['actual'] !== null ? View::money((float) $item['actual']) : '-' ?></td>
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
