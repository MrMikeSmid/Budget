<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Database.php';

session_start();
$db = Database::connect();

function redirect(string $url = '/'): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = [$message, $type];
}

function cents(string $value): int
{
    $normalized = str_replace(['€', ' ', '.'], '', trim($value));
    $normalized = str_replace(',', '.', $normalized);
    return (int) round(((float) $normalized) * 100);
}

function money(int $cents): string
{
    return '€ ' . number_format($cents / 100, 2, ',', '.');
}

function validMonth(string $month): string
{
    return preg_match('/^\d{4}-\d{2}$/', $month) ? $month : date('Y-m');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_transaction') {
        $data = [
            trim($_POST['date'] ?? ''), trim($_POST['description'] ?? ''),
            trim($_POST['category'] ?? ''), $_POST['type'] ?? '',
            cents($_POST['amount'] ?? '0'), trim($_POST['note'] ?? ''),
        ];
        if (!$data[0] || !$data[1] || !$data[2] || !in_array($data[3], ['inkomst', 'uitgave'], true) || $data[4] <= 0) {
            flash('Vul alle verplichte velden correct in.', 'error');
            redirect('/?month=' . validMonth(substr($data[0], 0, 7)));
        }
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = $db->prepare('UPDATE transactions SET transaction_date=?, description=?, category=?, type=?, amount_cents=?, note=? WHERE id=?');
            $stmt->execute([...$data, $id]);
            flash('Boeking is bijgewerkt.');
        } else {
            $stmt = $db->prepare('INSERT INTO transactions (transaction_date, description, category, type, amount_cents, note) VALUES (?,?,?,?,?,?)');
            $stmt->execute($data);
            flash('Boeking is toegevoegd.');
        }
        redirect('/?month=' . substr($data[0], 0, 7));
    }

    if ($action === 'delete_transaction') {
        $stmt = $db->prepare('DELETE FROM transactions WHERE id=?');
        $stmt->execute([(int) ($_POST['id'] ?? 0)]);
        flash('Boeking is verwijderd.');
        redirect('/?' . http_build_query(['month' => validMonth($_POST['month'] ?? '')]));
    }

    if ($action === 'save_budget') {
        $month = validMonth($_POST['month'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $amount = cents($_POST['amount'] ?? '0');
        if ($category === '' || $amount <= 0) {
            flash('Vul een categorie en een geldig budget in.', 'error');
        } else {
            $stmt = $db->prepare('INSERT INTO budgets (month, category, amount_cents) VALUES (?,?,?) ON CONFLICT(month, category) DO UPDATE SET amount_cents=excluded.amount_cents');
            $stmt->execute([$month, $category, $amount]);
            flash('Maandbudget is opgeslagen.');
        }
        redirect('/?month=' . $month . '#budgetten');
    }

    if ($action === 'seed') {
        $month = date('Y-m');
        $db->beginTransaction();
        $insert = $db->prepare('INSERT INTO transactions (transaction_date, description, category, type, amount_cents, note) VALUES (?,?,?,?,?,?)');
        foreach ([
            [$month . '-01', 'Salaris', 'Inkomen', 'inkomst', 285000, 'Maandinkomen'],
            [$month . '-02', 'Huur', 'Wonen', 'uitgave', 105000, ''],
            [$month . '-05', 'Boodschappen', 'Boodschappen', 'uitgave', 7845, 'Supermarkt'],
            [$month . '-08', 'Energie', 'Vaste lasten', 'uitgave', 14200, ''],
        ] as $row) $insert->execute($row);
        $budget = $db->prepare('INSERT OR IGNORE INTO budgets (month, category, amount_cents) VALUES (?,?,?)');
        foreach ([['Wonen', 110000], ['Boodschappen', 45000], ['Vaste lasten', 30000]] as $row) $budget->execute([$month, ...$row]);
        $db->commit();
        flash('Voorbeeldgegevens zijn toegevoegd.');
        redirect('/?month=' . $month);
    }

    if ($action === 'import' && isset($_FILES['csv']['tmp_name'])) {
        $handle = fopen($_FILES['csv']['tmp_name'], 'rb');
        $first = fgets($handle) ?: '';
        rewind($handle);
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $count = 0;
        $insert = $db->prepare('INSERT INTO transactions (transaction_date, description, category, type, amount_cents, note) VALUES (?,?,?,?,?,?)');
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (strtolower(trim($row[0] ?? '')) === 'datum') continue;
            $type = strtolower(trim($row[3] ?? ''));
            $amount = cents($row[4] ?? '0');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($row[0] ?? '')) && in_array($type, ['inkomst', 'uitgave'], true) && $amount > 0) {
                $insert->execute([trim($row[0]), trim($row[1] ?? ''), trim($row[2] ?? 'Overig'), $type, $amount, trim($row[5] ?? '')]);
                $count++;
            }
        }
        fclose($handle);
        flash("$count boekingen geïmporteerd.");
        redirect('/');
    }
}

