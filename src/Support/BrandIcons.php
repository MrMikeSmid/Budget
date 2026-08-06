<?php

namespace App\Support;

final class BrandIcons
{
    /**
     * Nederlandse merken, los toegevoegd (Simple Icons dekt vrijwel geen
     * Nederlandse merken) via Wikimedia Commons (per bestand het
     * licentietype geverifieerd — zie assets/icons/brands/nl-CREDITS.md
     * voor bronvermelding per icoon) of de officiële merkpagina van het
     * bedrijf zelf (Riverty).
     *
     * @var array<string, array{title: string, hex: string}>
     */
    private const ICONS = [
        'nl-kpn' => ['title' => 'KPN', 'hex' => '00C300'],
        'nl-essent' => ['title' => 'Essent', 'hex' => 'E50867'],
        'nl-asr' => ['title' => 'a.s.r.', 'hex' => '4AB286'],
        'nl-abnamro' => ['title' => 'ABN AMRO', 'hex' => '00A296'],
        'nl-knab' => ['title' => 'Knab', 'hex' => '00B2A9'],
        'nl-simpel' => ['title' => 'Simpel', 'hex' => 'E6007E'],
        'nl-videoland' => ['title' => 'Videoland', 'hex' => 'ED1C24'],
        'nl-basicfit' => ['title' => 'Basic-Fit', 'hex' => 'F36F21'],
        'nl-ing' => ['title' => 'ING', 'hex' => 'FF6200'],
        'nl-riverty' => ['title' => 'Riverty', 'hex' => '527A42'],
    ];

    /** @var array<string> Iconen die als PNG i.p.v. SVG zijn opgeslagen. */
    private const PNG_ICONS = ['nl-simpel', 'nl-videoland', 'nl-basicfit', 'nl-ing'];

    public static function all(): array
    {
        return self::ICONS;
    }

    public static function exists(string $slug): bool
    {
        return isset(self::ICONS[$slug]);
    }

    public static function title(string $slug): ?string
    {
        return self::ICONS[$slug]['title'] ?? null;
    }

    public static function extension(string $slug): string
    {
        return in_array($slug, self::PNG_ICONS, true) ? 'png' : 'svg';
    }
}
