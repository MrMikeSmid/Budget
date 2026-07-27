<?php
/** @var array[] $steps */
/** @var ?string $calendarStart */
/** @var ?string $calendarEnd */
if ($calendarStart === null): ?>
    <div class="empty">Nog geen stappen om in de kalender te tonen.</div>
<?php else:
    $rangeStart = new DateTimeImmutable($calendarStart);
    $rangeEnd = new DateTimeImmutable($calendarEnd);
    $totalDays = $rangeStart->diff($rangeEnd)->days + 1;
    $weekdays = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'];
    $months = ['Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni', 'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'];
    $today = date('Y-m-d');

    $days = [];
    $todayIndex = null;
    $cursor = $rangeStart;
    for ($i = 0; $i < $totalDays; $i++) {
        $dateStr = $cursor->format('Y-m-d');
        $days[$i] = $cursor;
        if ($dateStr === $today) {
            $todayIndex = $i;
        }
        $cursor = $cursor->modify('+1 day');
    }

    // Group each step's occurring days into contiguous runs, so a multi-day span
    // becomes one bar and scattered recurring days become separate short bars.
    $stepRuns = [];
    foreach ($steps as $stepIndex => $step) {
        $runs = [];
        $runStart = null;
        for ($i = 0; $i < $totalDays; $i++) {
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
            $runs[] = [$runStart, $totalDays - $runStart];
        }
        $stepRuns[$stepIndex] = $runs;
    }
    ?>
    <div class="calendar-legend">
        <span><i class="calendar-dot calendar-dot--eenmalig"></i> Eenmalig</span>
        <span><i class="calendar-dot calendar-dot--periodiek"></i> Periodiek</span>
    </div>
    <div class="gantt-wrap" id="ganttWrap">
        <div class="gantt" style="grid-template-columns:repeat(<?= $totalDays ?>,56px);grid-template-rows:38px repeat(<?= count($steps) ?>,38px)">
            <?php if ($todayIndex !== null): ?>
                <div class="gantt-today-line" style="grid-column:<?= $todayIndex + 1 ?>;grid-row:1 / span <?= count($steps) + 1 ?>"></div>
            <?php endif; ?>
            <?php foreach ($days as $i => $day): $showMonth = $i === 0 || (int) $day->format('j') === 1; ?>
                <div class="gantt-day-header<?= $i === $todayIndex ? ' today' : '' ?>" style="grid-column:<?= $i + 1 ?>;grid-row:1">
                    <span class="cal-month-label"><?= $showMonth ? e($months[(int) $day->format('n') - 1]) : '' ?></span>
                    <span class="cal-weekday"><?= e($weekdays[(int) $day->format('N') - 1]) ?></span>
                    <span class="cal-daynum"><?= (int) $day->format('j') ?></span>
                </div>
            <?php endforeach; ?>
            <?php foreach ($steps as $stepIndex => $step): ?>
                <?php foreach ($stepRuns[$stepIndex] as [$startCol, $length]): ?>
                    <div class="gantt-bar gantt-bar--<?= e($step['type']) ?>" style="grid-column:<?= $startCol + 1 ?> / span <?= $length ?>;grid-row:<?= $stepIndex + 2 ?>" title="<?= e($step['title']) ?>"><?= e($step['title']) ?></div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    (() => {
        const wrap = document.getElementById('ganttWrap');
        const todayLine = wrap ? wrap.querySelector('.gantt-today-line') : null;
        if (wrap && todayLine) {
            wrap.scrollLeft = Math.max(0, todayLine.offsetLeft - wrap.clientWidth / 2);
        }
    })();
    </script>
<?php endif; ?>
