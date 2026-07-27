<?php

declare(strict_types=1);

use App\Controllers\AbsenceController;
use App\Controllers\AuthController;
use App\Controllers\DepartmentController;
use App\Controllers\HomeController;
use App\Controllers\ItemController;
use App\Controllers\ParkController;
use App\Controllers\PersonController;
use App\Controllers\PlaybookController;
use App\Controllers\PlaybookShareController;
use App\Controllers\PlaybookStepController;
use App\Controllers\PrintController;
use App\Controllers\PwaController;
use App\Controllers\ReviewController;
use App\Core\Request;
use App\Core\Router;

require dirname(__DIR__) . '/app/bootstrap.php';

$router = new Router();

$router->get('/setup', [AuthController::class, 'showSetup']);
$router->post('/setup', [AuthController::class, 'setup']);
$router->get('/login', [AuthController::class, 'show']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/', [HomeController::class, 'index']);

$router->get('/parken', [ParkController::class, 'index']);
$router->get('/parken/nieuw', [ParkController::class, 'create']);
$router->post('/parken', [ParkController::class, 'store']);
$router->get('/parken/{id}', [ParkController::class, 'show']);
$router->get('/parken/{id}/bewerken', [ParkController::class, 'edit']);
$router->post('/parken/{id}/update', [ParkController::class, 'update']);
$router->post('/parken/{id}/delete', [ParkController::class, 'delete']);

$router->get('/personen', [PersonController::class, 'index']);
$router->get('/personen/nieuw', [PersonController::class, 'create']);
$router->post('/personen', [PersonController::class, 'store']);
$router->get('/personen/{id}', [PersonController::class, 'show']);
$router->get('/personen/{id}/bewerken', [PersonController::class, 'edit']);
$router->post('/personen/{id}/update', [PersonController::class, 'update']);
$router->post('/personen/{id}/delete', [PersonController::class, 'delete']);

$router->get('/items', [ItemController::class, 'index']);
$router->get('/parken/{parkId}/items/nieuw', [ItemController::class, 'create']);
$router->post('/parken/{parkId}/items', [ItemController::class, 'store']);
$router->get('/items/{id}/bewerken', [ItemController::class, 'edit']);
$router->post('/items/{id}/update', [ItemController::class, 'update']);
$router->post('/items/{id}/toggle', [ItemController::class, 'toggle']);
$router->post('/items/{id}/delete', [ItemController::class, 'delete']);

$router->post('/personen/{personId}/verzuim', [AbsenceController::class, 'store']);
$router->post('/verzuim/{id}/update', [AbsenceController::class, 'update']);
$router->post('/verzuim/{id}/delete', [AbsenceController::class, 'delete']);

$router->post('/personen/{personId}/gesprekken', [ReviewController::class, 'store']);
$router->post('/gesprekken/{id}/update', [ReviewController::class, 'update']);
$router->post('/gesprekken/{id}/delete', [ReviewController::class, 'delete']);

$router->get('/personen/{id}/print', [PrintController::class, 'person']);
$router->get('/parken/{id}/print', [PrintController::class, 'park']);

$router->get('/afdelingen', [DepartmentController::class, 'index']);
$router->get('/afdelingen/nieuw', [DepartmentController::class, 'create']);
$router->post('/afdelingen', [DepartmentController::class, 'store']);
$router->get('/afdelingen/{id}/bewerken', [DepartmentController::class, 'edit']);
$router->post('/afdelingen/{id}/update', [DepartmentController::class, 'update']);
$router->post('/afdelingen/{id}/delete', [DepartmentController::class, 'delete']);

$router->get('/draaiboeken', [PlaybookController::class, 'index']);
$router->get('/draaiboeken/nieuw', [PlaybookController::class, 'create']);
$router->post('/draaiboeken', [PlaybookController::class, 'store']);
$router->get('/draaiboeken/{id}', [PlaybookController::class, 'show']);
$router->get('/draaiboeken/{id}/bewerken', [PlaybookController::class, 'edit']);
$router->post('/draaiboeken/{id}/update', [PlaybookController::class, 'update']);
$router->post('/draaiboeken/{id}/delete', [PlaybookController::class, 'delete']);
$router->post('/draaiboeken/{id}/vernieuw-link', [PlaybookController::class, 'regenerateToken']);

$router->post('/draaiboeken/{playbookId}/stappen', [PlaybookStepController::class, 'store']);
$router->post('/stappen/{id}/update', [PlaybookStepController::class, 'update']);
$router->post('/stappen/{id}/toggle', [PlaybookStepController::class, 'toggle']);
$router->post('/stappen/{id}/delete', [PlaybookStepController::class, 'delete']);

$router->get('/gedeeld/{token}', [PlaybookShareController::class, 'show']);

$router->get('/manifest.webmanifest', [PwaController::class, 'manifest']);
$router->get('/pwa-icon/{name}', [PwaController::class, 'icon']);
$router->get('/sw.js', [PwaController::class, 'serviceWorker']);

$router->dispatch(new Request());
