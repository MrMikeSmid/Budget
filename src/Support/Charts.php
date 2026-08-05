<?php

namespace App\Support;

/**
 * Genereert inline SVG-grafieken zonder externe library/CDN. Kleuren volgen
 * de vaste categorale volgorde uit de dataviz-richtlijn (var(--chart-1) t/m
 * var(--chart-8), gedefinieerd in app.css) — nooit losse hexcodes hier.
 */
final class Charts
{
    private const CHART_COLOR_VARS = [
        'var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)',
        'var(--chart-5)', 'var(--chart-6)', 'var(--chart-7)', 'var(--chart-8)',
    ];

    /**
     * Zelfde kleurvolgorde als de lijn-/donutdiagrammen hierboven, voor
     * andere plekken die per item een chart-kleur nodig hebben (bijv. de
     * horizontale categorie-balken op het dashboard).
     */
    public static function colorForIndex(int $index): string
    {
        return self::CHART_COLOR_VARS[$index % count(self::CHART_COLOR_VARS)];
    }

    /**
     * Lijndiagram met 1-3 series over dezelfde x-as-buckets.
     *
     * @param string[] $labels x-as labels
     * @param array<string, float[]> $series naam => waardes (zelfde lengte als $labels)
     */
    public static function lineChart(array $labels, array $series, int $width = 640, int $height = 260): string
    {
        $count = count($labels);
        if ($count === 0) {
            return '<p class="text-muted">Nog geen data om te tonen.</p>';
        }

        $paddingLeft = 56;
        $paddingRight = 82;
        $paddingTop = 16;
        $paddingBottom = 32;
        $plotWidth = $width - $paddingLeft - $paddingRight;
        $plotHeight = $height - $paddingTop - $paddingBottom;

        $allValues = [0.0];
        foreach ($series as $values) {
            foreach ($values as $v) {
                $allValues[] = (float) $v;
            }
        }
        $max = max($allValues);
        $min = min(0.0, min($allValues));
        $range = ($max - $min) ?: 1.0;

        $x = static function (int $i) use ($count, $plotWidth, $paddingLeft): float {
            if ($count === 1) {
                return $paddingLeft + $plotWidth / 2;
            }

            return $paddingLeft + ($i / ($count - 1)) * $plotWidth;
        };
        $y = static function (float $value) use ($min, $range, $plotHeight, $paddingTop): float {
            return $paddingTop + $plotHeight - (($value - $min) / $range) * $plotHeight;
        };

        $svg = '<svg class="chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-label="Lijndiagram">';

        // Gridlines (4 horizontale stappen) + y-as labels.
        $steps = 4;
        for ($s = 0; $s <= $steps; $s++) {
            $value = $min + ($range * $s / $steps);
            $gy = $y($value);
            $svg .= '<line x1="' . $paddingLeft . '" y1="' . round($gy, 1) . '" x2="' . ($width - $paddingRight) . '" y2="' . round($gy, 1) . '" class="chart-grid" />';
            $svg .= '<text x="' . ($paddingLeft - 8) . '" y="' . round($gy + 4, 1) . '" class="chart-axis-label" text-anchor="end">' . self::compactNumber($value) . '</text>';
        }

        // X-as labels (om de zoveel punten als er weinig ruimte is).
        $labelEvery = $count > 8 ? (int) ceil($count / 8) : 1;
        foreach ($labels as $i => $label) {
            if ($i % $labelEvery !== 0 && $i !== $count - 1) {
                continue;
            }
            $svg .= '<text x="' . round($x($i), 1) . '" y="' . ($height - 8) . '" class="chart-axis-label" text-anchor="middle">' . View::e(self::truncate((string) $label, 10)) . '</text>';
        }

        $colorIndex = 0;
        $endLabels = [];
        foreach ($series as $name => $values) {
            $color = self::CHART_COLOR_VARS[$colorIndex % count(self::CHART_COLOR_VARS)];
            $colorIndex++;

            $points = [];
            foreach ($values as $i => $v) {
                $points[] = round($x($i), 1) . ',' . round($y((float) $v), 1);
            }
            $svg .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />';

            foreach ($values as $i => $v) {
                $px = round($x($i), 1);
                $py = round($y((float) $v), 1);
                $svg .= '<circle cx="' . $px . '" cy="' . $py . '" r="4" fill="' . $color . '" stroke="var(--surface)" stroke-width="2"><title>' . View::e((string) $labels[$i]) . ' — ' . View::e($name) . ': ' . View::e(View::money((float) $v)) . '</title></circle>';
            }

            $lastIndex = count($values) - 1;
            if ($lastIndex >= 0) {
                $endLabels[] = [
                    'x' => round($x($lastIndex), 1) + 8,
                    'y' => round($y((float) $values[$lastIndex]), 1) + 4,
                    'text' => View::money((float) $values[$lastIndex]),
                ];
            }
        }

        // Eindlabels die te dicht bij elkaar staan uit elkaar duwen i.p.v. laten overlappen.
        $minGap = 14;
        usort($endLabels, static fn ($a, $b) => $a['y'] <=> $b['y']);
        for ($i = 1; $i < count($endLabels); $i++) {
            if ($endLabels[$i]['y'] - $endLabels[$i - 1]['y'] < $minGap) {
                $endLabels[$i]['y'] = $endLabels[$i - 1]['y'] + $minGap;
            }
        }
        foreach ($endLabels as $label) {
            $svg .= '<text x="' . $label['x'] . '" y="' . $label['y'] . '" class="chart-end-label">' . View::e($label['text']) . '</text>';
        }

        $svg .= '</svg>';

        // Legenda (altijd bij 2+ series).
        if (count($series) > 1) {
            $svg .= '<div class="chart-legend">';
            $colorIndex = 0;
            foreach (array_keys($series) as $name) {
                $color = self::CHART_COLOR_VARS[$colorIndex % count(self::CHART_COLOR_VARS)];
                $colorIndex++;
                $svg .= '<span class="chart-legend-item"><span class="chart-legend-swatch" style="background:' . $color . '"></span>' . View::e($name) . '</span>';
            }
            $svg .= '</div>';
        }

        return $svg;
    }

