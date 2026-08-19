<?php

use App\Models\IconMapping;
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
/** @var array $categories */
/** @var bool $groupByCategory */
/** @var bool $iconCards */
/** @var array $iconMap */
/** @var bool $showIcons */
/** @var string|null $attentionMessage */
/** @var bool $showActual */

$showRecurrenceOptions = $showRecurrenceOptions ?? false;
$openForm = $openForm ?? false;
$defaultStatus = $defaultStatus ?? '';
$showHero = $showHero ?? false;
$heroLabel = $heroLabel ?? '';
$heroValue = $heroValue ?? $outstanding;
$quickActionIcon = $quickActionIcon ?? 'dashboard';
$quickActionLabel = $quickActionLabel ?? 'Regel toevoegen';
$groupByCategory = $groupByCategory ?? false;
$iconCards = $iconCards ?? false;
$iconMap = $iconMap ?? [];
$showIcons = $showIcons ?? true;
$attentionMessage = $attentionMessage ?? null;
$showActual = $showActual ?? false;

// Bij groeperen: één sectie per categorie, aflopend gesorteerd op aantal
// items — de categorie met de meeste items dus bovenaan. Regels zonder
// categorie komen altijd als laatste sectie, ongeacht hun aantal.
$sections = [];
if ($groupByCategory && !empty($items)) {
    $buckets = [];
    foreach ($items as $item) {
        $key = (int) ($item['category_id'] ?? 0);
        if (!isset($buckets[$key])) {
            $buckets[$key] = ['title' => $key > 0 ? $item['category_name'] : 'Geen categorie', 'items' => []];
        }
        $buckets[$key]['items'][] = $item;
    }
    $uncategorized = $buckets[0] ?? null;
    unset($buckets[0]);
    uasort($buckets, static fn (array $a, array $b) => count($b['items']) <=> count($a['items']));
    if ($uncategorized) {
        $buckets[0] = $uncategorized;
    }
    $sections = array_values($buckets);
} else {
    $sections[] = ['title' => null, 'items' => $items];
}
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
                <div class="field">
                    <label for="budgeted">Begroot</label>
                    <input type="number" step="0.01" id="budgeted" name="budgeted" value="<?= View::e((string) ($editing['budgeted'] ?? '0')) ?>">
                </div>
                <div class="field">
                    <label for="category_id">Categorie</label>
                    <select id="category_id" name="category_id">
                        <option value="">Geen categorie</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) ($editing['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php
                    $currentStatus = $editing['status'] ?? $defaultStatus;
                    $isCustomStatus = !in_array($currentStatus, $statusSuggestions, true);
                ?>
                <div class="field">
                    <label for="status_select">Status</label>
                    <select id="status_select" onchange="
                        var custom = document.getElementById('status');
                        if (this.value === '__custom__') { custom.style.display = ''; custom.focus(); }
                        else { custom.style.display = 'none'; custom.value = this.value; }
                    ">
                        <?php foreach ($statusSuggestions as $s): ?>
                            <option value="<?= View::e($s) ?>" <?= !$isCustomStatus && $currentStatus === $s ? 'selected' : '' ?>><?= View::e($s) ?></option>
                        <?php endforeach; ?>
                        <option value="__custom__" <?= $isCustomStatus ? 'selected' : '' ?>>Zelf typen…</option>
                    </select>
                    <input type="text" id="status" name="status" placeholder="Eigen status"
                        style="margin-top:6px;<?= $isCustomStatus ? '' : ' display:none;' ?>"
                        value="<?= View::e($currentStatus) ?>">
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

    <?php if ($attentionMessage): ?>
        <div class="flash warning"><?= View::e($attentionMessage) ?></div>
    <?php endif; ?>

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

    <?php if (empty($items)): ?>
        <div class="card">
            <p class="text-muted">Nog geen regels voor deze periode.</p>
        </div>
    <?php elseif ($iconCards): ?>
        <?php foreach ($sections as $section): ?>
            <div class="expense-list">
                <?php foreach ($section['items'] as $item): ?>
                    <?php
                        $rowHref = View::url($listPage, ['period' => $period['id'], 'edit' => $item['id']]);
                        $iconMappingId = $showIcons ? IconMapping::match($item['description'], $iconMap) : null;
                    ?>
                    <a class="expense-list-item" href="<?= View::e($rowHref) ?>">
                        <?php if ($showIcons): ?>
                        <span class="expense-list-icon">
                            <?php if ($iconMappingId): ?>
                                <img src="<?= View::e(View::url('icoon-afbeelding', ['id' => $iconMappingId])) ?>" alt="" width="28" height="28">
                            <?php else: ?>
                                <span class="placeholder"><?= View::e(mb_strtoupper(mb_substr($item['description'], 0, 1))) ?></span>
                            <?php endif; ?>
                        </span>
                        <?php endif; ?>
                        <span class="expense-list-body">
                            <span class="expense-list-title"><?= View::e($item['description']) ?></span>
                            <span class="expense-list-amount">
                                <span class="amount-figure" title="Begroot">
                                    <svg class="amount-arrow up" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="13" x2="8" y2="3"/><polyline points="4 7 8 3 12 7"/></svg>
                                    <?= View::money((float) $item['budgeted']) ?>
                                </span>
                                <?php if ($showActual && $item['actual'] !== null): ?>
                                    <span class="amount-figure" title="Werkelijk">
                                        <svg class="amount-arrow down" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="3" x2="8" y2="13"/><polyline points="4 9 8 13 12 9"/></svg>
                                        <?= View::money((float) $item['actual']) ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                        </span>
                        <?php if ($item['status']): ?>
                            <span class="badge <?= View::badgeClass($item['status']) ?>"><?= View::e($item['status']) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <?php foreach ($sections as $section): ?>
            <div class="card">
                <?php if ($section['title'] !== null): ?>
                    <div class="section-header">
                        <h2 class="mt-0"><?= View::e($section['title']) ?></h2>
                        <span class="text-muted"><?= count($section['items']) ?></span>
                    </div>
                <?php endif; ?>
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
                        <?php foreach ($section['items'] as $item): ?>
                            <?php
                                // Begroot/categorie/terugkerend van deze regel bewerk je altijd
                                // hier, ook als er al een kasstroommutatie aan gekoppeld is — die
                                // mutatie zelf (datum/bedrag) bewerk je via de "Kasstroom"-badge.
                                $rowHref = View::url($listPage, ['period' => $period['id'], 'edit' => $item['id']]);
                                $hasBadges = !empty($item['loan_id']) || !empty($item['linked_transaction_id']) || !empty($item['category_name']) || $item['status'];
                            ?>
                            <tr class="row-clickable<?= $hasBadges ? ' item-row-main' : '' ?>" data-href="<?= View::e($rowHref) ?>">
                                <td class="nowrap">
                                    <?= View::e($item['description']) ?>
                                    <?php if (!empty($item['is_recurring'])): ?> <span title="Terugkerend (<?= View::e(LineItem::INTERVALS[$item['recurrence_interval'] ?? 'maandelijks'] ?? 'Maandelijks') ?>)" class="text-muted">&#8635;</span><?php endif; ?>
                                </td>
                                <td class="num"><?= View::money((float) $item['budgeted']) ?></td>
                                <td class="num"><?= $item['actual'] !== null ? View::money((float) $item['actual']) : '-' ?></td>
                            </tr>
                            <?php if ($hasBadges): ?>
                                <tr class="row-clickable item-badges-row" data-href="<?= View::e($rowHref) ?>">
                                    <td colspan="3">
                                        <div class="item-badges">
                                            <?php if (!empty($item['loan_id'])): ?> <span class="badge neutral" title="Gekoppeld aan een lening">Lening</span><?php endif; ?>
                                            <?php if (!empty($item['linked_transaction_id'])): ?>
                                                <a class="badge neutral" title="Bewerk de gekoppelde kasstroommutatie (datum/bedrag)" href="<?= View::e(View::url('kasstroom', ['period' => $period['id'], 'edit' => $item['linked_transaction_id']])) ?>" onclick="event.stopPropagation();">Kasstroom</a>
                                            <?php endif; ?>
                                            <?php if (!empty($item['category_name'])): ?>
                                                <a class="badge category" href="<?= View::e(View::url('categorie', ['id' => $item['category_id'], 'period' => $period['id']])) ?>" onclick="event.stopPropagation();"><?= View::e($item['category_name']) ?></a>
                                            <?php endif; ?>
                                            <?php if ($item['status']): ?>
                                                <span class="badge <?= View::badgeClass($item['status']) ?>"><?= View::e($item['status']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
<?php else: ?>
    <div class="empty-state card">
        <p>Er is nog geen budgetperiode aangemaakt.</p>
        <a class="btn" href="<?= View::e(View::url('periods')) ?>">Periode aanmaken</a>
    </div>
<?php endif; ?>
