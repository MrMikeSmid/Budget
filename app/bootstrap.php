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
    session_name($config['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
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
function view(string $name, array $data = []): void { View::render($name, $data); }
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
function asset(string $path): string {
    $relativePath = ltrim($path, '/');
    $assetPath = dirname(__DIR__) . '/public/assets/' . $relativePath;
    $version = is_file($assetPath) ? (string) filemtime($assetPath) : '';
    $assetUrl = url('/public/assets/' . $relativePath);
    return $version !== '' ? $assetUrl . '?v=' . rawurlencode($version) : $assetUrl;
}
function profile_image_url(array $user): ?string {
    if (empty($user['profile_image'])) { return null; }
    return url('/settings/profile-image') . '?v=' . rawurlencode((string) $user['profile_image']);
}
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function flash(string $type, string $message): void { $_SESSION['_flash'][$type] = $message; }
function pull_flashes(): array { $messages = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $messages; }
