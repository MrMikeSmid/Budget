<?php
/** @var array[] $steps */
/** @var array $month */
/** @var string $monthUrlBase */
$weekdays = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
$monthStart = new DateTimeImmutable($month['monthStart']);
$today = date('Y-m-d');

$days = [];
$todayIndex = null;
for ($i = 0; $i < $month['daysInMonth']; $i++) {
    $day = $monthStart->modify("+{$i} days");
    $days[$i] = $day;
    if ($day->format('Y-m-d') === $today) {
        $todayIndex = $i;
    }
}

// Group each step's occurring days into contiguous runs, so a multi-day span
// becomes one bar and scattered recurring days become separate short bars.
// Steps with no occurrence at all this month are skipped (keeps the grid focused).
$stepRuns = [];
foreach ($steps as $stepIndex => $step) {
    $runs = [];
    $runStart = null;
    for ($i = 0; $i < $month['daysInMonth']; $i++) {
        $occurs = playbook_step_occurs_on($step, $days[$i]->format('Y-m-d'));
        if ($occurs && $runStart === null) {
            $runStart = $i;
        }
        if (!$occurs && $runStart !== null) {
            $runs[] = [$runStart, $i - $runStart];
            $runStart = null;
        }
    }
    if ($runStart !== null) {
        $runs[] = [$runStart, $month['daysInMonth'] - $runStart];
    }
    if (!empty($runs)) {
        $stepRuns[$stepIndex] = $runs;
    }
}
$visibleStepIndexes = array_keys($stepRuns);
$rowCount = max(count($visibleStepIndexes), 1);
?>
<div class="calendar-nav">
    <a class="button button--soft button--small" href="<?= e($monthUrlBase . '?maand=' . $month['prevMonth']) ?>">‹ Vorige</a>
    <strong class="calendar-month-label"><?= e(ucfirst($month['label'])) ?></strong>
    <?php if ($month['canGoNext']): ?>
        <a class="button button--soft button--small" href="<?= e($monthUrlBase . '?maand=' . $month['nextMonth']) ?>">Volgende ›</a>
    <?php else: ?>
        <span class="button button--soft button--small" style="opacity:.4;pointer-events:none">Volgende ›</span>
    <?php endif; ?>
</div>

<div class="calendar-legend">
    <span><i class="calendar-dot calendar-dot--eenmalig"></i> Eenmalig</span>
    <span><i class="calendar-dot calendar-dot--periodiek"></i> Periodiek</span>
</div>

<div class="gantt-wrap" id="ganttWrap">
    <div class="gantt" style="grid-template-columns:repeat(<?= $month['daysInMonth'] ?>,46px);grid-template-rows:38px repeat(<?= $rowCount ?>,38px)">
        <?php if ($todayIndex !== null): ?>
            <div class="gantt-today-line" style="grid-column:<?= $todayIndex + 1 ?>;grid-row:1 / span <?= $rowCount + 1 ?>"></div>
        <?php endif; ?>
        <?php foreach ($days as $i => $day): ?>
            <div class="gantt-day-header<?= $i === $todayIndex ? ' today' : '' ?>" style="grid-column:<?= $i + 1 ?>;grid-row:1">
                <span class="cal-weekday"><?= e($weekdays[(int) $day->format('N') - 1]) ?></span>
                <span class="cal-daynum"><?= (int) $day->format('j') ?></span>
            </div>
        <?php endforeach; ?>
        <?php foreach (array_values($visibleStepIndexes) as $row => $stepIndex): $step = $steps[$stepIndex]; ?>
            <?php foreach ($stepRuns[$stepIndex] as [$startCol, $length]): ?>
                <div class="gantt-bar gantt-bar--<?= e($step['type']) ?>" style="grid-column:<?= $startCol + 1 ?> / span <?= $length ?>;grid-row:<?= $row + 2 ?>" title="<?= e($step['title']) ?> · <?= $step['park_name'] ? e($step['park_name']) : 'Alle parken' ?>"><?= e($step['title']) ?></div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php if (empty($visibleStepIndexes)): ?>
    <div class="empty">Geen taken in <?= e($month['label']) ?>.</div>
<?php endif; ?>
<?php if ($todayIndex !== null): ?>
<script>
(() => {
    const wrap = document.getElementById('ganttWrap');
    const todayLine = wrap ? wrap.querySelector('.gantt-today-line') : null;
    if (wrap && todayLine) { wrap.scrollLeft = Math.max(0, todayLine.offsetLeft - wrap.clientWidth / 2); }
})();
</script>
<?php endif; ?>
