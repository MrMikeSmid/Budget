<?php

use App\Support\View;
?>
<div class="card">
    <h2 class="mt-0">📅 Budgetperiodes</h2>
    <p class="text-muted">Maandperiodes beheren en de actieve periode kiezen.</p>
    <a class="btn secondary" href="<?= View::e(View::url('periods')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">🏦 Leningen &amp; schulden</h2>
    <p class="text-muted">Totaalbedrag invullen; elke betaalde termijn wordt automatisch in mindering gebracht. Komt automatisch op de vaste lasten te staan.</p>
    <a class="btn secondary" href="<?= View::e(View::url('leningen')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">📊 Statistieken</h2>
    <p class="text-muted">Inkomsten, uitgaven en potjes per maand, kwartaal of jaar, met grafieken en een volledig totaaloverzicht.</p>
    <a class="btn secondary" href="<?= View::e(View::url('statistieken')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">👤 Accounts</h2>
    <p class="text-muted">Accounts toevoegen of verwijderen. Iedereen heeft volledige rechten.</p>
    <a class="btn secondary" href="<?= View::e(View::url('accounts')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">🕒 Activiteit</h2>
    <p class="text-muted">Tijdlijn van de laatste mutaties: wie wat heeft toegevoegd, gewijzigd of verwijderd.</p>
    <a class="btn secondary" href="<?= View::e(View::url('activiteit')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">🤖 AI-advies (Gemini)</h2>
    <p class="text-muted">Gemini API key en systeemprompt instellen voor het AI-advies op het dashboard.</p>
    <a class="btn secondary" href="<?= View::e(View::url('instellingen-ai')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">🚪 Uitloggen</h2>
    <p class="text-muted">Log uit van je account op dit apparaat.</p>
    <a class="btn secondary" href="<?= View::e(View::url('logout')) ?>">Uitloggen</a>
</div>
