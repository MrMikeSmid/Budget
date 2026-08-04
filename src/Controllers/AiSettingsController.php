<?php

namespace App\Controllers;

use App\Models\Activity;
use App\Models\AiSettings;
use App\Support\View;

final class AiSettingsController
{
    public static function index(): void
    {
        $settings = AiSettings::get();

        View::render('settings/ai', [
            'hasApiKey' => !empty($settings['gemini_api_key']),
            'systemPrompt' => $settings['system_prompt'],
        ]);
    }

    public static function save(): void
    {
        $apiKey = trim($_POST['gemini_api_key'] ?? '');
        $systemPrompt = trim($_POST['system_prompt'] ?? '');

        AiSettings::save($apiKey !== '' ? $apiKey : null, $systemPrompt);
        // Nooit de key zelf loggen — alleen dát er iets gewijzigd is.
        Activity::log('instellingen', 'AI-instellingen (Gemini) bijgewerkt');
        View::flash('AI-instellingen opgeslagen.');

        header('Location: ' . View::url('instellingen-ai'));
        exit;
    }
}
