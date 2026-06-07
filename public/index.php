<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\ListController;
use App\Controllers\PwaController;
use App\Controllers\SettingsController;
use App\Core\Request;
use App\Core\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new Router();
$router->get('/manifest.webmanifest', [PwaController::class, 'manifest']);
$router->get('/pwa-icon/{name}', [PwaController::class, 'icon']);
$router->get('/sw.js', [PwaController::class, 'serviceWorker']);
$router->get('/', [HomeController::class, 'index']);
$router->get('/login', [AuthController::class, 'show']);
$router->post('/login', [AuthController::class, 'identify']);
$router->post('/login/password', [AuthController::class, 'password']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->post('/lists', [ListController::class, 'create']);
$router->get('/lists/{id}', [ListController::class, 'show']);
$router->get('/lists/{id}/state', [ListController::class, 'state']);
$router->post('/lists/{id}/items', [ListController::class, 'addItem']);
$router->post('/lists/{listId}/items/{itemId}/toggle', [ListController::class, 'toggle']);
$router->post('/lists/{id}/share', [ListController::class, 'share']);
$router->post('/lists/{id}/delete', [ListController::class, 'delete']);
$router->get('/settings', [SettingsController::class, 'show']);
$router->post('/settings/profile', [SettingsController::class, 'profile']);
$router->get('/settings/profile-image', [SettingsController::class, 'profileImage']);
$router->post('/settings/password', [SettingsController::class, 'password']);
$router->dispatch(new Request());
