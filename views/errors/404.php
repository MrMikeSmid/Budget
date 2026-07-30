<?php

use App\Support\View;
?>
<div class="empty-state">
    <h2>Pagina niet gevonden</h2>
    <p>De pagina "<?= View::e($page) ?>" bestaat niet.</p>
    <a class="btn" href="<?= View::e(View::url('dashboard')) ?>">Terug naar overzicht</a>
</div>
