<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class PwaController extends Controller
{
    private const ICONS = [
        'favicon-32' => [32, false],
        'apple-touch-180' => [180, false],
        'app-192' => [192, false],
        'app-512' => [512, false],
        'maskable-512' => [512, true],
    ];

    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        echo json_encode([
            'id' => url('/'),
            'name' => 'Samen — gedeelde lijstjes',
            'short_name' => 'Samen',
            'description' => 'Maak lijstjes, deel ze en vink taken samen af.',
            'lang' => 'nl-NL',
            'dir' => 'ltr',
            'start_url' => url('/'),
            'scope' => url('/'),
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#faf8f5',
            'theme_color' => '#6d4aff',
            'categories' => ['productivity', 'lifestyle'],
            'icons' => [
                [
                    'src' => url('/pwa-icon/app-192'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => url('/pwa-icon/app-512'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => url('/pwa-icon/maskable-512'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function icon(string $name): void
    {
        if (!isset(self::ICONS[$name])) {
            http_response_code(404);
            return;
        }

        [$size, $maskable] = self::ICONS[$name];
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=31536000, immutable');
        echo $this->createPng($size, $maskable);
    }

    public function serviceWorker(): void
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Service-Worker-Allowed: ' . url('/'));
        readfile(dirname(__DIR__, 2) . '/public/sw.js');
    }

    private function createPng(int $size, bool $maskable): string
    {
        $center = $size / 2;
        $radius = (int) round($size * ($maskable ? 0.115 : 0.14));
        $offset = (int) round($size * ($maskable ? 0.12 : 0.145));
        $circles = [
            [$center - $offset, $center + ($offset * 0.28), [255, 143, 121]],
            [$center, $center - ($offset * 0.45), [250, 248, 245]],
            [$center + $offset, $center + ($offset * 0.32), [245, 199, 85]],
        ];
        $raw = '';

        for ($y = 0; $y < $size; $y++) {
            $raw .= "\0";
            for ($x = 0; $x < $size; $x++) {
                $color = [109, 74, 255];
                foreach ($circles as [$circleX, $circleY, $circleColor]) {
                    if ((($x - $circleX) ** 2) + (($y - $circleY) ** 2) <= $radius ** 2) {
                        $color = $circleColor;
                    }
                }
                $raw .= pack('C3', ...$color);
            }
        }

        $signature = "\x89PNG\r\n\x1a\n";
        $header = pack('NNCCCCC', $size, $size, 8, 2, 0, 0, 0);
        return $signature
            . $this->pngChunk('IHDR', $header)
            . $this->pngChunk('IDAT', gzcompress($raw, 9))
            . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data))
            . $type
            . $data
            . pack('H*', hash('crc32b', $type . $data));
    }
}
