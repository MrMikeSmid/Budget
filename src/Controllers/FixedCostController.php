<?php

namespace App\Controllers;

use App\Models\FixedCost;

final class FixedCostController extends LineItemController
{
    protected static function model(): string
    {
        return FixedCost::class;
    }

    protected static function view(): string
    {
        return 'fixed-costs/index';
    }

    protected static function page(): string
    {
        return 'vaste-lasten';
    }

    protected static function label(): string
    {
        return 'Vaste last';
    }

    protected static function amountSign(): int
    {
        return -1;
    }
}
