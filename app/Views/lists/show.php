<?php
$done = count(array_filter($items, fn($item) => (int) $item['is_completed'] === 1));
$total = count($items);
$percent = $total ? round($done / $total * 100) : 0;
$isOwner = (int) $list['owner_id'] === (int) $user['id'];
$isPending = !$isOwner && $list['membership_accepted_at'] === null;
$activeMemberCount = count(array_filter($members, static fn(array $member): bool => (bool) $member['is_active']));
$openItems = array_values(array_filter($items, fn($item) => (int) $item['is_completed'] === 0));
$completedItems = array_values(array_filter($items, fn($item) => (int) $item['is_completed'] === 1));
$commentLabel = static fn(array $item): string => (int) $item['comment_count'] . ' ' . ((int) $item['comment_count'] === 1 ? 'reactie' : 'reacties');
$priorityLabels = ['low' => 'Lage prioriteit', 'medium' => 'Normale prioriteit', 'high' => 'Hoge prioriteit'];
$formatDueDate = static function (?string $date): string {
    if (!$date) {
        return '';
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed ? $parsed->format('d-m-Y') : '';
};
$renderMemberAvatar = static function (array $member): void {
    $status = !$member['is_active'] ? 'uitgenodigd' : ($member['is_online'] ? 'online' : 'actief');
    $imageUrl = $member['profile_image_url'] ?? null;
    ?><i class="<?= $member['is_online'] ? 'member-avatar--online' : '' ?><?= !$member['is_active'] ? ' member-avatar--pending' : '' ?>" title="<?= e($member['name']) ?> is <?= e($status) ?>" aria-label="<?= e($member['name']) ?> is <?= e($status) ?>"><?php if ($imageUrl): ?><img src="<?= e($imageUrl) ?>" alt=""><?php else: ?><?= e(mb_strtoupper(mb_substr($member['name'], 0, 1))) ?><?php endif; ?></i><?php
};
$renderTask = static function (array $item) use ($list, $commentLabel, $priorityLabels, $formatDueDate): void {
    $isCompleted = (int) $item['is_completed'] === 1;
    $isOverdue = !$isCompleted && !empty($item['due_date']) && $item['due_date'] < date('Y-m-d');
    $hasImage = !empty($item['has_image'])
        || !empty($item['has_image_data'])
        || !empty($item['image_filename']);
    ?>
    <article class="task<?= $isCompleted ? ' task--done' : '' ?><?= $isOverdue ? ' task--overdue' : '' ?>">
        <form method="post" action="<?= e(url('/lists/' . $list['id'] . '/items/' . $item['id'] . '/toggle')) ?>" class="task-toggle-form" data-live-toggle>
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <button class="task-check" aria-label="<?= $isCompleted ? 'Markeer als niet gedaan' : 'Vink af' ?>"><?= $isCompleted ? '✓' : '' ?></button>
        </form>
        <?php if ($hasImage): ?>
            <img class="task-thumbnail" src="<?= e(url('/lists/' . $list['id'] . '/items/' . $item['id'] . '/image')) ?>" alt="" width="48" height="48" decoding="async">
        <?php endif; ?>
        <button type="button" class="task-content" data-task-details="<?= (int) $item['id'] ?>">
            <strong><?= e($item['title']) ?></strong>
            <?php if (($item['priority'] ?? 'none') !== 'none' || !empty($item['due_date'])): ?>
                <span class="task-badges">
                    <?php if (($item['priority'] ?? 'none') !== 'none'): ?><span class="task-badge task-badge--<?= e($item['priority']) ?>"><?= e($priorityLabels[$item['priority']] ?? '') ?></span><?php endif; ?>
                    <?php if (!empty($item['due_date'])): ?><span class="task-badge task-badge--date"><?= $isOverdue ? 'Vervallen' : 'Vervalt' ?> <?= e($formatDueDate($item['due_date'])) ?></span><?php endif; ?>
                </span>
            <?php endif; ?>
            <small><span><?= $isCompleted ? 'Afgevinkt door ' . e($item['completer_name']) : 'Toegevoegd door ' . e($item['creator_name']) ?></span><span class="task-comment-count<?= (int) $item['comment_count'] > 0 ? ' task-comment-count--active' : '' ?>" data-comment-count><?= e($commentLabel($item)) ?></span></small>
        </button>
        <?php if ($isCompleted): ?>
            <form method="post" action="<?= e(url('/lists/' . $list['id'] . '/items/' . $item['id'] . '/delete')) ?>" class="task-delete-form" data-live-delete>
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <button class="task-delete" aria-label="Verwijder <?= e($item['title']) ?>"><span aria-hidden="true">×</span></button>
            </form>
        <?php endif; ?>
    </article>
    <?php
};
?>
<section
    class="detail-page detail-page--<?= e($list['color']) ?><?= $isPending ? ' detail-page--pending' : '' ?>"
    data-live-list
    data-list-id="<?= (int) $list['id'] ?>"
    data-state-url="<?= e(url('/lists/' . $list['id'] . '/state')) ?>"
    data-toggle-url="<?= e(url('/lists/' . $list['id'] . '/items/__ITEM_ID__/toggle')) ?>"
    data-delete-url="<?= e(url('/lists/' . $list['id'] . '/items/__ITEM_ID__/delete')) ?>"
    data-comment-url="<?= e(url('/lists/' . $list['id'] . '/items/__ITEM_ID__/comments')) ?>"
    data-update-url="<?= e(url('/lists/' . $list['id'] . '/items/__ITEM_ID__/update')) ?>"
    data-image-url="<?= e(url('/lists/' . $list['id'] . '/items/__ITEM_ID__/image')) ?>"
    data-member-delete-url="<?= e(url('/lists/' . $list['id'] . '/members/__MEMBER_ID__/delete')) ?>"
    data-is-owner="<?= $isOwner ? 'true' : 'false' ?>"
    data-csrf-token="<?= e(csrf_token()) ?>"
>
    <header class="detail-header">
        <a class="icon-button icon-button--glass" href="<?= e(url('/')) ?>" aria-label="Terug"><span class="ui-icon ui-icon--arrow-left" aria-hidden="true"></span></a>
        <div class="member-stack member-stack--large" data-member-stack="header"><?php foreach (array_slice($members, 0, 3) as $member) { $renderMemberAvatar($member); } ?></div>
        <?php if ($isOwner): ?><button class="icon-button icon-button--glass" type="button" data-open-modal="share-list" aria-label="Delen">↗</button><?php else: ?><span class="icon-button icon-button--glass">♡</span><?php endif; ?>
    </header>
    <div class="detail-title">
        <span class="detail-emoji"><?= render_list_mood_icon($list['emoji']) ?></span>
        <span class="eyebrow" data-plan-label><?= $activeMemberCount > 1 ? 'Een plan van jullie samen' : 'Jouw persoonlijke plan' ?></span>
        <h1><?= e($list['title']) ?></h1>
        <p data-progress-copy><?= $total ? "$done van de $total taken afgevinkt" : 'Voeg hieronder jullie eerste taak toe.' ?></p>
    </div>
    <div class="detail-progress"><span style="width:<?= $percent ?>%" data-progress-bar></span></div>
    <div class="task-sheet">
        <form method="post" action="<?= e(url('/lists/' . $list['id'] . '/items')) ?>" class="quick-add" data-live-add>
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <button type="button" class="quick-add__more" data-open-modal="new-task" aria-label="Taak met details toevoegen">＋</button>
            <input name="title" maxlength="160" placeholder="Voeg een taak toe..." aria-label="Nieuwe taak" required>
            <button>Toevoegen</button>
        </form>
        <section class="task-section" data-task-section="open">
            <div class="task-heading">
                <div><span class="eyebrow">Nog te doen</span><h2 data-open-count><?= count($openItems) ?> open taken</h2></div>
                <div class="task-heading__actions">
                    <label class="task-sort">
                        <span class="sr-only">Sorteer taken</span>
                        <select data-task-sort aria-label="Sorteer taken">
                            <option value="priority_due">Prio &amp; vervaldatum</option>
                            <option value="due_date">Vervaldatum</option>
                            <option value="priority">Prioriteit</option>
                            <option value="newest">Nieuwste eerst</option>
                            <option value="alphabetical">Naam A–Z</option>
                        </select>
                    </label>
                </div>
            </div>
            <div data-task-container="open">
                <?php if ($openItems): ?><div class="task-list"><?php foreach ($openItems as $item) { $renderTask($item); } ?></div><?php else: ?><div class="tasks-empty tasks-empty--compact"><span>✓</span><h3>Alles is gedaan</h3><p>Voeg gerust een nieuwe taak toe.</p></div><?php endif; ?>
            </div>
        </section>
        <section class="task-section task-section--completed" data-task-section="completed" <?= $completedItems ? '' : 'hidden' ?>>
            <div class="task-heading task-heading--completed"><div><span class="eyebrow">Lekker bezig</span><h2>Afgerond</h2></div><span data-completed-count><?= count($completedItems) ?> klaar</span></div>
            <div data-task-container="completed"><?php if ($completedItems): ?><div class="task-list"><?php foreach ($completedItems as $item) { $renderTask($item); } ?></div><?php endif; ?></div>
        </section>
        <div class="people-heading"><div><span class="eyebrow">In dit lijstje</span><h3 data-member-count><?= count($members) ?> <?= count($members) === 1 ? 'persoon' : 'personen' ?></h3></div></div>
        <div class="member-list" data-member-list>
            <?php foreach ($members as $member): $memberStatus = !$member['is_active'] ? 'Uitgenodigd' : ($member['is_online'] ? 'Nu actief' : 'Actief op lijst'); ?>
                <article class="member-card<?= !$member['is_active'] ? ' member-card--pending' : '' ?>">
                    <div class="member-card__avatar"><?php if ($member['profile_image_url']): ?><img src="<?= e($member['profile_image_url']) ?>" alt="Profielfoto van <?= e($member['name']) ?>"><?php else: ?><?= e(mb_strtoupper(mb_substr($member['name'], 0, 1))) ?><?php endif; ?><span class="member-card__presence"></span></div>
                    <div><strong><?= e($member['name']) ?></strong><small><?= $member['is_owner'] ? 'Eigenaar · ' : '' ?><?= e($memberStatus) ?></small></div>
                    <span class="member-status member-status--<?= $member['is_active'] ? 'active' : 'pending' ?>"><?= $member['is_active'] ? 'Actief' : 'Uitgenodigd' ?></span>
                    <?php if ($isOwner && !$member['is_owner']): ?>
                        <form method="post" action="<?= e(url('/lists/' . $list['id'] . '/members/' . $member['id'] . '/delete')) ?>" class="member-remove-form" onsubmit="return confirm('Weet je zeker dat je <?= $member['is_active'] ? 'dit lid' : 'deze uitnodiging' ?> wilt verwijderen?')">
                            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                            <button class="member-remove" aria-label="<?= e($member['name']) ?> verwijderen" title="<?= $member['is_active'] ? 'Lid verwijderen' : 'Uitnodiging verwijderen' ?>">×</button>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($isOwner): ?><button type="button" class="share-callout" data-open-modal="share-list"><span class="share-callout__icon">♡</span><span><strong>Nodig iemand uit</strong><small>Samen afvinken is leuker</small></span><span class="ui-icon ui-icon--arrow-right" aria-hidden="true"></span></button><?php endif; ?>
        <?php if ($isOwner): ?><form method="post" action="<?= e(url('/lists/' . $list['id'] . '/delete')) ?>" onsubmit="return confirm('Weet je zeker dat je dit lijstje wilt verwijderen?')" class="delete-form"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><button class="text-link text-link--danger">Lijstje verwijderen</button></form><?php endif; ?>
    </div>
</section>
<script type="application/json" data-initial-list-state><?= json_encode($initialState, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<?php if (!$isPending): ?><dialog class="modal task-create-modal" id="new-task">
    <form method="post" action="<?= e(url('/lists/' . $list['id'] . '/items')) ?>" enctype="multipart/form-data" class="modal-card" data-live-add data-task-create-form>
        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
        <div class="modal-handle"></div>
        <div class="modal-heading"><div><span class="eyebrow">Nieuwe taak</span><h2>Wat wil je doen?</h2></div><button type="button" class="icon-button" data-close-modal aria-label="Sluiten">×</button></div>
        <label class="field"><span>Taak</span><input name="title" maxlength="160" placeholder="Bijv. Treinkaartjes boeken" required data-task-title></label>
        <label class="task-image-picker">
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" data-task-image-input>
            <span class="task-image-picker__preview" data-task-image-preview><strong>＋</strong></span>
            <span><strong>Afbeelding toevoegen</strong><small>JPG, PNG, WebP of GIF · max. 5 MB</small></span>
        </label>
        <div class="task-form-grid">
            <label class="field"><span>Prioriteit</span><select name="priority"><option value="none">Geen prioriteit</option><option value="low">Laag</option><option value="medium">Normaal</option><option value="high">Hoog</option></select></label>
            <label class="field"><span>Vervaldatum</span><input type="date" name="due_date" min="<?= e(date('Y-m-d')) ?>"></label>
        </div>
        <button class="button button--primary button--wide">Taak toevoegen <span class="ui-icon ui-icon--arrow-right" aria-hidden="true"></span></button>
    </form>
</dialog><?php endif; ?>
<dialog class="modal task-comments-modal" id="task-comments">
    <div class="modal-card">
        <div class="modal-handle"></div>
        <div class="modal-heading"><div><span class="eyebrow">Taak</span><h2 data-comments-task-title></h2></div><button type="button" class="icon-button" data-close-modal aria-label="Sluiten">×</button></div>
        <div class="task-detail-media" data-task-detail-media hidden><img data-task-detail-image src="" alt=""></div>
        <div class="task-detail-badges" data-task-detail-badges></div>
        <?php if (!$isPending): ?>
            <button type="button" class="task-edit-toggle" data-toggle-task-edit><span aria-hidden="true">✎</span> Taak bewerken</button>
            <form method="post" class="task-edit-form" data-live-edit hidden>
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <label class="field"><span>Taak</span><input name="title" maxlength="160" required data-edit-task-title></label>
                <div class="task-form-grid">
                    <label class="field"><span>Prioriteit</span><select name="priority" data-edit-task-priority><option value="none">Geen prioriteit</option><option value="low">Laag</option><option value="medium">Normaal</option><option value="high">Hoog</option></select></label>
                    <label class="field"><span>Vervaldatum</span><input type="date" name="due_date" data-edit-task-due-date></label>
                </div>
                <div class="task-edit-form__actions"><button type="button" class="text-link" data-cancel-task-edit>Annuleren</button><button class="button button--primary">Wijzigingen opslaan</button></div>
            </form>
        <?php endif; ?>
        <div class="comment-list" data-comment-list></div>
        <form method="post" class="comment-form" data-live-comment<?= $isPending ? ' data-pending-invitation hidden' : '' ?>>
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <label class="field"><span>Nieuwe reactie</span><textarea name="body" maxlength="1000" rows="3" placeholder="Schrijf een reactie..." required></textarea></label>
            <button class="button button--primary button--wide">Reactie plaatsen</button>
        </form>
    </div>
</dialog>
<?php if ($isOwner): ?><dialog class="modal" id="share-list"><form method="post" action="<?= e(url('/lists/' . $list['id'] . '/share')) ?>" class="modal-card"><input type="hidden" name="_token" value="<?= e(csrf_token()) ?>"><div class="modal-handle"></div><div class="modal-heading"><div><span class="eyebrow">Samen is leuker</span><h2>Nodig iemand uit</h2></div><button type="button" class="icon-button" data-close-modal>×</button></div><p class="modal-intro">Vul het e-mailadres in. De ontvanger verschijnt als uitgenodigd en wordt pas actief nadat de uitnodiging is geaccepteerd.</p><label class="field"><span>E-mailadres</span><input type="email" name="email" placeholder="vriend@voorbeeld.nl" required></label><button class="button button--primary button--wide">Uitnodigen <span>♡</span></button></form></dialog><?php endif; ?>
