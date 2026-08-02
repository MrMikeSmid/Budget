<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Controllers\AccountController;
use App\Controllers\ActivityController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\FixedCostController;
use App\Controllers\IncomeController;
use App\Controllers\LoanController;
use App\Controllers\PeriodController;
use App\Controllers\PotController;
use App\Controllers\PotTransactionController;
use App\Controllers\StatisticsController;
use App\Controllers\TransactionController;
use App\Models\User;
use App\Support\Auth;
use App\Support\Config;
use App\Support\Router;
use App\Support\View;

$config = Config::get();

if (!empty($config['debug'])) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}

session_name($config['session_name'] ?? 'budgetapp_session');
session_start();

/**
 * Vereist een ingelogde gebruiker. Zolang er nog geen enkel account bestaat
 * wordt altijd naar de setup-pagina gestuurd.
 */
function authed(callable $handler): callable
{
    return static function () use ($handler) {
        if (User::count() === 0) {
            header('Location: ' . View::url('setup'));
            exit;
        }

        Auth::requireLogin();
        $handler();
    };
}

$router = new Router('dashboard');

// Gastroutes: alleen bereikbaar zonder account/sessie.
$router->get('setup', [AuthController::class, 'showSetup']);
$router->post('setup', [AuthController::class, 'setup']);
$router->get('login', [AuthController::class, 'showLogin']);
$router->post('login', [AuthController::class, 'login']);
$router->get('logout', [AuthController::class, 'logout']);

// Dashboard
$router->get('dashboard', authed([DashboardController::class, 'index']));

// Instellingen-hub (verwijst door naar periodes/accounts)
$router->get('instellingen', authed(static function () {
    View::render('settings/index');
}));

// Periodes
$router->get('periods', authed([PeriodController::class, 'index']));
$router->post('periods-save', authed([PeriodController::class, 'save']));
$router->post('periods-activate', authed([PeriodController::class, 'activate']));
$router->post('periods-delete', authed([PeriodController::class, 'delete']));

// Inkomsten
$router->get('inkomsten', authed([IncomeController::class, 'index']));
$router->post('inkomsten-save', authed([IncomeController::class, 'save']));
$router->post('inkomsten-delete', authed([IncomeController::class, 'delete']));

// Vaste lasten
$router->get('vaste-lasten', authed([FixedCostController::class, 'index']));
$router->post('vaste-lasten-save', authed([FixedCostController::class, 'save']));
$router->post('vaste-lasten-delete', authed([FixedCostController::class, 'delete']));

// Kasstroom
$router->get('kasstroom', authed([TransactionController::class, 'index']));
$router->post('kasstroom-save', authed([TransactionController::class, 'save']));
$router->post('kasstroom-delete', authed([TransactionController::class, 'delete']));

// Potjes
$router->get('potjes', authed([PotController::class, 'index']));
$router->post('potjes-save', authed([PotController::class, 'save']));
$router->post('potjes-delete', authed([PotController::class, 'delete']));

// Potje-detail: alleen bekijken van de ledger; mutaties worden bij
// kasstroom toegevoegd (Uitgave/Overboeken) en kunnen hier verwijderd worden.
$router->get('potje', authed([PotTransactionController::class, 'index']));
$router->post('potje-transactie-delete', authed([PotTransactionController::class, 'delete']));
$router->post('potje-overboeking-save', authed([PotTransactionController::class, 'transfer']));

// Leningen/schulden
$router->get('leningen', authed([LoanController::class, 'index']));
$router->post('leningen-save', authed([LoanController::class, 'save']));
$router->post('leningen-delete', authed([LoanController::class, 'delete']));

// Statistieken
$router->get('statistieken', authed([StatisticsController::class, 'index']));

// Activiteit (tijdlijn van alle mutaties)
$router->get('activiteit', authed([ActivityController::class, 'index']));

// Accounts
$router->get('accounts', authed([AccountController::class, 'index']));
$router->post('accounts-save', authed([AccountController::class, 'create']));
$router->post('accounts-delete', authed([AccountController::class, 'delete']));

$router->dispatch();
