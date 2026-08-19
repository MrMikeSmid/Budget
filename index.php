<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use App\Controllers\ActivityController;
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\CategoryController;
use App\Controllers\DashboardController;
use App\Controllers\FixedCostController;
use App\Controllers\HouseholdController;
use App\Controllers\IconMappingController;
use App\Controllers\IncomeController;
use App\Controllers\InviteController;
use App\Controllers\LoanController;
use App\Controllers\PeriodCloseController;
use App\Controllers\PeriodController;
use App\Controllers\PotController;
use App\Controllers\PotTransactionController;
use App\Controllers\RegisterController;
use App\Controllers\StatisticsController;
use App\Controllers\TransactionController;
use App\Controllers\VerifyController;
use App\Controllers\WarningController;
use App\Models\BudgetPeriod;
use App\Models\HouseholdMember;
use App\Support\Auth;
use App\Support\AppDatabase;
use App\Support\Config;
use App\Support\Database;
use App\Support\IconMappingGlobalizer;
use App\Support\LegacyImporter;
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
 * Vangt elke onverwachte fout tijdens het opstarten of afhandelen van een
 * request af: logt de volledige details naar storage/error.log (via FTP te
 * downloaden, storage/ is al beschermd tegen directe webtoegang) én naar de
 * PHP-errorlog van de server, en toont bezoekers een nette foutpagina i.p.v.
 * een kale 500. Geeft bewust geen technische details aan de bezoeker — de
 * site is dan sowieso voor iedereen stuk, dus dat is geen plek om details te
 * lekken.
 */
