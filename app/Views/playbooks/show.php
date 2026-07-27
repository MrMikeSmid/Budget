<header class="topbar">
    <div><span class="eyebrow"><?= e($playbook['department_name']) ?><?= $playbook['park_name'] ? ' · ' . e($playbook['park_name']) : '' ?></span><h1><?= e($playbook['title']) ?></h1></div>
    <a class="icon-button" href="<?= e(url('/draaiboeken/' . $playbook['id'] . '/bewerken')) ?>" title="Bewerken">✎</a>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<div class="card">
    <div><strong>Leiding:</strong> <?= e($playbook['leader_name']) ?></div>
    <?php if ($playbook['description']): ?><p style="margin:10px 0 0;font-size:13px;white-space:pre-wrap"><?= e($playbook['description']) ?></p><?php endif; ?>
</div>

<div class="card" style="background:var(--brand-soft)">
    <div class="card-row">
        <div><strong>Deelbare link</strong><br><small>Iedereen met deze link kan het draaiboek bekijken, zonder in te loggen.</small></div>
    </div>
    <div class="field-row" style="margin-top:10px;align-items:flex-end">
        <label class="field" style="margin-bottom:0"><input readonly onclick="this.select()" value="<?= e($shareUrl) ?>"></label>
        <form method="post" action="<?= e(url('/draaiboeken/' . $playbook['id'] . '/vernieuw-link')) ?>" onsubmit="return confirm('De huidige link stopt direct met werken. Nieuwe link genereren?')">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <button class="button button--soft button--small" type="submit">Vernieuw link</button>
        </form>
    </div>
</div>

<div class="section-heading"><h2>Tijdlijn</h2></div>
<?php if (empty($steps)): ?>
    <div class="empty">Nog geen stappen toegevoegd.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($steps as $step): $state = playbook_step_state($step); ?>
            <div class="card">
                <div class="card-row">
                    <h3><?= e(schedule_type_label($step['schedule_type'])) ?> <?= e(format_date($step['date'])) ?>: <?= e($step['title']) ?></h3>
                    <span class="badge <?= $state['class'] ?>"><?= e($state['label']) ?></span>
                </div>
                <?php if ($step['body']): ?><p style="margin:8px 0 0;font-size:13px;white-space:pre-wrap"><?= e($step['body']) ?></p><?php endif; ?>
                <div class="card-row" style="margin-top:10px;gap:8px">
                    <form method="post" action="<?= e(url('/stappen/' . $step['id'] . '/toggle')) ?>">
                        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                        <button class="button button--soft button--small" type="submit"><?= $step['status'] === 'afgerond' ? 'Heropenen' : 'Afronden' ?></button>
                    </form>
                    <details class="edit-toggle" style="flex:1">
                        <summary>Bewerken</summary>
                        <div class="card">
                            <form method="post" action="<?= e(url('/stappen/' . $step['id'] . '/update')) ?>">
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <fieldset>
                                    <legend>Type</legend>
                                    <div class="choice-row">
                                        <label><input type="radio" name="schedule_type" value="op_datum" <?= $step['schedule_type'] === 'op_datum' ? 'checked' : '' ?>> Op datum</label>
                                        <label><input type="radio" name="schedule_type" value="vanaf_datum" <?= $step['schedule_type'] === 'vanaf_datum' ? 'checked' : '' ?>> Vanaf datum</label>
                                    </div>
                                </fieldset>
                                <label class="field"><span>Datum</span><input type="date" name="date" value="<?= e($step['date']) ?>" required></label>
                                <label class="field"><span>Titel</span><input name="title" maxlength="160" value="<?= e($step['title']) ?>" required></label>
                                <label class="field"><span>Toelichting</span><textarea name="body"><?= e($step['body']) ?></textarea></label>
                                <button class="button button--primary button--wide" type="submit">Opslaan</button>
                            </form>
                            <form method="post" action="<?= e(url('/stappen/' . $step['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('Stap verwijderen?')">
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <button class="button button--danger button--wide" type="submit">Verwijderen</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<details class="edit-toggle">
    <summary>+ Nieuwe stap</summary>
    <div class="card">
        <form method="post" action="<?= e(url('/draaiboeken/' . $playbook['id'] . '/stappen')) ?>">
            <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
            <fieldset>
                <legend>Type</legend>
                <div class="choice-row">
                    <label><input type="radio" name="schedule_type" value="op_datum" checked> Op datum</label>
                    <label><input type="radio" name="schedule_type" value="vanaf_datum"> Vanaf datum</label>
                </div>
            </fieldset>
            <label class="field"><span>Datum</span><input type="date" name="date" required></label>
            <label class="field"><span>Titel</span><input name="title" maxlength="160" required placeholder="Wat moet er gebeuren?"></label>
            <label class="field"><span>Toelichting</span><textarea name="body"></textarea></label>
            <button class="button button--primary button--wide" type="submit">Stap toevoegen</button>
        </form>
    </div>
</details>
