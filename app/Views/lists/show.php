<?php
$done = count(array_filter($items, fn($item) => (int) $item['is_completed'] === 1));
$total = count($items);
$percent = $total ? round($done / $total * 100) : 0;
$isOwner = (int) $list['owner_id'] === (int) $user['id'];
$openItems = array_values(array_filter($items, fn($item) => (int) $item['is_completed'] === 0));
$completedItems = array_values(array_filter($items, fn($item) => (int) $item['is_completed'] === 1));
$commentLabel = static fn(array $item): string => (int) $item['comment_count'] . ' ' . ((int) $item['comment_count'] === 1 ? 'reactie' : 'reacties');
?>
<section
    class="detail-page detail-page--<?= e($list['color']) ?>"
    data-live-list
    data-state-url="<?= e(url('/lists/' . $list['id'] . '/state')) ?>"
    data-toggle-url="<?= e(url('/lists/' . $list['id'] . '/items/__ITEM_ID__/toggle')) ?>"
    data-delete-url="<?= e(url('/lists/' . $list['id'] . '/items/__ITEM_ID__/delete')) ?>"
    data-comment-url="<?= e(url('/lists/' . $list['id'] . '/items/__ITEM_ID__/comments')) ?>"
    data-csrf-token="<?= e(csrf_token()) ?>"
>
    <header class="detail-header">
        <a class="icon-button icon-button--glass" href="<?= e(url('/')) ?>" aria-label="Terug"><span class="ui-icon ui-icon--arrow-left" aria-hidden="true"></span></a>
        <div class="member-stack member-stack--large" data-member-stack="header"><?php foreach (array_slice($members, 0, 3) as $member): ?><i class="<?= $member['is_online'] ? 'member-avatar--online' : '' ?>" title="<?= e($member['name']) ?> is <?= $member['is_online'] ? 'online' : 'offline' ?>" aria-label="<?= e($member['name']) ?> is <?= $member['is_online'] ? 'online' : 'offline' ?>"><?= e(mb_strtoupper(mb_substr($member['name'], 0, 1))) ?></i><?php endforeach; ?></div>
        <?php if ($isOwner): ?><button class="icon-button icon-button--glass" type="button" data-open-modal="share-list" aria-label="Delen">↗</button><?php else: ?><span class="icon-button icon-button--glass">♡</span><?php endif; ?>
    </header>
    <div class="detail-title">
        <span class="detail-emoji"><?= render_list_mood_icon($list['emoji']) ?></span>
        <span class="eyebrow" data-plan-label><?= count($members) > 1 ? 'Een plan van jullie samen' : 'Jouw persoonlijke plan' ?></span>
        <h1><?= e($list['title']) ?></h1>
        <p data-progress-copy><?= $total ? "$done van de $total taken afgevinkt" : 'Voeg hieronder jullie eerste taak toe.' ?></p>
    </div>
    <div class="detail-progress"><span style="width:<?= $percent ?>%" data-progress-bar></span></div>
    <div class="task-sheet">
        <form method="post" action="<?= e(url('/lists/' . $list['id'] . '/items')) ?>" class="quick-add" data-live-add>
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <span>＋</span>
            <input name="title" maxlength="160" placeholder="Voeg een taak toe..." aria-label="Nieuwe taak" required>
            <button>Toevoegen</button>
        </form>
        <section class="task-section" data-task-section="open">
            <div class="task-heading"><div><span class="eyebrow">Nog even doen</span><h2>Te doen</h2></div><span data-open-count><?= count($openItems) ?> open</span></div>
            <div data-task-container="open">
                <?php if ($openItems): ?>
                    <div class="task-list">
                        <?php foreach ($openItems as $item): ?>
                            <article class="task">
                                <form method="post" action="<?= e(url('/lists/' . $list['id'] . '/items/' . $item['id'] . '/toggle')) ?>" class="task-toggle-form" data-live-toggle>
                                    <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                    <button class="task-check" aria-label="Vink af"></button>
                                </form>
                                <button type="button" class="task-content" data-task-details="<?= (int) $item['id'] ?>"><strong><?= e($item['title']) ?></strong><small><span>Toegevoegd door <?= e($item['creator_name']) ?></span><span data-comment-count><?= e($commentLabel($item)) ?></span></small></button>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="tasks-empty tasks-empty--compact"><span>✓</span><h3>Alles is gedaan</h3><p>Voeg gerust een nieuwe taak toe.</p></div>
                <?php endif; ?>
            </div>
        </section>
        <section class="task-section task-section--completed" data-task-section="completed" <?= $completedItems ? '' : 'hidden' ?>>
            <div class="task-heading task-heading--completed"><div><span class="eyebrow">Lekker bezig</span><h2>Afgerond</h2></div><span data-completed-count><?= count($completedItems) ?> klaar</span></div>
            <div data-task-container="completed">
                <?php if ($completedItems): ?><div class="task-list">
                    <?php foreach ($completedItems as $item): ?>
                        <article class="task task--done">
                            <form method="post" action="<?= e(url('/lists/' . $list['id'] . '/items/' . $item['id'] . '/toggle')) ?>" class="task-toggle-form" data-live-toggle>
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <button class="task-check" aria-label="Markeer als niet gedaan">✓</button>
                            </form>
                            <button type="button" class="task-content" data-task-details="<?= (int) $item['id'] ?>"><strong><?= e($item['title']) ?></strong><small><span>Afgevinkt door <?= e($item['completer_name']) ?></span><span data-comment-count><?= e($commentLabel($item)) ?></span></small></button>
                            <form method="post" action="<?= e(url('/lists/' . $list['id'] . '/items/' . $item['id'] . '/delete')) ?>" class="task-delete-form" data-live-delete>
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <button class="task-delete" aria-label="Verwijder <?= e($item['title']) ?>"><span aria-hidden="true">×</span></button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div><?php endif; ?>
            </div>
        </section>
        <div class="people-row">
            <div><span class="eyebrow">In dit lijstje</span><h3 data-member-count><?= count($members) ?> <?= count($members) === 1 ? 'persoon' : 'personen' ?></h3></div>
            <div class="member-stack member-stack--large" data-member-stack="people"><?php foreach (array_slice($members, 0, 4) as $member): ?><i class="<?= $member['is_online'] ? 'member-avatar--online' : '' ?>" title="<?= e($member['name']) ?> is <?= $member['is_online'] ? 'online' : 'offline' ?>" aria-label="<?= e($member['name']) ?> is <?= $member['is_online'] ? 'online' : 'offline' ?>"><?= e(mb_strtoupper(mb_substr($member['name'], 0, 1))) ?></i><?php endforeach; ?></div>
        </div>
        <?php if ($isOwner): ?><button type="button" class="share-callout" data-open-modal="share-list"><span class="share-callout__icon">♡</span><span><strong>Nodig iemand uit</strong><small>Samen afvinken is leuker</small></span><span class="ui-icon ui-icon--arrow-right" aria-hidden="true"></span></button><?php endif; ?>
        <?php if ($isOwner): ?><form method="post" action="<?= e(url('/lists/' . $list['id'] . '/delete')) ?>" onsubmit="return confirm('Weet je zeker dat je dit lijstje wilt verwijderen?')" class="delete-form"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><button class="text-link text-link--danger">Lijstje verwijderen</button></form><?php endif; ?>
    </div>
