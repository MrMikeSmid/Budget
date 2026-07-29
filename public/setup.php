<?php

declare(strict_types=1);

session_start();

$configPath = dirname(__DIR__) . '/config/config.php';
$hasConfig = is_file($configPath);

/** @return array<string, mixed> */
function load_existing_config(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $data = require $path;
    return is_array($data) ? $data : [];
}

$existing = load_existing_config($configPath);

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function render_page(string $title, string $body): never
{
    echo "<!doctype html><html lang=\"nl\"><head><meta charset=\"utf-8\">" .
        "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">" .
        '<meta name="robots" content="noindex, nofollow">' .
        '<title>' . h($title) . '</title><style>' . setup_css() . '</style></head><body>' .
        '<main>' . $body . '</main></body></html>';
    exit;
}

function setup_css(): string
{
    return <<<CSS
        :root { color-scheme: light dark; }
        body { font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 2rem 1rem; background: #f4f5f7; }
        @media (prefers-color-scheme: dark) { body { background: #1a1b1e; color: #e8e8e8; } }
        main { max-width: 640px; margin: 0 auto; }
        .card { background: #fff; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 1.25rem; }
        @media (prefers-color-scheme: dark) { .card { background: #26272b; } }
        h1 { font-size: 1.35rem; margin: 0 0 .25rem; }
        h2 { font-size: 1.05rem; margin: 1.5rem 0 .5rem; }
        p.lead { color: #666; margin-top: 0; }
        @media (prefers-color-scheme: dark) { p.lead { color: #aaa; } }
        label { display: block; font-size: .85rem; font-weight: 600; margin: .9rem 0 .3rem; }
        input[type=text], input[type=password], input[type=number], select {
            width: 100%; box-sizing: border-box; padding: .55rem .65rem; border-radius: 6px;
            border: 1px solid #ccc; font-size: .95rem;
        }
        @media (prefers-color-scheme: dark) { input { background: #1a1b1e; color: #eee; border-color: #444; } }
        .row { display: flex; gap: .75rem; }
        .row > div { flex: 1; }
        .checkbox { display: flex; align-items: center; gap: .5rem; margin-top: .9rem; }
        .checkbox label { margin: 0; }
        .hint { font-size: .8rem; color: #888; margin-top: .25rem; }
        button {
            margin-top: 1.5rem; padding: .65rem 1.2rem; border-radius: 6px; border: none;
            background: #2563eb; color: #fff; font-size: .95rem; font-weight: 600; cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
        .warn { background: #fff4e5; border: 1px solid #f0b955; color: #7a4b00; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem; font-size: .9rem; }
        @media (prefers-color-scheme: dark) { .warn { background: #3a2f14; border-color: #7a5c1e; color: #f0c46a; } }
        .error { background: #fde8e8; border: 1px solid #e08a8a; color: #8a1f1f; border-radius: 8px; padding: .85rem 1rem; margin-bottom: 1rem; font-size: .9rem; }
        @media (prefers-color-scheme: dark) { .error { background: #3a1f1f; border-color: #7a3a3a; color: #f5a8a8; } }
        .success { background: #e7f7ec; border: 1px solid #8fd19e; color: #1e6b34; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem; }
        @media (prefers-color-scheme: dark) { .success { background: #17301f; border-color: #2f6b42; color: #a3e0b4; } }
        code, .token { font-family: ui-monospace, monospace; background: #eee; padding: .15rem .4rem; border-radius: 4px; word-break: break-all; }
        @media (prefers-color-scheme: dark) { code, .token { background: #1a1b1e; } }
        a { color: #2563eb; }
        CSS;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valid(): bool
{
    $token = $_POST['csrf'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}

// --- Toegangscontrole: als er al een config staat, moet je het huidige
// bearer token invoeren voordat je iets mag wijzigen. Zonder config (eerste
// keer) is er nog niets te beschermen, dus dan mag je direct het formulier
// invullen. ---
if ($hasConfig && empty($_SESSION['setup_ok'])) {
    $error = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gate_token'])) {
        if (!csrf_valid()) {
            $error = 'Ongeldig formulier (csrf), probeer opnieuw.';
        } elseif (hash_equals((string) ($existing['MCP_BEARER_TOKEN'] ?? ''), (string) $_POST['gate_token'])) {
            $_SESSION['setup_ok'] = true;
            header('Location: setup.php');
            exit;
        } else {
            $error = 'Ongeldig bearer token.';
        }
    }

    render_page('Configuratie ontgrendelen', '
        <div class="card">
            <h1>Er staat al een configuratie</h1>
            <p class="lead">Vul het huidige <strong>MCP_BEARER_TOKEN</strong> in om de instellingen te bekijken/wijzigen.</p>
            ' . ($error ? '<div class="error">' . h($error) . '</div>' : '') . '
            <form method="post">
                <input type="hidden" name="csrf" value="' . h(csrf_token()) . '">
                <label for="gate_token">Bearer token</label>
                <input type="password" id="gate_token" name="gate_token" autofocus required>
                <button type="submit">Ontgrendelen</button>
            </form>
        </div>
    ');
}

// --- Opslaan ---
$saved = false;
$formErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imapHost'])) {
    if (!csrf_valid()) {
        $formErrors[] = 'Ongeldig formulier (csrf), herlaad de pagina en probeer opnieuw.';
    }

    $imapHost = trim((string) ($_POST['imapHost'] ?? ''));
    $mailProtocol = strtolower(trim((string) ($_POST['mailProtocol'] ?? 'imap')));
    $imapUser = trim((string) ($_POST['imapUser'] ?? ''));
    $imapPassword = (string) ($_POST['imapPassword'] ?? '');
    $smtpHost = trim((string) ($_POST['smtpHost'] ?? ''));
    $bearerToken = trim((string) ($_POST['bearerToken'] ?? ''));

    if (!in_array($mailProtocol, ['imap', 'pop3'], true)) {
        $formErrors[] = 'Kies IMAP of POP3 als protocol voor inkomende mail.';
    }
    if ($imapHost === '') {
        $formErrors[] = 'Host voor inkomende mail is verplicht.';
    }
    if ($imapUser === '') {
        $formErrors[] = 'Gebruikersnaam voor inkomende mail is verplicht.';
    }
    if ($imapPassword === '' && !$hasConfig) {
        $formErrors[] = 'Wachtwoord voor inkomende mail is verplicht.';
    }
    if ($smtpHost === '') {
        $formErrors[] = 'SMTP-host is verplicht.';
    }
    if ($bearerToken === '' && !$hasConfig) {
        $formErrors[] = 'Bearer token is verplicht.';
    }

    if ($formErrors === []) {
        $newConfig = $existing;
        $newConfig['MCP_BEARER_TOKEN'] = $bearerToken !== '' ? $bearerToken : (string) ($existing['MCP_BEARER_TOKEN'] ?? '');
        $newConfig['MAIL_PROTOCOL'] = $mailProtocol;
        $newConfig['IMAP_HOST'] = $imapHost;
        $newConfig['IMAP_PORT'] = (int) ($_POST['imapPort'] ?? 993);
        $newConfig['IMAP_SECURE'] = isset($_POST['imapSecure']);
        $newConfig['IMAP_USER'] = $imapUser;
        $newConfig['IMAP_PASSWORD'] = $imapPassword !== '' ? $imapPassword : (string) ($existing['IMAP_PASSWORD'] ?? '');
        $newConfig['SMTP_HOST'] = $smtpHost;
        $newConfig['SMTP_PORT'] = (int) ($_POST['smtpPort'] ?? 587);
        $newConfig['SMTP_SECURE'] = isset($_POST['smtpSecure']);

        $smtpUser = trim((string) ($_POST['smtpUser'] ?? ''));
        $smtpPassword = (string) ($_POST['smtpPassword'] ?? '');
        $newConfig['SMTP_USER'] = $smtpUser !== '' ? $smtpUser : (string) ($existing['SMTP_USER'] ?? '');
        $newConfig['SMTP_PASSWORD'] = $smtpPassword !== '' ? $smtpPassword : (string) ($existing['SMTP_PASSWORD'] ?? '');

        $fromAddress = trim((string) ($_POST['fromAddress'] ?? ''));
        $newConfig['SMTP_FROM_ADDRESS'] = $fromAddress !== '' ? $fromAddress : $imapUser;
        $newConfig['SMTP_FROM_NAME'] = trim((string) ($_POST['fromName'] ?? ''));

        $php = "<?php\n\n" .
            "// Automatisch gegenereerd via setup.php op " . date('c') . ".\n" .
            "// Bewerk dit bestand rechtstreeks, of gebruik setup.php opnieuw.\n" .
            'return ' . var_export($newConfig, true) . ";\n";

        if (@file_put_contents($configPath, $php) === false) {
            $formErrors[] = 'Kon config/config.php niet schrijven. Controleer de schrijfrechten van de map "config/".';
        } else {
            @chmod($configPath, 0600);
            $existing = $newConfig;
            $hasConfig = true;
            $_SESSION['setup_ok'] = true;
            $saved = true;
        }
    }
}

$bearerSuggestion = $hasConfig ? '' : bin2hex(random_bytes(32));

$errorsHtml = '';
if ($formErrors !== []) {
    $errorsHtml = '<div class="error"><strong>Controleer het formulier:</strong><ul>' .
        implode('', array_map(static fn ($e) => '<li>' . h($e) . '</li>', $formErrors)) . '</ul></div>';
}

$successHtml = '';
if ($saved) {
    $successHtml = '<div class="success">' .
        '<strong>Opgeslagen.</strong> De configuratie is bijgewerkt.' .
        ($bearerSuggestion !== '' || isset($_POST['bearerToken']) ? '<p>Bearer token voor GPT/ChatGPT (nu instellen, wordt hierna niet meer volledig getoond):<br>' .
            '<span class="token">' . h($existing['MCP_BEARER_TOKEN']) . '</span></p>' : '') .
        '<p>Test nu: <a href="health.php" target="_blank">health.php</a> en koppel <code>mcp.php</code> als connector-URL in GPT/ChatGPT.</p>' .
        '<p><strong>Belangrijk:</strong> verwijder of hernoem <code>setup.php</code> zodra je klaar bent — ' .
        'deze pagina blijft anders bereikbaar (al wel achter je bearer token beschermd).</p>' .
        '</div>';
}

$warnHtml = !$hasConfig
    ? '<div class="warn"><strong>Eerste keer instellen.</strong> Zodra je hebt opgeslagen, wordt deze pagina ' .
        'vergrendeld met je bearer token. Verwijder <code>setup.php</code> na gebruik voor extra veiligheid.</div>'
    : '';

$val = static fn (string $key, string $default = '') => h($existing[$key] ?? $default);
$checked = static fn (string $key, bool $default) => (($existing[$key] ?? $default)) ? 'checked' : '';
$selected = static fn (string $value) => (($existing['MAIL_PROTOCOL'] ?? 'imap') === $value) ? 'selected' : '';

render_page('MCP Email Connector - instellingen', '
    <div class="card">
        <h1>MCP Email Connector - instellingen</h1>
        <p class="lead">Vul hier je IMAP- of POP3-gegevens en SMTP-gegevens in. Wachtwoordvelden leeglaten behoudt de bestaande waarde.</p>
    </div>
    ' . $warnHtml . $errorsHtml . $successHtml . '
    <div class="card">
        <form method="post">
            <input type="hidden" name="csrf" value="' . h(csrf_token()) . '">

            <h2>Bearer token (voor GPT/ChatGPT)</h2>
            <label for="bearerToken">Token</label>
            <input type="text" id="bearerToken" name="bearerToken" value="' . h($bearerSuggestion) . '"
                placeholder="' . ($hasConfig ? 'laat leeg om te behouden' : '') . '">
            <p class="hint">Dit is het token voor GPT/ChatGPT. Gebruik bij URL-only configuratie <code>?token=DIT_TOKEN</code>.</p>

            <h2>Inkomende mail</h2>
            <label for="mailProtocol">Protocol</label>
            <select id="mailProtocol" name="mailProtocol">
                <option value="imap" ' . $selected('imap') . '>IMAP</option>
                <option value="pop3" ' . $selected('pop3') . '>POP3</option>
            </select>
            <p class="hint">Gebruik voor POP3 doorgaans poort 995 met SSL/TLS; voor IMAP poort 993.</p>
            <label for="imapHost">Host</label>
            <input type="text" id="imapHost" name="imapHost" value="' . $val('IMAP_HOST') . '" required>
            <div class="row">
                <div>
                    <label for="imapPort">Poort</label>
                    <input type="number" id="imapPort" name="imapPort" value="' . $val('IMAP_PORT', '993') . '">
                </div>
                <div class="checkbox" style="margin-top:1.8rem">
                    <input type="checkbox" id="imapSecure" name="imapSecure" ' . $checked('IMAP_SECURE', true) . '>
                    <label for="imapSecure" style="margin:0">SSL/TLS (IMAP 993 / POP3 995)</label>
                </div>
            </div>
            <label for="imapUser">Gebruikersnaam</label>
            <input type="text" id="imapUser" name="imapUser" value="' . $val('IMAP_USER') . '" required>
            <label for="imapPassword">Wachtwoord</label>
            <input type="password" id="imapPassword" name="imapPassword"
                placeholder="' . ($hasConfig ? 'laat leeg om te behouden' : '') . '">

            <h2>SMTP (uitgaand)</h2>
            <label for="smtpHost">Host</label>
            <input type="text" id="smtpHost" name="smtpHost" value="' . $val('SMTP_HOST') . '" required>
            <div class="row">
                <div>
                    <label for="smtpPort">Poort</label>
                    <input type="number" id="smtpPort" name="smtpPort" value="' . $val('SMTP_PORT', '587') . '">
                </div>
                <div class="checkbox" style="margin-top:1.8rem">
                    <input type="checkbox" id="smtpSecure" name="smtpSecure" ' . $checked('SMTP_SECURE', false) . '>
                    <label for="smtpSecure" style="margin:0">SSL/TLS (poort 465, anders STARTTLS op 587)</label>
                </div>
            </div>
            <label for="smtpUser">Gebruikersnaam (leeg = zelfde als inkomende mail)</label>
            <input type="text" id="smtpUser" name="smtpUser" value="' . $val('SMTP_USER') . '">
            <label for="smtpPassword">Wachtwoord (leeg = zelfde als inkomende mail)</label>
            <input type="password" id="smtpPassword" name="smtpPassword"
                placeholder="' . ($hasConfig ? 'laat leeg om te behouden' : '') . '">
            <label for="fromAddress">Afzenderadres (leeg = gebruikersnaam inkomende mail)</label>
            <input type="text" id="fromAddress" name="fromAddress" value="' . $val('SMTP_FROM_ADDRESS') . '">
            <label for="fromName">Afzendernaam</label>
            <input type="text" id="fromName" name="fromName" value="' . $val('SMTP_FROM_NAME') . '">

            <button type="submit">Opslaan</button>
        </form>
    </div>
');
