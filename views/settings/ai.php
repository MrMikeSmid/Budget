<?php

use App\Support\Csrf;
use App\Support\View;

/** @var bool $hasApiKey */
/** @var string $systemPrompt */
/** @var string $model */
?>
<p><a href="<?= View::e(View::url('instellingen')) ?>">&larr; Instellingen</a></p>

<div class="card">
    <h2 class="mt-0">🤖 AI-advies (Gemini)</h2>
    <p class="text-muted">Het dashboard laat Gemini een persoonlijk financieel advies genereren. Daarvoor is een eigen API key nodig — die haal je op via <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">Google AI Studio</a>. De key wordt alleen op de server gebruikt en nooit teruggetoond.</p>

    <form method="post" action="<?= View::e(View::url('instellingen-ai-save')) ?>">
        <?= Csrf::field() ?>
        <div class="field">
            <label for="gemini_api_key">Gemini API key</label>
            <div class="field-row" style="align-items:flex-end;">
                <div class="field" style="flex:1; margin-bottom:0;">
                    <input type="password" id="gemini_api_key" name="gemini_api_key" autocomplete="off"
                        placeholder="<?= $hasApiKey ? 'Er is al een key opgeslagen — laat leeg om te behouden' : 'Plak hier je API key' ?>">
                </div>
                <button type="button" class="btn small secondary" data-toggle-password="gemini_api_key">Tonen</button>
            </div>
            <?php if ($hasApiKey): ?>
                <p class="text-muted" style="font-size:12px; margin:4px 0 0;">Er staat al een key opgeslagen. Vul alleen iets in als je 'm wilt vervangen.</p>
            <?php endif; ?>
        </div>
        <div class="field">
            <label for="gemini_model">Model</label>
            <input type="text" id="gemini_model" name="gemini_model" value="<?= View::e($model) ?>">
            <p class="text-muted" style="font-size:12px; margin:4px 0 0;">Google hernoemt modelnamen wel eens — krijg je een "HTTP 404"-foutmelding bij het advies, dan is dit waarschijnlijk de oorzaak. Kijk op <a href="https://ai.google.dev/gemini-api/docs/models" target="_blank" rel="noopener">de modellenlijst</a> voor een geldige naam (bijv. "gemini-2.5-flash" of "gemini-flash-latest").</p>
        </div>
        <div class="field">
            <label for="system_prompt">Systeemprompt</label>
            <textarea id="system_prompt" name="system_prompt" rows="6"><?= View::e($systemPrompt) ?></textarea>
        </div>
        <button type="submit" class="btn">Opslaan</button>
    </form>
</div>
