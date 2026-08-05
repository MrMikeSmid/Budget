<?php

namespace App\Support;

use App\Models\Settings;

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
     * Absolute link, nodig voor e-mails (verificatie/uitnodigingen) waar een
     * relatieve index.php?page=... link geen betekenis heeft. Gebruikt de
     * geconfigureerde 'app_url' als die gezet is; anders (lokaal draaien,
     * zonder config.php) afgeleid uit de huidige request. Vertrouw dat laatste
     * niet in productie: de Host-header is door de client te vervalsen, dus
     * zet 'app_url' expliciet in config.php op een publieke server.
     */
    public static function absoluteUrl(string $page, array $params = []): string
    {
        $configured = Settings::appUrl();
        $base = $configured !== null ? rtrim($configured, '/') : self::guessBaseUrl();

        return $base . '/' . self::url($page, $params);
    }

    private static function guessBaseUrl(): string
    {
        $isHttps = (($_SERVER['HTTPS'] ?? '') !== '') && $_SERVER['HTTPS'] !== 'off';
        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');

        return $scheme . '://' . $host . $dir;
    }

    /**
     * Line-art iconen (Feather/Lucide-stijl: uniforme stroke, geen vulling).
     * Vaste set, geen user input, dus veilig om als raw SVG te echoen.
     * Gebruikt voor zowel de navigatie als losse acties (bijv. de
     * snelkoppelingen op het dashboard).
     */
    public static function navIcon(string $key): string
    {
        $icons = [
            'dashboard' => '<path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9a1 1 0 0 0 1 1h3v-6h4v6h3a1 1 0 0 0 1-1v-9"/>',
            'kasstroom' => '<polyline points="3 12 8 12 10 6 14 18 16 12 21 12"/>',
            'inkomsten' => '<path d="M20 7H6a2 2 0 0 1 0-4h12v4"/><rect x="3" y="7" width="18" height="12" rx="2"/><circle cx="16" cy="13" r="1.2" fill="currentColor" stroke="none"/>',
            'vaste-lasten' => '<rect x="5" y="3" width="14" height="18" rx="2"/><line x1="8" y1="8" x2="16" y2="8"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="13" y2="16"/>',
            'potjes' => '<path d="M4.5 13.5c0-3.6 3.1-6 6.9-6 2 0 3.6.5 4.8 1.4.7-.6 1.7-.9 2.6-.7-.1.9-.5 1.7-1.1 2.2.6.9.9 1.9.9 3.1 0 3.4-3.2 6-7.2 6s-7-2.6-7-6Z"/><circle cx="9" cy="12.5" r=".9" fill="currentColor" stroke="none"/><path d="M9.5 19.5v1.5M15 19.3v1.5"/>',
            'instellingen' => '<line x1="4" y1="6" x2="20" y2="6"/><circle cx="14" cy="6" r="2"/><line x1="4" y1="12" x2="20" y2="12"/><circle cx="8" cy="12" r="2"/><line x1="4" y1="18" x2="20" y2="18"/><circle cx="16" cy="18" r="2"/>',
            'uitgave' => '<path d="M12 5v13"/><path d="m6 13 6 6 6-6"/>',
            'overboeking' => '<path d="M6 8h13l-3.5-3.5"/><path d="M18 16H5l3.5 3.5"/>',
        ];

        $inner = $icons[$key] ?? '<circle cx="12" cy="12" r="8"/>';

        return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
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