if (($_GET['export'] ?? '') === 'csv') {
    $month = validMonth($_GET['month'] ?? '');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="budget-' . $month . '.csv"');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['datum', 'omschrijving', 'categorie', 'type', 'bedrag', 'notitie'], ';');
    $stmt = $db->prepare("SELECT * FROM transactions WHERE substr(transaction_date,1,7)=? ORDER BY transaction_date,id");
    $stmt->execute([$month]);
    foreach ($stmt as $row) fputcsv($out, [$row['transaction_date'], $row['description'], $row['category'], $row['type'], number_format($row['amount_cents']/100, 2, ',', ''), $row['note']], ';');
    exit;
}

$month = validMonth($_GET['month'] ?? date('Y-m'));
$type = in_array($_GET['type'] ?? '', ['inkomst', 'uitgave'], true) ? $_GET['type'] : '';
$search = trim($_GET['q'] ?? '');
$sql = "SELECT * FROM transactions WHERE substr(transaction_date,1,7)=?";
$params = [$month];
if ($type) { $sql .= ' AND type=?'; $params[] = $type; }
if ($search) { $sql .= ' AND (description LIKE ? OR category LIKE ? OR note LIKE ?)'; array_push($params, "%$search%", "%$search%", "%$search%"); }
$sql .= ' ORDER BY transaction_date DESC, id DESC';
$stmt = $db->prepare($sql); $stmt->execute($params); $transactions = $stmt->fetchAll();

$totals = ['inkomst' => 0, 'uitgave' => 0];
foreach ($transactions as $transaction) $totals[$transaction['type']] += $transaction['amount_cents'];
$allTotalsStmt = $db->prepare("SELECT type, SUM(amount_cents) total FROM transactions WHERE substr(transaction_date,1,7)=? GROUP BY type");
$allTotalsStmt->execute([$month]);
$allTotals = ['inkomst' => 0, 'uitgave' => 0];
foreach ($allTotalsStmt as $row) $allTotals[$row['type']] = (int) $row['total'];

