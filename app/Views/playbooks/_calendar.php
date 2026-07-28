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
$presentCategories = array_unique(array_column($steps, 'category'));
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

<?php if (!empty($presentCategories)): ?>
<div class="calendar-legend">
    <?php foreach ($presentCategories as $category): $meta = calendar_category_meta($category); ?>
        <span><i class="calendar-dot <?= e($meta['class']) ?>"></i> <?= e($meta['label']) ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

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
        <?php foreach (array_values($visibleStepIndexes) as $row => $stepIndex): $step = $steps[$stepIndex]; $meta = calendar_category_meta($step['category']); ?>
            <?php foreach ($stepRuns[$stepIndex] as [$startCol, $length]): ?>
                <a class="gantt-bar <?= e($meta['class']) ?>" href="<?= e($step['url']) ?>" style="grid-column:<?= $startCol + 1 ?> / span <?= $length ?>;grid-row:<?= $row + 2 ?>" title="<?= e($step['title']) ?> · <?= e($step['subtitle']) ?>"><?= e($step['title']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php if (empty($visibleStepIndexes)): ?>
    <div class="empty">Geen taken in <?= e($month['label']) ?>.</div>
<?php endif; ?>
<script>
(() => {
    const wrap = document.getElementById('ganttWrap');
    if (!wrap) return;

    const todayLine = wrap.querySelector('.gantt-today-line');
    if (todayLine) { wrap.scrollLeft = Math.max(0, todayLine.offsetLeft - wrap.clientWidth / 2); }

    // Desktop mice send vertical wheel deltas; translate those into horizontal
    // scroll so the calendar scrolls without needing Shift held down. Trackpads
    // already send a real deltaX, so leave those untouched.
    wrap.addEventListener('wheel', (e) => {
        if (Math.abs(e.deltaY) > Math.abs(e.deltaX)) {
            wrap.scrollLeft += e.deltaY;
            e.preventDefault();
        }
    }, { passive: false });

    // Click-and-drag scrolling for desktop mouse users. Bars are now links (to
    // jump to the underlying record), so a drag that moved the pointer must
    // suppress the click that would otherwise follow on mouseup.
    let dragging = false;
    let dragMoved = false;
    let dragStartX = 0;
    let dragStartScroll = 0;
    wrap.addEventListener('mousedown', (e) => {
        dragging = true;
        dragMoved = false;
        wrap.classList.add('is-dragging');
        dragStartX = e.pageX;
        dragStartScroll = wrap.scrollLeft;
    });
    window.addEventListener('mousemove', (e) => {
        if (!dragging) return;
        const delta = e.pageX - dragStartX;
        if (Math.abs(delta) > 4) { dragMoved = true; }
        wrap.scrollLeft = dragStartScroll - delta;
    });
    window.addEventListener('mouseup', () => {
        dragging = false;
        wrap.classList.remove('is-dragging');
    });
    wrap.addEventListener('click', (e) => {
        if (dragMoved) {
            e.preventDefault();
            dragMoved = false;
        }
    }, true);
})();
</script>
