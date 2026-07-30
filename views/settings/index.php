<?php

use App\Support\View;
?>
<div class="card">
    <h2 class="mt-0">📅 Budgetperiodes</h2>
    <p class="text-muted">Maandperiodes beheren, beginstand instellen en de actieve periode kiezen.</p>
    <a class="btn secondary" href="<?= View::e(View::url('periods')) ?>">Openen</a>
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
