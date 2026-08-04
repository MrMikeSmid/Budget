<?php

namespace App\Models;

use App\Support\Database;
use DateTime;

/**
 * Gedeelde basis voor income_items en fixed_costs: beide zijn een lijst
 * regels per periode met omschrijving, begroot/werkelijk bedrag, status
 * en een optionele terugkerende frequentie.
 */
abstract class LineItem
{
    public const INTERVALS = [
        'maandelijks' => 'Maandelijks',
        'kwartaal' => 'Per kwartaal',
        'halfjaarlijks' => 'Halfjaarlijks',
        'jaarlijks' => 'Jaarlijks',
    ];

    public const MODES = [
        'periode' => 'Bij start nieuwe periode',
        'datum' => 'Op een vaste datum',
    ];

    private const INTERVAL_MONTHS = [
        'maandelijks' => 1,
        'kwartaal' => 3,
        'halfjaarlijks' => 6,
        'jaarlijks' => 12,
    ];

    abstract protected static function table(): string;

    /** Kolom op de transactions-tabel die naar deze regelsoort verwijst. */
    abstract protected static function transactionLinkColumn(): string;

    /**
     * Status waarmee een nieuw gekopieerde terugkerende regel start (zie
     * copyRecurring()). Vaste lasten krijgen "Open" mee zodat meteen
     * duidelijk is wat er nog betaald moet worden; inkomsten laten dit leeg
     * omdat hun "nog te ontvangen"-telling een lege status al als "nog niet
     * ontvangen" behandelt.
     */
    protected static function defaultRecurringStatus(): string
    {
        return '';
    }

    public static function normalizeInterval(string $interval): string
    {
        return array_key_exists($interval, static::INTERVALS) ? $interval : 'maandelijks';
    }

    public static function normalizeMode(string $mode): string
    {
        return array_key_exists($mode, static::MODES) ? $mode : 'periode';
    }

    public static function forPeriod(int $periodId): array
    {
        $table = static::table();
        $linkColumn = static::transactionLinkColumn();
        $stmt = Database::connection()->prepare(
            "SELECT {$table}.*,
                    (SELECT t.id FROM transactions t WHERE t.{$linkColumn} = {$table}.id ORDER BY t.id DESC LIMIT 1) AS linked_transaction_id
             FROM {$table} WHERE period_id = :period_id ORDER BY sort_order, id"
        );
        $stmt->execute(['period_id' => $periodId]);

        return $stmt->fetchAll();
    }

    /**
     * Id van de kasstroommutatie die aan deze regel gekoppeld is (indien
     * aanwezig). Zodra een regel gekoppeld is, gebeurt bewerken via die
     * mutatie op kasstroom i.p.v. via het eigen formulier van deze regel —
     * de mutatie kent immers ook het werkelijke bedrag en de datum.
     */
    public static function linkedTransactionId(int $id): ?int
    {
        $stmt = Database::connection()->prepare(
            'SELECT id FROM transactions WHERE ' . static::transactionLinkColumn() . ' = :id ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetchColumn();

        return $result !== false ? (int) $result : null;
    }

    /**
     * Herberekent "werkelijk" als de som van alle gekoppelde
     * kasstroommutaties (kan er meerdere zijn bij gedeeltelijke
     * betalingen). Wordt aangeroepen na elke create/update/delete van een
     * gekoppelde mutatie, zodat dit bedrag nooit los raakt van de
     * daadwerkelijke kasstroomgeschiedenis.
     */
    public static function syncActualFromTransactions(int $id): void
    {
        $pdo = Database::connection();
        $linkColumn = static::transactionLinkColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(ABS(amount)), 0) FROM transactions WHERE {$linkColumn} = :id");
        $stmt->execute(['id' => $id]);
        $sum = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare('UPDATE ' . static::table() . ' SET actual = :actual WHERE id = :id');
        $stmt->execute(['actual' => $sum, 'id' => $id]);
    }

