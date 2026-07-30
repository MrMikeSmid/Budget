<?php

namespace App\Controllers;

use App\Models\IncomeItem;

final class IncomeController extends LineItemController
{
    protected static function model(): string
    {
        return IncomeItem::class;
    }

    protected static function view(): string
    {
        return 'income/index';
    }

    protected static function page(): string
    {
        return 'inkomsten';
    }
}
