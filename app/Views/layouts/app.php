<?php
$viewer = current_user();
$flashes = pull_flashes();
$currentPath = (new \App\Core\Request())->path();
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#2f5d62">
    <meta name="application-name" content="Regie">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Regie">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?= e($title ?? config('name')) ?> · <?= e(config('name')) ?></title>
    <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= e(url('/pwa-icon/favicon-32')) ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= e(url('/pwa-icon/apple-touch-180')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="<?= $viewer ? 'is-authenticated' : 'is-guest' ?>" data-service-worker="<?= e(url('/sw.js')) ?>" data-app-scope="<?= e(url('/')) ?>">
<div class="app-shell">
    <?php if ($viewer): ?>
        <nav class="bottom-nav" aria-label="Hoofdnavigatie">
            <span class="nav-brand desktop-only">Regie</span>
            <a href="<?= e(url('/')) ?>" class="<?= $currentPath === '/' ? 'active' : '' ?>"><span class="nav-icon" aria-hidden="true">🏠</span><span>Home</span></a>
            <a href="<?= e(url('/parken')) ?>" class="<?= str_starts_with($currentPath, '/parken') ? 'active' : '' ?>"><span class="nav-icon" aria-hidden="true">📍</span><span>Parken</span></a>
            <a href="<?= e(url('/personen')) ?>" class="<?= str_starts_with($currentPath, '/personen') ? 'active' : '' ?>"><span class="nav-icon" aria-hidden="true">👥</span><span>Personen</span></a>
            <a href="<?= e(url('/items')) ?>" class="<?= str_starts_with($currentPath, '/items') ? 'active' : '' ?>"><span class="nav-icon" aria-hidden="true">✅</span><span>Taken</span></a>
            <form method="post" action="<?= e(url('/logout')) ?>" class="desktop-only" style="margin-top:auto">
                <input type="hidden" name="_token" value="<?= e(csrf_token()) ?>">
                <button class="button button--ghost button--small button--wide" type="submit">Uitloggen</button>
            </form>
        </nav>
    <?php endif; ?>
    <main class="page-content">
        <?php foreach ($flashes as $type => $message): ?>
            <div class="toast toast--<?= e($type) ?>" role="status"><span><?= e($message) ?></span><button type="button" onclick="this.closest('.toast').remove()" aria-label="Sluiten">×</button></div>
        <?php endforeach; ?>
        <?= $content ?>
    </main>
</div>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
