<?php

use App\Support\Csrf;
use App\Support\View;

/** @var array $users */
/** @var array $households */
/** @var array $mail */
/** @var string|null $appUrl */
/** @var string $dkimDomain */
/** @var string|null $dkimSelector */
/** @var string|null $dkimPublicKeyDns */
?>
<div class="card">
    <h2 class="mt-0">✉️ E-mail (SMTP)</h2>
    <p class="text-muted">Zolang "Host" leeg is, verstuurt de app geen mail — verificatie-/uitnodigingslinks worden dan gewoon op het scherm getoond om zelf te delen.</p>
    <form class="inline-form" method="post" action="<?= View::e(View::url('admin-instellingen-save')) ?>">
        <?= Csrf::field() ?>
        <div class="field">
            <label for="mail_host">Host</label>
            <input type="text" id="mail_host" name="mail_host" value="<?= View::e($mail['host'] ?? '') ?>" placeholder="smtp.voorbeeld.nl">
        </div>
        <div class="field">
            <label for="mail_port">Poort</label>
            <input type="number" id="mail_port" name="mail_port" value="<?= View::e((string) ($mail['port'] ?? 587)) ?>">
        </div>
        <div class="field">
            <label for="mail_encryption">Beveiliging</label>
            <select id="mail_encryption" name="mail_encryption">
                <?php foreach (['tls' => 'STARTTLS (aanbevolen)', 'ssl' => 'SSL (impliciet)', 'none' => 'Geen'] as $value => $label): ?>
                    <option value="<?= View::e($value) ?>" <?= ($mail['encryption'] ?? 'tls') === $value ? 'selected' : '' ?>><?= View::e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="mail_username">Gebruikersnaam</label>
            <input type="text" id="mail_username" name="mail_username" value="<?= View::e($mail['username'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="mail_password">Wachtwoord</label>
            <input type="password" id="mail_password" name="mail_password" placeholder="(ongewijzigd laten = leeg)" autocomplete="new-password">
        </div>
        <div class="field">
            <label for="mail_from_address">Afzender e-mailadres</label>
            <input type="email" id="mail_from_address" name="mail_from_address" value="<?= View::e($mail['from_address'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="mail_from_name">Afzendernaam</label>
            <input type="text" id="mail_from_name" name="mail_from_name" value="<?= View::e($mail['from_name'] ?? '') ?>">
        </div>
        <div class="field">
            <label for="app_url">Basis-URL van de site</label>
            <input type="url" id="app_url" name="app_url" value="<?= View::e($appUrl ?? '') ?>" placeholder="https://mikesmid.nl/budget">
        </div>
        <button type="submit" class="btn" formaction="<?= View::e(View::url('admin-instellingen-save')) ?>">Opslaan</button>
        <button type="submit" class="btn secondary" formaction="<?= View::e(View::url('admin-instellingen-test')) ?>">Testmail versturen</button>
    </form>
    <p class="text-muted">"Testmail versturen" slaat de ingevulde gegevens eerst op en stuurt daarna een testmail naar je eigen e-mailadres.</p>
</div>

<div class="card">
    <h2 class="mt-0">🔑 DKIM (tegen spam-plaatsing)</h2>
    <p class="text-muted">
        Mail die aankomt maar in spam belandt, komt bijna altijd door ontbrekende
        SPF/DKIM/DMARC-records op je domein — niet door de app. DKIM
        (een digitale handtekening per mail) kan de app zelf regelen; SPF en
        DMARC stel je in bij je hostingpaneel/DNS-provider.
    </p>
    <?php if ($dkimSelector && $dkimPublicKeyDns): ?>
        <p class="text-muted">DKIM is actief (selector "<?= View::e($dkimSelector) ?>"). Voeg deze TXT-record toe bij je DNS-beheer:</p>
        <div class="field">
            <label>Host / naam</label>
            <input type="text" readonly value="<?= View::e($dkimSelector . '._domainkey.' . $dkimDomain) ?>" onclick="this.select()">
        </div>
        <div class="field">
            <label>Waarde</label>
            <textarea readonly rows="4" style="width:100%; font-family:monospace; font-size:0.85em; word-break:break-all;" onclick="this.select()">v=DKIM1; k=rsa; p=<?= View::e($dkimPublicKeyDns) ?></textarea>
        </div>
        <p class="text-muted">Sommige DNS-panelen splitsen lange TXT-waarden automatisch in stukken van 255 tekens — dat is normaal en hoeft niet handmatig.</p>
        <form method="post" action="<?= View::e(View::url('admin-dkim-verwijderen')) ?>" onsubmit="return confirm('DKIM uitschakelen?');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn small danger">DKIM uitschakelen</button>
        </form>
    <?php else: ?>
        <?php if ($dkimDomain === ''): ?>
            <p class="text-muted">Vul eerst een afzender-e-mailadres in hierboven (bepaalt het domein voor DKIM).</p>
        <?php else: ?>
            <form method="post" action="<?= View::e(View::url('admin-dkim-genereren')) ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="btn">DKIM-sleutel genereren voor <?= View::e($dkimDomain) ?></button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="card">
    <h2 class="mt-0">👤 Gebruikers</h2>
    <div class="table-scroll">
        <table>
            <thead><tr><th class="nowrap">Naam</th><th>E-mail</th><th>Status</th><th>Admin</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="nowrap"><?= View::e($u['name']) ?></td>
                    <td><?= View::e($u['email']) ?></td>
                    <td><?= empty($u['email_verified_at']) ? 'Niet geverifieerd' : 'Geverifieerd' ?></td>
                    <td><?= !empty($u['is_admin']) ? 'Ja' : '' ?></td>
                    <td>
                        <?php if (empty($u['email_verified_at'])): ?>
                            <form method="post" action="<?= View::e(View::url('admin-verifieer-gebruiker')) ?>">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <button type="submit" class="btn small">Verifiëren</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2 class="mt-0">🏠 Huishoudens</h2>
    <div class="table-scroll">
        <table>
            <thead><tr><th class="nowrap">Naam</th><th>Leden</th><th class="nowrap">Aangemaakt</th></tr></thead>
            <tbody>
            <?php foreach ($households as $h): ?>
                <tr>
                    <td class="nowrap"><?= View::e($h['name']) ?></td>
                    <td><?= (int) $h['member_count'] ?></td>
                    <td class="nowrap"><?= View::e($h['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
