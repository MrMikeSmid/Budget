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
        $stmt = Database::connection()->prepare(
            'SELECT * FROM ' . static::table() . ' WHERE period_id = :period_id ORDER BY sort_order, id'
        );
        $stmt->execute(['period_id' => $periodId]);

        return $stmt->fetchAll();
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
     * Kopieert terugkerende regels naar een nieuwe periode, met inachtneming
     * van de frequentie (maandelijks/kwartaal/halfjaarlijks/jaarlijks): zoekt
     * niet alleen in de vorige periode, maar over de hele geschiedenis — een
     * jaarlijkse regel kan voor het laatst maanden geleden voorgekomen zijn.
     * Werkelijk bedrag en status worden leeggemaakt (nieuwe periode, nog niets
     * betaald/ontvangen); terugkerend blijft aan staan.
     */
    public static function copyRecurring(int $fromPeriodId, int $toPeriodId): void
    {
        $newPeriod = BudgetPeriod::find($toPeriodId);
        if (!$newPeriod) {
            return;
        }

        $pdo = Database::connection();
        $table = static::table();

        // Eén rij per terugkerende reeks: de meest recente voorkomen, ongeacht periode.
        $stmt = $pdo->prepare(
            "SELECT COALESCE(li.recurrence_group_id, li.id) AS group_key, MAX(bp.start_date) AS latest_start
             FROM {$table} li
             JOIN budget_periods bp ON bp.id = li.period_id
             WHERE li.is_recurring = 1
             GROUP BY group_key"
        );
        $stmt->execute();
        $groups = $stmt->fetchAll();

        foreach ($groups as $group) {
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

            if (!$item || (int) $item['period_id'] === $toPeriodId) {
                continue; // al aanwezig in de doelperiode
            }

            if (!self::isDueForPeriod($item, $newPeriod)) {
                continue;
            }

            $groupId = $item['recurrence_group_id'] ? (int) $item['recurrence_group_id'] : (int) $item['id'];

            static::createFull(
                $toPeriodId,
                (string) $item['description'],
                (float) $item['budgeted'],
                null,
                '',
                true,
                (string) $item['recurrence_interval'],
                (string) $item['recurrence_mode'],
                $item['recurrence_date'] ?: null,
                $groupId
            );
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
