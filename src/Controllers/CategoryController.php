<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\BudgetPeriod;
use App\Models\Category;
use App\Models\FixedCost;
use App\Models\IncomeItem;
use App\Support\View;

final class CategoryController
{
    public static function index(): void
    {
        $editId = isset($_GET['edit']) ? (int) $_GET['edit'] : null;

        View::render('categories/index', [
            'categories' => Category::all(),
            'editing' => $editId ? Category::find($editId) : null,
        ]);
    }

    public static function save(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $type = Category::normalizeType((string) ($_POST['type'] ?? 'uitgaven'));
        $returnTo = $_POST['return'] ?? '';
        $redirect = $returnTo === 'categorie' && $id > 0
            ? View::url('categorie', ['id' => $id])
            : View::url('categorieen');

        if ($name === '') {
            View::flash('Vul een naam in.', 'error');
        } elseif ($id > 0) {
            Category::update($id, $name, $type);
            Activity::log('categorieen', 'Categorie bijgewerkt: ' . $name);
            View::flash('Categorie opgeslagen.');
        } else {
            Category::create($name, $type);
            Activity::log('categorieen', 'Categorie aangemaakt: ' . $name);
            View::flash('Categorie toegevoegd.');
        }

        header('Location: ' . $redirect);
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $category = Category::find($id);
        Category::delete($id);
        if ($category) {
            Activity::log('categorieen', 'Categorie verwijderd: ' . $category['name']);
        }
        View::flash('Categorie verwijderd. Regels die deze categorie hadden, staan nu zonder categorie.');
        header('Location: ' . View::url('categorieen'));
        exit;
    }

    /**
     * Alle inkomsten óf lasten (incl. leningtermijnen) in deze categorie,
     * binnen de gekozen periode, met totalen — bereikt via de categorie-
     * badge op de lijsten van inkomsten/lasten/leningen. Welke van de twee
     * getoond wordt hangt af van het type van de categorie.
     */
    public static function show(): void
    {
        $id = (int) ($_GET['id'] ?? 0);
        $category = Category::find($id);

        if (!$category) {
            http_response_code(404);
            View::render('errors/404', ['page' => 'categorie']);
            return;
        }

        $period = BudgetPeriod::resolveFromRequest();
        $isIncome = $category['type'] === 'inkomsten';

        $incomeItems = [];
        $fixedCosts = [];
        $budgeted = 0.0;
        $actual = 0.0;
        $outstanding = 0.0;

        if ($period) {
            $periodId = (int) $period['id'];
            if ($isIncome) {
                $incomeItems = IncomeItem::forCategoryInPeriod($id, $periodId);
                $budgeted = array_sum(array_column($incomeItems, 'budgeted'));
                $actual = array_sum(array_map(static fn ($i) => (float) ($i['actual'] ?? 0), $incomeItems));
                $outstanding = IncomeItem::outstandingForCategory($id, $periodId);
            } else {
                $fixedCosts = FixedCost::forCategoryInPeriod($id, $periodId);
                $budgeted = array_sum(array_column($fixedCosts, 'budgeted'));
                $actual = array_sum(array_map(static fn ($i) => (float) ($i['actual'] ?? 0), $fixedCosts));
                $outstanding = FixedCost::outstandingForCategory($id, $periodId);
            }
        }

        View::render('categories/show', [
            'category' => $category,
            'isIncome' => $isIncome,
            'periods' => BudgetPeriod::all(),
            'period' => $period,
            'incomeItems' => $incomeItems,
            'fixedCosts' => $fixedCosts,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'outstanding' => $outstanding,
            'openForm' => !empty($_GET['open']),
        ]);
    }
}
