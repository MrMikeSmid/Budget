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
    <meta name="theme-color" content="#4f7fae">
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
            <a href="<?= e(url('/')) ?>" class="<?= $currentPath === '/' ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"/><path d="M5.5 10v9a1 1 0 0 0 1 1H9.5a1 1 0 0 0 1-1v-4a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v4a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-9"/></svg>
                <span>Home</span>
            </a>
            <a href="<?= e(url('/parken')) ?>" class="<?= str_starts_with($currentPath, '/parken') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.4"/></svg>
                <span>Parken</span>
            </a>
            <a href="<?= e(url('/personen')) ?>" class="<?= str_starts_with($currentPath, '/personen') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 19.5c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/><path d="M16 8.2A2.7 2.7 0 1 1 16.2 8"/><path d="M15.5 14.7c2.4.4 4 2.2 4 4.8"/></svg>
                <span>Personen</span>
            </a>
            <a href="<?= e(url('/items')) ?>" class="<?= str_starts_with($currentPath, '/items') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M8.5 12.3 11 14.8l4.8-5.3"/></svg>
                <span>Taken</span>
            </a>
            <a href="<?= e(url('/draaiboeken')) ?>" class="<?= str_starts_with($currentPath, '/draaiboeken') || str_starts_with($currentPath, '/afdelingen') ? 'active' : '' ?>">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3.5" width="14" height="17" rx="2"/><path d="M9 3.5v2.2h6V3.5"/><path d="M8.5 11.5h7M8.5 14.7h7M8.5 17.3h4"/></svg>
                <span>Draaiboeken</span>
            </a>
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
