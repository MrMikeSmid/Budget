<?php

namespace App\Support;

final class View
{
    /** @var array<string, mixed> */
    private static array $flash = [];

    public static function render(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        extract($data, EXTR_SKIP);

        $viewsDir = __DIR__ . '/../../views';

        $renderInner = function () use ($template, $data, $viewsDir) {
            extract($data, EXTR_SKIP);
            require $viewsDir . '/' . $template . '.php';
        };

        if ($layout === null) {
            $renderInner();
            return;
        }

        ob_start();
        $renderInner();
        $content = ob_get_clean();

        require $viewsDir . '/' . $layout . '.php';
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    public static function money(?float $value): string
    {
        if ($value === null) {
            return '-';
        }

        return '€ ' . number_format($value, 2, ',', '.');
    }

    /**
     * Bouwt een relatieve link naar een pagina binnen de app.
     * Relatief zodat de hele app zonder aanpassingen naar een andere map
     * verplaatst kan worden.
     */
    public static function url(string $page, array $params = []): string
    {
        $params = array_merge(['page' => $page], $params);

        return 'index.php?' . http_build_query($params);
    }

    public static function asset(string $path): string
    {
        return 'assets/' . ltrim($path, '/');
    }

    /**
     * Kleurcode voor een statustekst. Statussen zijn vrije tekst (net als in Excel),
     * dit is puur cosmetisch en valt terug op "neutral" voor onbekende waardes.
     */
    public static function badgeClass(string $status): string
    {
        $status = mb_strtolower($status);

        if (str_starts_with($status, 'betaald') || str_starts_with($status, 'ontvangen')) {
            return 'paid';
        }
        if (str_starts_with($status, 'open')) {
            return 'open';
        }
        if (str_starts_with($status, 'volgende') || str_starts_with($status, 'nog te')) {
            return 'next';
        }

        return 'neutral';
    }

    public static function flash(?string $message = null, string $type = 'success'): ?string
    {
        if ($message !== null) {
            $_SESSION['flash'] = ['message' => $message, 'type' => $type];
            return null;
        }

        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);

        return $flash ? $flash['type'] . '|' . $flash['message'] : null;
    }
}
