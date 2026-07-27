<header class="topbar">
    <div><span class="eyebrow"><?= e($park['name']) ?> · <?= $person['type'] === 'staff' ? 'Medewerker' : 'Gast' ?></span><h1><?= e($person['name']) ?></h1></div>
    <div style="display:flex;gap:8px">
        <a class="icon-button" href="<?= e(url('/personen/' . $person['id'] . '/print')) ?>" title="Print">⎙</a>
        <a class="icon-button" href="<?= e(url('/personen/' . $person['id'] . '/bewerken')) ?>" title="Bewerken">✎</a>
    </div>
</header>

<?php foreach (pull_flashes() as $type => $message): ?>
    <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span></div>
<?php endforeach; ?>

<div class="card">
    <?php if ($person['role']): ?><div><strong><?= e($person['role']) ?></strong></div><?php endif; ?>
    <?php if ($person['email'] || $person['phone']): ?><small><?= e($person['email']) ?><?= $person['email'] && $person['phone'] ? ' · ' : '' ?><?= e($person['phone']) ?></small><?php endif; ?>
    <?php if ($person['notes']): ?><p style="margin:10px 0 0;font-size:13px;white-space:pre-wrap"><?= e($person['notes']) ?></p><?php endif; ?>
    <?php if (!$person['is_active']): ?><div style="margin-top:8px"><span class="badge badge--muted">Inactief</span></div><?php endif; ?>
</div>

<div class="section-heading">
    <h2>Notities, afspraken &amp; taken</h2>
    <a class="button button--soft button--small" href="<?= e(url('/parken/' . $park['id'] . '/items/nieuw?category=' . ($person['type'] === 'staff' ? 'personeel' : 'gasten') . '&person_id=' . $person['id'])) ?>">+ Toevoegen</a>
</div>
<?php if (empty($items)): ?>
    <div class="empty">Nog niets genoteerd bij deze persoon.</div>
