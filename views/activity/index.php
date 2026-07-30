<?php

use App\Support\View;

/** @var array $activities */

$categoryIcons = [
    'kasstroom' => '💳',
    'inkomsten' => '💰',
    'vaste-lasten' => '🧾',
    'potjes' => '🐷',
    'periods' => '📅',
];
?>
<div class="card">
    <h2 class="mt-0">Activiteit</h2>
    <?php if (empty($activities)): ?>
        <p class="text-muted">Nog geen activiteit.</p>
    <?php else: ?>
        <div class="timeline">
            <?php foreach ($activities as $a): ?>
                <div class="timeline-item">
                    <div class="timeline-icon"><?= View::e($categoryIcons[$a['category']] ?? '•') ?></div>
                    <div class="timeline-body">
                        <div class="timeline-desc"><?= View::e($a['description']) ?></div>
                        <div class="timeline-meta">
                            <span class="timeline-user"><?= View::e($a['user_name']) ?></span>
                            <span class="timeline-time"><?= View::e(date('d-m-Y H:i', strtotime($a['occurred_at']))) ?></span>
                        </div>
                    </div>
                    <?php if ($a['amount'] !== null): ?>
                        <div class="timeline-amount <?= (float) $a['amount'] < 0 ? 'negative' : 'positive' ?>"><?= View::money((float) $a['amount']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
