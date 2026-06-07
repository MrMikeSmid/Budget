<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = []): void
    {
        $viewFile = dirname(__DIR__) . '/Views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View {$view} bestaat niet.");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        require dirname(__DIR__) . '/Views/layouts/app.php';
    }
}