<?php else: ?>
    <div class="card-list">
        <?php foreach ($items as $item): $overdue = is_overdue($item['due_date'], $item['status']); ?>
            <div class="card">
                <div class="card-row">
                    <h3><?= e($item['title']) ?></h3>
                    <span class="badge <?= $item['status'] === 'afgerond' ? 'badge--ok' : ($overdue ? 'badge--danger' : 'badge--muted') ?>"><?= $overdue ? 'Vervallen' : e(status_label($item['status'])) ?></span>
                </div>
                <small><?= e(item_type_label($item['type'])) ?><?= $item['due_date'] ? ' · ' . e(format_date($item['due_date'])) : '' ?></small>
                <?php if ($item['body']): ?><p style="margin:8px 0 0;font-size:13px;white-space:pre-wrap"><?= e($item['body']) ?></p><?php endif; ?>
                <div class="card-row" style="margin-top:10px;gap:8px">
                    <form method="post" action="<?= e(url('/items/' . $item['id'] . '/toggle')) ?>">
                        <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                        <button class="button button--soft button--small" type="submit"><?= $item['status'] === 'afgerond' ? 'Heropenen' : 'Afronden' ?></button>
                    </form>
                    <a class="button button--ghost button--small" href="<?= e(url('/items/' . $item['id'] . '/bewerken')) ?>">Bewerken</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($person['type'] === 'staff'): ?>
    <div class="section-heading"><h2>Verzuim</h2></div>
    <?php if (empty($absences)): ?>
        <div class="empty">Geen verzuimregistraties.</div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($absences as $absence): ?>
                <div class="card">
                    <div class="card-row">
                        <h3><?= e(format_date($absence['start_date'])) ?> <?= $absence['end_date'] ? '– ' . e(format_date($absence['end_date'])) : '(loopt nog)' ?></h3>
                        <span class="badge <?= $absence['status'] === 'hersteld' ? 'badge--ok' : 'badge--warn' ?>"><?= e(absence_status_label($absence['status'])) ?></span>
                    </div>
                    <?php if ($absence['reason']): ?><small><?= e($absence['reason']) ?></small><?php endif; ?>
                    <?php if ($absence['notes']): ?><p style="margin:8px 0 0;font-size:13px;white-space:pre-wrap"><?= e($absence['notes']) ?></p><?php endif; ?>
                    <details class="edit-toggle">
                        <summary>Bewerken / afsluiten</summary>
                        <div class="card">
                            <form method="post" action="<?= e(url('/verzuim/' . $absence['id'] . '/update')) ?>">
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <div class="field-row">
                                    <label class="field"><span>Start</span><input type="date" name="start_date" value="<?= e($absence['start_date']) ?>" required></label>
                                    <label class="field"><span>Einde</span><input type="date" name="end_date" value="<?= e($absence['end_date'] ?? '') ?>"></label>
                                </div>
                                <label class="field"><span>Reden</span><input name="reason" value="<?= e($absence['reason']) ?>"></label>
                                <label class="field"><span>Status</span><select name="status">
                                    <?php foreach (['lopend', 'hersteld', 'langdurig'] as $s): ?><option value="<?= $s ?>" <?= $absence['status'] === $s ? 'selected' : '' ?>><?= e(absence_status_label($s)) ?></option><?php endforeach; ?>
                                </select></label>
                                <label class="field"><span>Notities</span><textarea name="notes"><?= e($absence['notes']) ?></textarea></label>
                                <button class="button button--primary button--wide" type="submit">Opslaan</button>
                            </form>
                            <form method="post" action="<?= e(url('/verzuim/' . $absence['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('Verzuimregel verwijderen?')">
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <button class="button button--danger button--wide" type="submit">Verwijderen</button>
                            </form>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <details class="edit-toggle" style="margin-bottom:20px">
        <summary>+ Nieuwe verzuimregistratie</summary>
        <div class="card">
            <form method="post" action="<?= e(url('/personen/' . $person['id'] . '/verzuim')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <div class="field-row">
                    <label class="field"><span>Start</span><input type="date" name="start_date" required></label>
                    <label class="field"><span>Einde (optioneel)</span><input type="date" name="end_date"></label>
                </div>
                <label class="field"><span>Reden</span><input name="reason" placeholder="Bijv. ziekte, verlof"></label>
                <label class="field"><span>Notities</span><textarea name="notes" placeholder="Afspraken over begeleiding, bedrijfsarts, etc."></textarea></label>
                <button class="button button--primary button--wide" type="submit">Registreren</button>
            </form>
        </div>
    </details>

    <div class="section-heading"><h2>Functioneringsgesprekken</h2></div>
    <?php if (empty($reviews)): ?>
        <div class="empty">Nog geen gesprekken vastgelegd.</div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($reviews as $review): ?>
                <div class="card">
                    <div class="card-row">
                        <h3><?= e(format_date($review['review_date'])) ?></h3>
                        <span class="badge badge--muted"><?= e(review_type_label($review['type'])) ?></span>
                    </div>
                    <?php if ($review['summary']): ?><p style="margin:8px 0 0;font-size:13px;white-space:pre-wrap"><?= e($review['summary']) ?></p><?php endif; ?>
                    <?php if ($review['follow_up_date']): ?><small>Vervolg: <?= e(format_date($review['follow_up_date'])) ?></small><?php endif; ?>
                    <details class="edit-toggle">
                        <summary>Bewerken</summary>
                        <div class="card">
                            <form method="post" action="<?= e(url('/gesprekken/' . $review['id'] . '/update')) ?>">
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <div class="field-row">
                                    <label class="field"><span>Datum</span><input type="date" name="review_date" value="<?= e($review['review_date']) ?>" required></label>
                                    <label class="field"><span>Vervolgdatum</span><input type="date" name="follow_up_date" value="<?= e($review['follow_up_date'] ?? '') ?>"></label>
                                </div>
                                <label class="field"><span>Type</span><select name="type">
                                    <?php foreach (['functioneringsgesprek', 'beoordelingsgesprek', 'proefperiode', 'overig'] as $t): ?><option value="<?= $t ?>" <?= $review['type'] === $t ? 'selected' : '' ?>><?= e(review_type_label($t)) ?></option><?php endforeach; ?>
                                </select></label>
                                <label class="field"><span>Samenvatting</span><textarea name="summary"><?= e($review['summary']) ?></textarea></label>
                                <button class="button button--primary button--wide" type="submit">Opslaan</button>
                            </form>
                            <form method="post" action="<?= e(url('/gesprekken/' . $review['id'] . '/delete')) ?>" class="inline-form" onsubmit="return confirm('Gesprek verwijderen?')">
                                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                                <button class="button button--danger button--wide" type="submit">Verwijderen</button>
                            </form>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <details class="edit-toggle">
        <summary>+ Nieuw gesprek</summary>
        <div class="card">
            <form method="post" action="<?= e(url('/personen/' . $person['id'] . '/gesprekken')) ?>">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <div class="field-row">
                    <label class="field"><span>Datum</span><input type="date" name="review_date" required></label>
                    <label class="field"><span>Vervolgdatum</span><input type="date" name="follow_up_date"></label>
                </div>
                <label class="field"><span>Type</span><select name="type">
                    <?php foreach (['functioneringsgesprek', 'beoordelingsgesprek', 'proefperiode', 'overig'] as $t): ?><option value="<?= $t ?>"><?= e(review_type_label($t)) ?></option><?php endforeach; ?>
                </select></label>
                <label class="field"><span>Samenvatting</span><textarea name="summary" placeholder="Belangrijkste punten en afspraken"></textarea></label>
                <button class="button button--primary button--wide" type="submit">Vastleggen</button>
            </form>
        </div>
    </details>
<?php endif; ?>