function renderFatalError(\Throwable $e, array $config): void
{
    http_response_code(500);

    $logLine = sprintf(
        "[%s] %s: %s in %s:%d\n%s\n\n",
        date('c'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log(trim($logLine));

    $storageDir = $config['storage_dir'] ?? null;
    if ($storageDir && is_dir($storageDir)) {
        @file_put_contents($storageDir . '/error.log', $logLine, FILE_APPEND | LOCK_EX);
    }

    echo '<!doctype html><html lang="nl"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Budgetapp</title></head>'
        . '<body style="font-family: sans-serif; max-width: 32rem; margin: 4rem auto; padding: 0 1rem; color: #1e293b;">'
        . '<h1>Tijdelijk niet beschikbaar</h1>'
        . '<p>Er ging iets mis. We zijn ervan op de hoogte en lossen het zo snel mogelijk op — probeer het over een paar minuten opnieuw.</p>'
        . '</body></html>';
}

try {
    // Zet de centrale database (gebruikers/huishoudens/uitnodigingen) klaar
    // en zet, als dit de eerste request na de upgrade naar meerdere
    // huishoudens is, de bestaande (single-tenant) database eenmalig om naar
    // huishouden #1 — zie LegacyImporter voor waarom dit hier moet gebeuren
    // i.p.v. via een handmatig servercommando.
    AppDatabase::connection();
    LegacyImporter::runIfNeeded();
    // Iconenkoppelingen zijn app-breed geworden (voorheen per huishouden) —
    // zet bestaande koppelingen eenmalig over naar de centrale database.
    IconMappingGlobalizer::runIfNeeded();
} catch (\Throwable $e) {
    renderFatalError($e, $config);
    exit;
}

/**
 * Vereist een ingelogde gebruiker MET een geldig huishouden, en zet de
 * domein-database (Database::connection()) op dat huishouden vóór de
 * handler draait. De sessie-hint voor "welk huishouden" wordt elke request
 * opnieuw tegen de actuele lidmaatschappen gevalideerd — nooit blind
 * vertrouwd — zodat iemand die net verwijderd is uit een huishouden daar
 * niet stiekem toegang toe blijft houden.
 */
function authed(callable $handler): callable
{
    return static function () use ($handler) {
        Auth::requireLogin();

        $user = Auth::user();
        $memberships = HouseholdMember::householdsFor((int) $user['id']);

        if (empty($memberships)) {
            View::render('errors/no-household', [], 'layout-guest');
            return;
        }

        $preferredId = $_SESSION['household_id'] ?? null;
        $match = array_values(array_filter(
            $memberships,
            static fn (array $h): bool => (int) $h['id'] === (int) $preferredId
        ));
        $active = $match[0] ?? $memberships[0];
        $_SESSION['household_id'] = (int) $active['id'];

        $storageDir = Config::get()['storage_dir'];
        Database::useHouseholdDb($storageDir . '/' . $active['db_path']);

        $handler();
    };
}

/**
 * App-breed beheerdersrecht (los van huishoudens — een admin hoeft geen lid
 * van een huishouden te zijn om bijv. SMTP-instellingen te beheren), dus
 * geen huishouden-resolutie zoals bij authed().
 */
function adminOnly(callable $handler): callable
{
    return static function () use ($handler) {
        Auth::requireLogin();

        if (!Auth::isAdmin()) {
            http_response_code(403);
            View::render('errors/403', [], 'layout-guest');
            return;
        }

        $handler();
    };
}

$router = new Router('dashboard');

// Gastroutes: alleen bereikbaar zonder sessie (of maken er geen gebruik van).
$router->get('registreren', [RegisterController::class, 'showRegister']);
$router->post('registreren', [RegisterController::class, 'register']);
$router->get('verifieer-email', [VerifyController::class, 'verify']);
$router->get('login', [AuthController::class, 'showLogin']);
$router->post('login', [AuthController::class, 'login']);
$router->get('logout', [AuthController::class, 'logout']);

// Uitnodiging accepteren: bereikbaar zonder ingelogd te zijn (de link komt
// per mail/gedeelde link binnen), beslist zelf of dat een login- of
// registratieformulier oplevert.
$router->get('uitnodiging', [InviteController::class, 'showAccept']);
$router->post('uitnodiging-inloggen', [InviteController::class, 'acceptViaLogin']);
$router->post('uitnodiging-registreren', [InviteController::class, 'acceptViaRegister']);

// Dashboard
$router->get('dashboard', authed([DashboardController::class, 'index']));

// Instellingen-hub (verwijst door naar periodes/huishouden)
$router->get('instellingen', authed(static function () {
    View::render('settings/index', [
        'periods' => BudgetPeriod::all(),
        'period' => BudgetPeriod::resolveFromRequest(),
    ]);
}));

// Wegklikken van een "meer betaald/ontvangen dan begroot"-waarschuwing.
$router->post('waarschuwing-dismiss', authed([WarningController::class, 'dismiss']));

// Categorieën (gedeeld tussen inkomsten, lasten en leningen)
$router->get('categorieen', authed([CategoryController::class, 'index']));
$router->post('categorieen-save', authed([CategoryController::class, 'save']));
$router->post('categorieen-delete', authed([CategoryController::class, 'delete']));
$router->get('categorie', authed([CategoryController::class, 'show']));

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

// Periode afsluiten: openstaande lasten/saldo optioneel meenemen naar een
// andere periode (potjes lopen altijd vanzelf door).
$router->get('periode-afsluiten', authed([PeriodCloseController::class, 'confirm']));
$router->post('periode-afsluiten-uitvoeren', authed([PeriodCloseController::class, 'execute']));

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

// Huishouden: leden, uitnodigen, hernoemen, wisselen
$router->get('huishouden', authed([HouseholdController::class, 'index']));
$router->post('huishouden-uitnodigen', authed([InviteController::class, 'send']));
$router->post('huishouden-verwijderen', authed([HouseholdController::class, 'removeMember']));
$router->post('huishouden-hernoemen', authed([HouseholdController::class, 'rename']));
$router->post('huishouden-wisselen', authed([HouseholdController::class, 'switchHousehold']));

// Admin: app-breed beheer (SMTP-instellingen, gebruikers handmatig
// verifiëren, overzicht van huishoudens) — alleen voor is_admin-accounts.
$router->get('admin', adminOnly([AdminController::class, 'index']));
$router->post('admin-instellingen-save', adminOnly([AdminController::class, 'saveSettings']));
$router->post('admin-instellingen-test', adminOnly([AdminController::class, 'testSettings']));
$router->post('admin-dkim-genereren', adminOnly([AdminController::class, 'generateDkim']));
$router->post('admin-dkim-verwijderen', adminOnly([AdminController::class, 'removeDkim']));
$router->post('admin-verifieer-gebruiker', adminOnly([AdminController::class, 'verifyUser']));

// Iconen: omschrijving aan een zelf-geüploade afbeelding koppelen. App-breed
// (niet per huishouden) en dus alleen door een admin te beheren; elk
// huishouden ziet wel gewoon de resulterende iconen op hun eigen lasten/
// inkomsten (icoon-afbeelding is bewust geen adminOnly()).
$router->get('iconen', adminOnly([IconMappingController::class, 'index']));
$router->post('iconen-save', adminOnly([IconMappingController::class, 'save']));
$router->post('iconen-delete', adminOnly([IconMappingController::class, 'delete']));
$router->get('icoon-afbeelding', authed([IconMappingController::class, 'image']));

try {
    $router->dispatch();
} catch (\Throwable $e) {
    renderFatalError($e, $config);
    exit;
}
