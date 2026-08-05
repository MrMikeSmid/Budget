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

        if ($name === '') {
            View::flash('Vul een naam in.', 'error');
        } elseif ($id > 0) {
            Category::update($id, $name);
            Activity::log('categorieen', 'Categorie bijgewerkt: ' . $name);
            View::flash('Categorie opgeslagen.');
        } else {
            Category::create($name);
            Activity::log('categorieen', 'Categorie aangemaakt: ' . $name);
            View::flash('Categorie toegevoegd.');
        }

        header('Location: ' . View::url('categorieen'));
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
     * Alle inkomsten en lasten (incl. leningtermijnen) in deze categorie,
     * binnen de gekozen periode, met totalen — bereikt via de categorie-
     * badge op de lijsten van inkomsten/lasten/leningen.
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

        $incomeItems = $period ? IncomeItem::forCategoryInPeriod($id, (int) $period['id']) : [];
        $fixedCosts = $period ? FixedCost::forCategoryInPeriod($id, (int) $period['id']) : [];

        View::render('categories/show', [
            'category' => $category,
            'periods' => BudgetPeriod::all(),
            'period' => $period,
            'incomeItems' => $incomeItems,
            'fixedCosts' => $fixedCosts,
            'incomeBudgeted' => array_sum(array_column($incomeItems, 'budgeted')),
            'incomeActual' => array_sum(array_map(static fn ($i) => (float) ($i['actual'] ?? 0), $incomeItems)),
            'costsBudgeted' => array_sum(array_column($fixedCosts, 'budgeted')),
            'costsActual' => array_sum(array_map(static fn ($i) => (float) ($i['actual'] ?? 0), $fixedCosts)),
        ]);
    }
}
