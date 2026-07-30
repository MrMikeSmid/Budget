<?php

use App\Support\View;

$currentPage = $_GET['page'] ?? 'dashboard';
$flash = View::flash();
$navItems = [
    'dashboard' => ['icon' => '🏠', 'label' => 'Overzicht'],
    'kasstroom' => ['icon' => '💸', 'label' => 'Kasstroom'],
    'inkomsten' => ['icon' => '💰', 'label' => 'Inkomsten'],
    'vaste-lasten' => ['icon' => '📌', 'label' => 'Vaste lasten'],
    'potjes' => ['icon' => '🐷', 'label' => 'Potjes'],
    'instellingen' => ['icon' => '⚙️', 'label' => 'Meer'],
];
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
<div class="app">
    <div style="flex:1; display:flex; flex-direction:column;">
        <header class="topbar">
            <h1>Budgetapp</h1>
            <a class="logout-link" href="<?= View::e(View::url('logout')) ?>">Uitloggen</a>
        </header>
        <main class="content">
            <?php if ($flash): ?>
                <?php [$type, $message] = explode('|', $flash, 2); ?>
                <div class="flash <?= View::e($type) ?>"><?= View::e($message) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
    <nav class="bottom-nav">
        <?php foreach ($navItems as $page => $item): ?>
            <a href="<?= View::e(View::url($page)) ?>" class="<?= $currentPage === $page || ($page === 'instellingen' && in_array($currentPage, ['periods', 'accounts', 'instellingen'], true)) ? 'active' : '' ?>">
                <span class="icon"><?= $item['icon'] ?></span>
                <span><?= View::e($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
</body>
</html>