    /**
     * Donut-diagram (taartdiagram met gat) voor verdeling van een geheel.
     *
     * @param array<string, float> $slices naam => bedrag (alleen positieve waardes)
     */
    public static function donutChart(array $slices, int $size = 220, ?string $centerLabel = null): string
    {
        $slices = array_filter($slices, static fn ($v) => $v > 0);

        if (empty($slices)) {
            return '<p class="text-muted">Nog geen data om te tonen.</p>';
        }

        // Maximaal 8 slices; de rest vouwt samen tot "Overig".
        if (count($slices) > 8) {
            arsort($slices);
            $top = array_slice($slices, 0, 7, true);
            $rest = array_sum(array_slice($slices, 7, null, true));
            $top['Overig'] = $rest;
            $slices = $top;
        }

        $total = array_sum($slices);
        $cx = $size / 2;
        $cy = $size / 2;
        $outerR = $size / 2 - 4;
        $innerR = $outerR * 0.6;

        $svg = '<svg class="chart" viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="Taartdiagram">';

        $angle = 0.0;
        $colorIndex = 0;
        $legend = '<div class="chart-legend">';

        foreach ($slices as $name => $value) {
            $color = self::CHART_COLOR_VARS[$colorIndex % count(self::CHART_COLOR_VARS)];
            $colorIndex++;

            $fraction = $value / $total;
            $sweep = $fraction * 360;
            $startAngle = $angle;
            $endAngle = $angle + $sweep;
            $angle = $endAngle;

            $largeArc = $sweep > 180 ? 1 : 0;

            [$ox1, $oy1] = self::polarPoint($cx, $cy, $outerR, $startAngle);
            [$ox2, $oy2] = self::polarPoint($cx, $cy, $outerR, $endAngle);
            [$ix1, $iy1] = self::polarPoint($cx, $cy, $innerR, $endAngle);
            [$ix2, $iy2] = self::polarPoint($cx, $cy, $innerR, $startAngle);

            $path = "M {$ox1} {$oy1} A {$outerR} {$outerR} 0 {$largeArc} 1 {$ox2} {$oy2}"
                . " L {$ix1} {$iy1} A {$innerR} {$innerR} 0 {$largeArc} 0 {$ix2} {$iy2} Z";

            $percent = round($fraction * 100, 1);
            $svg .= '<path d="' . $path . '" fill="' . $color . '" stroke="var(--surface)" stroke-width="2">'
                . '<title>' . View::e((string) $name) . ': ' . View::e(View::money((float) $value)) . ' (' . $percent . '%)</title></path>';

            $legend .= '<span class="chart-legend-item"><span class="chart-legend-swatch" style="background:' . $color . '"></span>'
                . View::e((string) $name) . ' <span class="text-muted">' . View::money((float) $value) . ' · ' . $percent . '%</span></span>';
        }

        if ($centerLabel !== null) {
            $svg .= '<text x="' . $cx . '" y="' . ($cy - 4) . '" text-anchor="middle" class="chart-center-value">' . View::e(View::money($total)) . '</text>';
            $svg .= '<text x="' . $cx . '" y="' . ($cy + 16) . '" text-anchor="middle" class="chart-axis-label">' . View::e($centerLabel) . '</text>';
        }

        $svg .= '</svg>';
        $legend .= '</div>';

        return $svg . $legend;
    }

    private static function polarPoint(float $cx, float $cy, float $r, float $angleDeg): array
    {
        $angleRad = deg2rad($angleDeg - 90);

        return [round($cx + $r * cos($angleRad), 2), round($cy + $r * sin($angleRad), 2)];
    }

    private static function compactNumber(float $value): string
    {
        if (abs($value) >= 1000) {
            return number_format($value / 1000, 1, ',', '.') . 'k';
        }

        return number_format($value, 0, ',', '.');
    }

    private static function truncate(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }
}