$budgetStmt = $db->prepare("SELECT b.*, COALESCE(SUM(t.amount_cents),0) spent FROM budgets b LEFT JOIN transactions t ON t.category=b.category AND t.type='uitgave' AND substr(t.transaction_date,1,7)=b.month WHERE b.month=? GROUP BY b.id ORDER BY b.category");
$budgetStmt->execute([$month]); $budgets = $budgetStmt->fetchAll();
$edit = null;
if (isset($_GET['edit'])) { $e = $db->prepare('SELECT * FROM transactions WHERE id=?'); $e->execute([(int) $_GET['edit']]); $edit = $e->fetch() ?: null; }
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$categories = ['Boodschappen','Inkomen','Kleding','Ontspanning','Sparen','Vaste lasten','Vervoer','Wonen','Zorg','Overig'];
?>
<!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Budgetbeheer</title><link rel="stylesheet" href="/style.css">
</head>
<body>
<header><div><span class="logo">€</span><strong>Budgetbeheer</strong></div><nav><a href="#overzicht">Overzicht</a><a href="#boekingen">Boekingen</a><a href="#budgetten">Budgetten</a></nav></header>
<main>
  <?php if ($flash): ?><div class="flash <?= $flash[1] ?>"><?= htmlspecialchars($flash[0]) ?></div><?php endif; ?>
  <section class="hero" id="overzicht"><div><p class="eyebrow">FINANCIEEL OVERZICHT</p><h1>Grip op je geld,<br><em>maand na maand.</em></h1><p>Alle inkomsten, uitgaven en budgetten helder op één plek.</p></div><form class="month-picker"><label>Bekijk maand<input type="month" name="month" value="<?= $month ?>" onchange="this.form.submit()"></label></form></section>
  <section class="stats">
    <article><span>Inkomsten</span><strong class="positive"><?= money($allTotals['inkomst']) ?></strong><small>Deze maand</small></article>
    <article><span>Uitgaven</span><strong class="negative"><?= money($allTotals['uitgave']) ?></strong><small>Deze maand</small></article>
    <article class="balance"><span>Resterend</span><strong><?= money($allTotals['inkomst']-$allTotals['uitgave']) ?></strong><small><?= $allTotals['inkomst'] ? round(($allTotals['inkomst']-$allTotals['uitgave'])/$allTotals['inkomst']*100) : 0 ?>% van inkomsten</small></article>
  </section>
  <section class="grid">
    <div class="panel form-panel"><div class="panel-title"><div><small>NIEUWE BOEKING</small><h2><?= $edit ? 'Boeking wijzigen' : 'Bedrag toevoegen' ?></h2></div></div>
      <form method="post" class="entry-form"><input type="hidden" name="action" value="save_transaction"><input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
        <label>Datum<input required type="date" name="date" value="<?= htmlspecialchars($edit['transaction_date'] ?? date('Y-m-d')) ?>"></label>
        <label>Soort<select name="type"><option value="uitgave">Uitgave</option><option value="inkomst" <?= ($edit['type'] ?? '') === 'inkomst' ? 'selected' : '' ?>>Inkomst</option></select></label>
        <label class="wide">Omschrijving<input required name="description" placeholder="Bijv. boodschappen" value="<?= htmlspecialchars($edit['description'] ?? '') ?>"></label>
        <label>Categorie<input required name="category" list="categories" value="<?= htmlspecialchars($edit['category'] ?? '') ?>"><datalist id="categories"><?php foreach ($categories as $c): ?><option value="<?= $c ?>"><?php endforeach; ?></datalist></label>
        <label>Bedrag<input required name="amount" inputmode="decimal" placeholder="0,00" value="<?= $edit ? number_format($edit['amount_cents']/100,2,',','') : '' ?>"></label>
        <label class="wide">Notitie <span>(optioneel)</span><input name="note" value="<?= htmlspecialchars($edit['note'] ?? '') ?>"></label>
        <button class="primary wide"><?= $edit ? 'Wijzigingen opslaan' : '+ Boeking toevoegen' ?></button>
      </form>
    </div>
    <div class="panel" id="budgetten"><div class="panel-title"><div><small>MAANDLIMIETEN</small><h2>Budgetten</h2></div><span><?= count($budgets) ?> categorieën</span></div>
      <?php if (!$budgets): ?><div class="empty">Nog geen budgetten voor deze maand.</div><?php endif; ?>
      <?php foreach ($budgets as $budget): $pct = min(100, round($budget['spent']/$budget['amount_cents']*100)); ?><div class="budget-row"><div><b><?= htmlspecialchars($budget['category']) ?></b><span><?= money((int)$budget['spent']) ?> van <?= money((int)$budget['amount_cents']) ?></span></div><div class="progress"><i style="width:<?= $pct ?>%" class="<?= $pct >= 90 ? 'danger' : '' ?>"></i></div></div><?php endforeach; ?>
      <form method="post" class="budget-form"><input type="hidden" name="action" value="save_budget"><input type="hidden" name="month" value="<?= $month ?>"><input required name="category" list="categories" placeholder="Categorie"><input required name="amount" placeholder="Budget €"><button>Opslaan</button></form>
    </div>
  </section>
  <section class="panel transactions" id="boekingen"><div class="panel-title"><div><small>TRANSACTIES</small><h2>Boekingen</h2></div><a class="button" href="?export=csv&month=<?= $month ?>">↓ Exporteer CSV</a></div>
    <form class="filters"><input type="hidden" name="month" value="<?= $month ?>"><input name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Zoek omschrijving of categorie"><select name="type"><option value="">Alle soorten</option><option value="inkomst" <?= $type==='inkomst'?'selected':'' ?>>Inkomsten</option><option value="uitgave" <?= $type==='uitgave'?'selected':'' ?>>Uitgaven</option></select><button>Filter</button></form>
    <div class="table-wrap"><table><thead><tr><th>Datum</th><th>Omschrijving</th><th>Categorie</th><th>Bedrag</th><th></th></tr></thead><tbody>
    <?php foreach ($transactions as $row): ?><tr><td><?= date('d-m-Y', strtotime($row['transaction_date'])) ?></td><td><b><?= htmlspecialchars($row['description']) ?></b><?php if($row['note']): ?><small><?= htmlspecialchars($row['note']) ?></small><?php endif; ?></td><td><span class="tag"><?= htmlspecialchars($row['category']) ?></span></td><td class="amount <?= $row['type']==='inkomst'?'positive':'negative' ?>"><?= $row['type']==='inkomst'?'+':'−' ?> <?= money((int)$row['amount_cents']) ?></td><td class="actions"><a href="?month=<?= $month ?>&edit=<?= $row['id'] ?>#overzicht">Wijzig</a><form method="post" onsubmit="return confirm('Deze boeking verwijderen?')"><input type="hidden" name="action" value="delete_transaction"><input type="hidden" name="id" value="<?= $row['id'] ?>"><input type="hidden" name="month" value="<?= $month ?>"><button>×</button></form></td></tr><?php endforeach; ?>
    <?php if (!$transactions): ?><tr><td colspan="5" class="empty">Geen boekingen gevonden.</td></tr><?php endif; ?></tbody></table></div>
  </section>
  <section class="import"><div><h3>Gegevens overzetten uit een spreadsheet</h3><p>Exporteer een werkblad als CSV en importeer het hier.</p></div><form method="post" enctype="multipart/form-data"><input type="hidden" name="action" value="import"><input required type="file" name="csv" accept=".csv,text/csv"><button>CSV importeren</button></form><?php if (!$allTotals['inkomst'] && !$allTotals['uitgave']): ?><form method="post"><input type="hidden" name="action" value="seed"><button class="link-button">Gebruik voorbeeldgegevens</button></form><?php endif; ?></section>
</main><footer>Budgetbeheer · Je gegevens blijven lokaal in SQLite.</footer>
</body></html>
