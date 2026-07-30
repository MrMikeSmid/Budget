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
<link rel="stylesheet" href="<?= View::e(View::asset('css/app.css')) ?>">
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
