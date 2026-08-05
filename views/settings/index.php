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
    <h2 class="mt-0">🏷️ Categorieën</h2>
    <p class="text-muted">Categorieën beheren voor inkomsten, lasten en leningen, zodat je kunt zien hoeveel er per categorie in/uit gaat.</p>
    <a class="btn secondary" href="<?= View::e(View::url('categorieen')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">📊 Statistieken</h2>
    <p class="text-muted">Inkomsten, uitgaven en potjes per maand, kwartaal of jaar, met grafieken en een volledig totaaloverzicht.</p>
    <a class="btn secondary" href="<?= View::e(View::url('statistieken')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">👤 Huishouden</h2>
    <p class="text-muted">Leden uitnodigen of verwijderen. Iedereen in het huishouden heeft volledige rechten.</p>
    <a class="btn secondary" href="<?= View::e(View::url('huishouden')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">🕒 Activiteit</h2>
    <p class="text-muted">Tijdlijn van de laatste mutaties: wie wat heeft toegevoegd, gewijzigd of verwijderd.</p>
    <a class="btn secondary" href="<?= View::e(View::url('activiteit')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">🚪 Uitloggen</h2>
    <p class="text-muted">Log uit van je account op dit apparaat.</p>
    <a class="btn secondary" href="<?= View::e(View::url('logout')) ?>">Uitloggen</a>
</div>