    /**
     * Zet de status terug als deze regel geen enkele gekoppelde
     * kasstroommutatie meer heeft (bijv. na het verwijderen of ontkoppelen
     * van de laatste mutatie) — anders blijft "Betaald"/"Ontvangen" ten
     * onrechte staan terwijl er niets meer tegenover staat. Blijft de
     * regel via een andere mutatie nog gekoppeld (gedeeltelijke
     * betalingen), dan gebeurt er niets.
     */
    public static function revertStatusIfUnlinked(int $id, string $unlinkedStatus): void
    {
        if (static::linkedTransactionId($id) !== null) {
            return;
        }

        $stmt = Database::connection()->prepare('UPDATE ' . static::table() . ' SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $unlinkedStatus, 'id' => $id]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM ' . static::table() . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public static function create(int $periodId, string $description, float $budgeted, ?float $actual, string $status, bool $isRecurring = false): int
    {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ' . static::table() . ' WHERE period_id = :period_id');
        $stmt->execute(['period_id' => $periodId]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO ' . static::table() . ' (period_id, description, budgeted, actual, status, is_recurring, sort_order)
             VALUES (:period_id, :description, :budgeted, :actual, :status, :is_recurring, :sort_order)'
        );
        $stmt->execute([
            'period_id' => $periodId,
            'description' => $description,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'status' => $status,
            'is_recurring' => $isRecurring ? 1 : 0,
            'sort_order' => $sortOrder,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function update(int $id, string $description, float $budgeted, ?float $actual, string $status, bool $isRecurring = false): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE ' . static::table() . ' SET description = :description, budgeted = :budgeted, actual = :actual, status = :status, is_recurring = :is_recurring WHERE id = :id'
        );
        $stmt->execute([
            'description' => $description,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'status' => $status,
            'is_recurring' => $isRecurring ? 1 : 0,
            'id' => $id,
        ]);
    }

    /**
     * Volledige create/update met de terugkeerfrequentie-velden
     * (interval/modus/datum/groep). $recurrenceGroupId wijst naar de
     * oorspronkelijke regel van een terugkerende reeks.
     */
    public static function createFull(
        int $periodId,
        string $description,
        float $budgeted,
        ?float $actual,
        string $status,
        bool $isRecurring,
        string $recurrenceInterval = 'maandelijks',
        string $recurrenceMode = 'periode',
        ?string $recurrenceDate = null,
        ?int $recurrenceGroupId = null
    ): int {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM ' . static::table() . ' WHERE period_id = :period_id');
        $stmt->execute(['period_id' => $periodId]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO ' . static::table() . '
                (period_id, description, budgeted, actual, status, is_recurring, recurrence_interval, recurrence_mode, recurrence_date, recurrence_group_id, sort_order)
             VALUES
                (:period_id, :description, :budgeted, :actual, :status, :is_recurring, :recurrence_interval, :recurrence_mode, :recurrence_date, :recurrence_group_id, :sort_order)'
        );
        $stmt->execute([
            'period_id' => $periodId,
            'description' => $description,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'status' => $status,
            'is_recurring' => $isRecurring ? 1 : 0,
            'recurrence_interval' => static::normalizeInterval($recurrenceInterval),
            'recurrence_mode' => static::normalizeMode($recurrenceMode),
            'recurrence_date' => $recurrenceDate ?: null,
            'recurrence_group_id' => $recurrenceGroupId,
            'sort_order' => $sortOrder,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function updateFull(
        int $id,
        string $description,
        float $budgeted,
        ?float $actual,
        string $status,
        bool $isRecurring,
        string $recurrenceInterval = 'maandelijks',
        string $recurrenceMode = 'periode',
        ?string $recurrenceDate = null
    ): void {
        $stmt = Database::connection()->prepare(
            'UPDATE ' . static::table() . ' SET
                description = :description, budgeted = :budgeted, actual = :actual, status = :status,
                is_recurring = :is_recurring, recurrence_interval = :recurrence_interval,
                recurrence_mode = :recurrence_mode, recurrence_date = :recurrence_date
             WHERE id = :id'
        );
        $stmt->execute([
            'description' => $description,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'status' => $status,
            'is_recurring' => $isRecurring ? 1 : 0,
            'recurrence_interval' => static::normalizeInterval($recurrenceInterval),
            'recurrence_mode' => static::normalizeMode($recurrenceMode),
            'recurrence_date' => $recurrenceDate ?: null,
            'id' => $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM ' . static::table() . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function totals(int $periodId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT COALESCE(SUM(budgeted), 0) AS budgeted, COALESCE(SUM(actual), 0) AS actual
             FROM ' . static::table() . ' WHERE period_id = :period_id'
        );
        $stmt->execute(['period_id' => $periodId]);

        return $stmt->fetch();
    }

    /**
     * Kopieert terugkerende regels naar een periode, met inachtneming van de
     * frequentie (maandelijks/kwartaal/halfjaarlijks/jaarlijks): zoekt niet
     * alleen in de vorige periode, maar over de hele geschiedenis — een
     * jaarlijkse regel kan voor het laatst maanden geleden voorgekomen zijn.
     * Werkelijk bedrag en status worden leeggemaakt (nieuwe periode, nog niets
     * betaald/ontvangen); terugkerend blijft aan staan. Idempotent: een groep
     * die in $toPeriodId al een voorkomen heeft, wordt overgeslagen.
     */
    public static function copyRecurring(int $toPeriodId): void
    {
        $newPeriod = BudgetPeriod::find($toPeriodId);
        if (!$newPeriod) {
            return;
        }

        $pdo = Database::connection();
        $table = static::table();

        // Eén rij per terugkerende reeks: de meest recente voorkomen vóór of
        // op de doelperiode. Beperkt tot bp.start_date <= doelperiode, want
        // fillFuturePeriods() kan periodes niet per se in chronologische
        // volgorde (opnieuw) langslopen — zonder deze grens zou een reeks die
        // al verder in de toekomst een voorkomen heeft, per ongeluk dat latere
        // voorkomen als uitgangspunt nemen voor een eerdere doelperiode.
        $stmt = $pdo->prepare(
            "SELECT COALESCE(li.recurrence_group_id, li.id) AS group_key, MAX(bp.start_date) AS latest_start
             FROM {$table} li
             JOIN budget_periods bp ON bp.id = li.period_id
             WHERE li.is_recurring = 1 AND bp.start_date <= :target_start
             GROUP BY group_key"
        );
        $stmt->bindValue('target_start', $newPeriod['start_date']);
        $stmt->execute();
        $groups = $stmt->fetchAll();

        foreach ($groups as $group) {
            // Losse, expliciete aanwezigheidscheck (i.p.v. afleiden uit "is de
            // meest recente voorkomen toevallig de doelperiode"): anders mist
            // deze check een voorkomen dat al in de doelperiode zit terwijl er
            // ook al een latere periode gevuld is.
            $existsStmt = $pdo->prepare(
                "SELECT 1 FROM {$table} WHERE COALESCE(recurrence_group_id, id) = :group_key AND period_id = :period_id LIMIT 1"
            );
            $existsStmt->bindValue('group_key', (int) $group['group_key'], \PDO::PARAM_INT);
            $existsStmt->bindValue('period_id', $toPeriodId, \PDO::PARAM_INT);
            $existsStmt->execute();
            if ($existsStmt->fetchColumn()) {
                continue; // al aanwezig in de doelperiode
            }

            $stmt = $pdo->prepare(
                "SELECT li.*, bp.start_date AS occurrence_start_date
                 FROM {$table} li
                 JOIN budget_periods bp ON bp.id = li.period_id
                 WHERE COALESCE(li.recurrence_group_id, li.id) = :group_key AND bp.start_date = :latest_start
                 LIMIT 1"
            );
            // group_key moet als INTEGER gebonden worden: SQLite beschouwt de
            // INTEGER-kolomwaarde 1 en de TEXT-waarde '1' (PDO's standaardbinding)
            // niet als gelijk, waardoor deze match anders altijd faalt.
            $stmt->bindValue('group_key', (int) $group['group_key'], \PDO::PARAM_INT);
            $stmt->bindValue('latest_start', $group['latest_start']);
            $stmt->execute();
            $item = $stmt->fetch();

            if (!$item) {
                continue;
            }

            if (!self::isDueForPeriod($item, $newPeriod)) {
                continue;
            }

            // Extra vangnet los van de group_key-check hierboven: als er door
            // eerdere (inmiddels opgeloste) dubbele-periode-bugs twee losse
            // terugkerende reeksen met dezelfde omschrijving/bedrag bestaan,
            // voorkomt dit dat ze allebei naar dezelfde nieuwe periode
            // gekopieerd worden.
            $dupStmt = $pdo->prepare(
                "SELECT 1 FROM {$table} WHERE period_id = :period_id AND description = :description AND budgeted = :budgeted LIMIT 1"
            );
            $dupStmt->execute([
                'period_id' => $toPeriodId,
                'description' => $item['description'],
                'budgeted' => $item['budgeted'],
            ]);
            if ($dupStmt->fetchColumn()) {
                continue;
            }

            $groupId = $item['recurrence_group_id'] ? (int) $item['recurrence_group_id'] : (int) $item['id'];

            static::createFull(
                $toPeriodId,
                (string) $item['description'],
                (float) $item['budgeted'],
                null,
                static::defaultRecurringStatus(),
                true,
                (string) $item['recurrence_interval'],
                (string) $item['recurrence_mode'],
                $item['recurrence_date'] ?: null,
                $groupId
            );
        }
    }

    /**
     * Vult alle al bestaande, latere periodes aan met terugkerende regels
     * die daar nog ontbreken. Nodig omdat er vooruit gepland wordt: een
     * nieuwe (of net terugkerend gemaakte) regel in $fromPeriodId moet ook
     * verschijnen in periodes die al bestonden vóórdat deze regel er was —
     * niet pas zodra er weer een nieuwe periode aangemaakt wordt.
     */
    public static function fillFuturePeriods(int $fromPeriodId): void
    {
        $fromPeriod = BudgetPeriod::find($fromPeriodId);
        if (!$fromPeriod) {
            return;
        }

        $futurePeriods = array_values(array_filter(
            BudgetPeriod::all(),
            static fn (array $p): bool => $p['start_date'] > $fromPeriod['start_date']
        ));
        usort($futurePeriods, static fn (array $a, array $b): int => $a['start_date'] <=> $b['start_date']);

        foreach ($futurePeriods as $period) {
            static::copyRecurring((int) $period['id']);
        }
    }

    /**
     * Is deze terugkerende regel aan de beurt in de opgegeven (nieuwe) periode?
     */
    private static function isDueForPeriod(array $item, array $newPeriod): bool
    {
        $interval = static::normalizeInterval((string) $item['recurrence_interval']);
        $months = self::INTERVAL_MONTHS[$interval];

        if ($months <= 1) {
            return true; // maandelijks: elke periode
        }

        $newStart = new DateTime((string) $newPeriod['start_date']);
        $mode = static::normalizeMode((string) $item['recurrence_mode']);

        if ($mode === 'datum' && !empty($item['recurrence_date'])) {
            $newEnd = new DateTime((string) $newPeriod['end_date']);
            $candidate = new DateTime((string) $item['recurrence_date']);

            $safety = 0;
            while ($candidate < $newStart && $safety < 1000) {
                $candidate->modify("+{$months} months");
                $safety++;
            }

            return $candidate >= $newStart && $candidate <= $newEnd;
        }

        // mode 'periode': tel hele maanden sinds de laatste keer dat deze regel voorkwam.
        $occurrenceStart = new DateTime((string) $item['occurrence_start_date']);
        $diffMonths = (((int) $newStart->format('Y')) - ((int) $occurrenceStart->format('Y'))) * 12
            + (((int) $newStart->format('n')) - ((int) $occurrenceStart->format('n')));

        return $diffMonths >= $months;
    }
}