</section>
<script type="application/json" data-initial-list-state><?= json_encode($initialState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<dialog class="modal task-comments-modal" id="task-comments">
    <div class="modal-card">
        <div class="modal-handle"></div>
        <div class="modal-heading"><div><span class="eyebrow">Taak</span><h2 data-comments-task-title></h2></div><button type="button" class="icon-button" data-close-modal aria-label="Sluiten">×</button></div>
        <div class="comment-list" data-comment-list></div>
        <form method="post" class="comment-form" data-live-comment>
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Nieuwe reactie</span><textarea name="body" maxlength="1000" rows="3" placeholder="Schrijf een reactie..." required></textarea></label>
            <button class="button button--primary button--wide">Reactie plaatsen</button>
        </form>
    </div>
</dialog>
<?php if ($isOwner): ?><dialog class="modal" id="share-list"><form method="post" action="<?= e(url('/lists/' . $list['id'] . '/share')) ?>" class="modal-card"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><div class="modal-handle"></div><div class="modal-heading"><div><span class="eyebrow">Samen is leuker</span><h2>Nodig iemand uit</h2></div><button type="button" class="icon-button" data-close-modal>×</button></div><p class="modal-intro">Vul het e-mailadres in. We maken alvast een plek voor diegene; inloggen kan direct met hetzelfde adres.</p><label class="field"><span>E-mailadres</span><input type="email" name="email" placeholder="vriend@voorbeeld.nl" required></label><button class="button button--primary button--wide">Uitnodigen <span>♡</span></button></form></dialog><?php endif; ?>
