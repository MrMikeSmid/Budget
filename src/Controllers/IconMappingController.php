<?php

namespace App\Controllers;

use App\Models\IconMapping;
use App\Support\BrandIcons;
use App\Support\View;

final class IconMappingController
{
    public static function index(): void
    {
        View::render('icon-mappings/index', [
            'mappings' => IconMapping::all(),
            'icons' => BrandIcons::all(),
        ]);
    }

    public static function save(): void
    {
        $description = trim($_POST['description'] ?? '');
        $iconSlug = trim($_POST['icon_slug'] ?? '');

        if ($description === '' || !BrandIcons::exists($iconSlug)) {
            View::flash('Vul een omschrijving in en kies een icoon.', 'error');
        } else {
            IconMapping::upsert($description, $iconSlug);
            View::flash('Icoon gekoppeld aan "' . $description . '".');
        }

        header('Location: ' . View::url('iconen'));
        exit;
    }

    public static function delete(): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        IconMapping::delete($id);
        View::flash('Koppeling verwijderd.');

        header('Location: ' . View::url('iconen'));
        exit;
    }
}
