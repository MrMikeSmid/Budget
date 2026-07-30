<?php

use App\Support\View;
?>
<div class="card">
    <h2 class="mt-0">📅 Budgetperiodes</h2>
    <p class="text-muted">Maandperiodes beheren, beginstand instellen en de actieve periode kiezen.</p>
    <a class="btn secondary" href="<?= View::e(View::url('periods')) ?>">Openen</a>
</div>
<div class="card">
    <h2 class="mt-0">👤 Accounts</h2>
    <p class="text-muted">Accounts toevoegen of verwijderen. Iedereen heeft volledige rechten.</p>
    <a class="btn secondary" href="<?= View::e(View::url('accounts')) ?>">Openen</a>
</div>
