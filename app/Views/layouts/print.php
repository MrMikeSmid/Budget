<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? config('name')) ?> · <?= e(config('name')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="print-page">
    <div class="print-actions">
        <button type="button" class="button button--primary" onclick="window.print()">Print / bewaar als PDF</button>
    </div>
    <?= $content ?>
</div>
</body>
</html>
