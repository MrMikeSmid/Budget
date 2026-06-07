<?php $viewer = current_user(); $flashes = pull_flashes(); ?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#6d4aff">
    <title><?= e($title ?? config('name')) ?> · <?= e(config('name')) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="<?= $viewer ? 'is-authenticated' : 'is-guest' ?>">
<div class="ambient ambient-one"></div><div class="ambient ambient-two"></div>
<div class="app-shell">
    <?php if ($viewer && empty($viewer['password_hash'])): ?>
        <a class="security-nudge" href="<?= e(url('/settings#beveiliging')) ?>">
            <span class="security-nudge__icon">♢</span>
            <span><strong>Bescherm je account</strong><small>Stel straks rustig een wachtwoord in</small></span>
            <span aria-hidden="true">→</span>
        </a>
    <?php endif; ?>
    <?php foreach ($flashes as $type => $message): ?>
        <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span><button type="button" aria-label="Sluiten">×</button></div>
    <?php endforeach; ?>
    <main class="page-content"><?= $content ?></main>
    <?php if ($viewer): ?>
        <nav class="bottom-nav" aria-label="Hoofdnavigatie">
            <a href="<?= e(url('/')) ?>" class="<?= ($_SERVER['REQUEST_URI'] ?? '') === url('/') ? 'active' : '' ?>"><span class="nav-icon">⌂</span><span>Lijstjes</span></a>
            <button type="button" class="nav-create" data-open-modal="new-list" aria-label="Nieuw lijstje"><span>＋</span></button>
            <a href="<?= e(url('/settings')) ?>" class="<?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/settings') ? 'active' : '' ?>"><span class="nav-icon">◉</span><span>Profiel</span></a>
        </nav>
        <dialog class="modal" id="new-list">
            <form method="post" action="<?= e(url('/lists')) ?>" class="modal-card">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <div class="modal-handle"></div>
                <div class="modal-heading"><div><span class="eyebrow">Iets nieuws</span><h2>Maak een lijstje</h2></div><button type="button" class="icon-button" data-close-modal aria-label="Sluiten">×</button></div>
                <label class="field"><span>Naam van je lijst</span><input name="title" maxlength="80" placeholder="Bijv. Weekendje weg" required autofocus></label>
                <fieldset class="choice-group"><legend>Kies een sfeer</legend><div class="emoji-choices">
                    <?php foreach (['✨','🛒','🏠','✈️','🎉'] as $index => $emoji): ?><label><input type="radio" name="emoji" value="<?= $emoji ?>" <?= $index === 0 ? 'checked' : '' ?>><span><?= $emoji ?></span></label><?php endforeach; ?>
                </div><div class="color-choices">
                    <?php foreach (['violet','coral','mint','sun'] as $index => $color): ?><label><input type="radio" name="color" value="<?= $color ?>" <?= $index === 0 ? 'checked' : '' ?>><span class="swatch swatch--<?= $color ?>"></span></label><?php endforeach; ?>
                </div></fieldset>
                <button class="button button--primary button--wide">Lijstje maken <span>→</span></button>
            </form>
        </dialog>
    <?php endif; ?>
</div>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body></html>
