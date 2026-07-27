<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Database;
use App\Core\View;

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
});

$config = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($config['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionLifetime = (int) $config['session_lifetime'];
    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    session_name($config['session_name']);
    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$GLOBALS['config'] = $config;
$GLOBALS['db'] = new Database($config['database']);

function db(): PDO { return $GLOBALS['db']->pdo(); }
function config(string $key, mixed $default = null): mixed { return $GLOBALS['config'][$key] ?? $default; }
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function view(string $name, array $data = [], string $layout = 'app'): void { View::render($name, $data, $layout); }
function current_user(): ?array { return Auth::user(); }
function csrf_token(): string {
    if (empty($_SESSION['_token'])) { $_SESSION['_token'] = bin2hex(random_bytes(32)); }
    return $_SESSION['_token'];
}
function base_path(): string {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $base = preg_replace('#/public/index\.php$#', '', $script);
    if ($base === $script) { $base = preg_replace('#/index\.php$#', '', $script); }
    return rtrim($base, '/');
}
function url(string $path = '/'): string { return base_path() . '/' . ltrim($path, '/'); }
function absolute_url(string $path = '/'): string {
    $configuredUrl = (string) config('app_url', '');
    if ($configuredUrl !== '') { return rtrim($configuredUrl, '/') . '/' . ltrim($path, '/'); }
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = preg_replace('/[^a-z0-9.\-:\[\]]/i', '', (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost'));
    return $scheme . '://' . ($host ?: 'localhost') . url($path);
}
function asset(string $path): string {
    $relativePath = ltrim($path, '/');
    $assetPath = dirname(__DIR__) . '/public/assets/' . $relativePath;
    $version = is_file($assetPath) ? (string) filemtime($assetPath) : '';
    $assetUrl = url('/public/assets/' . $relativePath);
    return $version !== '' ? $assetUrl . '?v=' . rawurlencode($version) : $assetUrl;
}
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function flash(string $type, string $message): void { $_SESSION['_flash'][$type] = $message; }
function pull_flashes(): array { $messages = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $messages; }
function format_date(?string $value): string {
    if (!$value) { return ''; }
    try { return (new DateTimeImmutable($value))->format('d-m-Y'); } catch (Exception) { return ''; }
}
function category_label(string $category): string {
    return ['personeel' => 'Personeel', 'park' => 'Park', 'gasten' => 'Gasten', 'taken' => 'Taken'][$category] ?? $category;
}
function item_type_label(string $type): string {
    return ['notitie' => 'Notitie', 'afspraak' => 'Afspraak', 'taak' => 'Taak'][$type] ?? $type;
}
function status_label(string $status): string {
    return ['open' => 'Open', 'in_uitvoering' => 'In uitvoering', 'afgerond' => 'Afgerond', 'gearchiveerd' => 'Gearchiveerd'][$status] ?? $status;
}
function absence_status_label(string $status): string {
    return ['lopend' => 'Lopend', 'hersteld' => 'Hersteld', 'langdurig' => 'Langdurig'][$status] ?? $status;
}
function review_type_label(string $type): string {
    return ['functioneringsgesprek' => 'Functioneringsgesprek', 'beoordelingsgesprek' => 'Beoordelingsgesprek', 'proefperiode' => 'Proefperiode', 'overig' => 'Overig'][$type] ?? $type;
}
function is_overdue(?string $dueDate, string $status): bool {
    return $dueDate !== null && $status !== 'afgerond' && $dueDate < date('Y-m-d');
}
function step_type_label(string $type): string {
    return $type === 'periodiek' ? 'Periodiek' : 'Eenmalig';
}
function recurrence_interval_label(?string $interval): string {
    return ['dagelijks' => 'Dagelijks', 'wekelijks' => 'Wekelijks', 'maandelijks' => 'Maandelijks'][$interval ?? ''] ?? '';
}
/** @return array{label: string, class: string} */
function playbook_step_state(array $step): array {
    if ($step['status'] === 'afgerond') {
        return ['label' => 'Afgerond', 'class' => 'badge--ok'];
    }
    $today = date('Y-m-d');
    if ($today < $step['start_date']) {
        return ['label' => 'Gepland', 'class' => 'badge--muted'];
    }
    if ($today <= $step['end_date']) {
        return ['label' => 'Actief', 'class' => 'badge--warn'];
    }
    return $step['type'] === 'periodiek'
        ? ['label' => 'Periode afgelopen', 'class' => 'badge--muted']
        : ['label' => 'Vervallen', 'class' => 'badge--danger'];
}
function playbook_step_schedule_label(array $step): string {
    $range = $step['start_date'] === $step['end_date']
        ? format_date($step['start_date'])
        : format_date($step['start_date']) . ' – ' . format_date($step['end_date']);
    if ($step['type'] === 'periodiek') {
        return recurrence_interval_label($step['recurrence_interval']) . ', ' . $range;
    }
    return $range;
}
/** Whether a step occurs on the given date (respecting its recurrence interval). */
function playbook_step_occurs_on(array $step, string $date): bool {
    if ($date < $step['start_date'] || $date > $step['end_date']) {
        return false;
    }
    if ($step['type'] !== 'periodiek') {
        return true;
    }
    $start = new DateTimeImmutable($step['start_date']);
    $current = new DateTimeImmutable($date);
    $diffDays = (int) $start->diff($current)->days;
    return match ($step['recurrence_interval'] ?? '') {
        'dagelijks' => true,
        'wekelijks' => $diffDays % 7 === 0,
        'maandelijks' => (int) $start->format('j') === (int) $current->format('j'),
        default => false,
    };
}
/**
 * The [start, end] window to render on the calendar strip, spanning every step's
 * start/end date but capped to a sane number of days so the page stays usable.
 * @param array[] $steps
 * @return array{0: ?string, 1: ?string}
 */
function playbook_calendar_range(array $steps, int $maxDays = 180): array {
    if (empty($steps)) {
        return [null, null];
    }
    $starts = array_column($steps, 'start_date');
    $ends = array_column($steps, 'end_date');
    sort($starts);
    sort($ends);
    $start = new DateTimeImmutable($starts[0]);
    $end = new DateTimeImmutable($ends[count($ends) - 1]);
    if ($start->diff($end)->days > $maxDays) {
        $end = $start->modify('+' . $maxDays . ' days');
    }
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}
