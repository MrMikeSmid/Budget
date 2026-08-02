<?php

use App\Support\View;

$flash = View::flash();
?>
<!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Budgetapp</title>
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#2563eb">
<link rel="icon" type="image/png" sizes="32x32" href="<?= View::e(View::asset('icons/favicon-32.png')) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= View::e(View::asset('icons/favicon-16.png')) ?>">
<link rel="apple-touch-icon" href="<?= View::e(View::asset('icons/apple-touch-icon.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Budgetapp">
<link rel="stylesheet" href="<?= View::e(View::asset('css/app.css')) ?>">
<script src="<?= View::e(View::asset('js/app.js')) ?>" defer></script>
</head>
<body>
<div class="guest-wrap">
    <div class="guest-card">
        <h1>Budgetapp</h1>
        <?php if ($flash): ?>
            <?php [$type, $message] = explode('|', $flash, 2); ?>
            <div class="flash <?= View::e($type) ?>"><?= View::e($message) ?></div>
        <?php endif; ?>
        <?= $content ?>
    </div>
</div>
</body>
</html>
