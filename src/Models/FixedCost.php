<?php

namespace App\Models;

use App\Support\Database;
use DateTime;

final class FixedCost extends LineItem
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

    protected static function table(): string
    {
        return 'fixed_costs';
    }

    public static function normalizeInterval(string $interval): string
    {
        return array_key_exists($interval, self::INTERVALS) ? $interval : 'maandelijks';
    }

    public static function normalizeMode(string $mode): string
    {
        return array_key_exists($mode, self::MODES) ? $mode : 'periode';
    }

    /**
     * Nog openstaand: het volledige begrote bedrag van regels met status
     * "Open", plus — bij status "Betaald" — het verschil tussen begroot en
     * werkelijk als er minder is betaald dan begroot (een gedeeltelijke
     * betaling laat dus het restant nog als openstaand zien, in plaats van
     * de hele regel als afgehandeld te beschouwen zodra de status op
     * "Betaald" staat).
     */
    public static function outstanding(int $periodId): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN status LIKE 'Open%' THEN budgeted
                    WHEN status LIKE 'Betaald%' THEN MAX(budgeted - COALESCE(actual, budgeted), 0)
                    ELSE 0
                END
             ), 0) FROM fixed_costs
             WHERE period_id = :period_id"
        );
        $stmt->execute(['period_id' => $periodId]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Daadwerkelijk betaald: bedrag van regels met status "betaald". Werkelijk
     * bedrag telt als dat is ingevuld, anders valt dit terug op het begrote
     * bedrag (zelfde patroon als IncomeItem::receivedTotal()).
     */
    public static function paidTotal(int $periodId): float
    {
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(COALESCE(actual, budgeted)), 0) FROM fixed_costs
             WHERE period_id = :period_id AND status LIKE 'Betaald%'"
        );
        $stmt->execute(['period_id' => $periodId]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Volledige create met de extra terugkeer-/leningvelden. Overschrijft
     * LineItem::create, die alleen de basisvelden kent.
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
        ?int $recurrenceGroupId = null,
        ?int $loanId = null
    ): int {
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM fixed_costs WHERE period_id = :period_id');
        $stmt->execute(['period_id' => $periodId]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'INSERT INTO fixed_costs
                (period_id, description, budgeted, actual, status, is_recurring, recurrence_interval, recurrence_mode, recurrence_date, recurrence_group_id, loan_id, sort_order)
             VALUES
                (:period_id, :description, :budgeted, :actual, :status, :is_recurring, :recurrence_interval, :recurrence_mode, :recurrence_date, :recurrence_group_id, :loan_id, :sort_order)'
        );
        $stmt->execute([
            'period_id' => $periodId,
            'description' => $description,
            'budgeted' => $budgeted,
            'actual' => $actual,
            'status' => $status,
            'is_recurring' => $isRecurring ? 1 : 0,
            'recurrence_interval' => self::normalizeInterval($recurrenceInterval),
            'recurrence_mode' => self::normalizeMode($recurrenceMode),
            'recurrence_date' => $recurrenceDate ?: null,
            'recurrence_group_id' => $recurrenceGroupId,
            'loan_id' => $loanId,
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
            'UPDATE fixed_costs SET
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
            'recurrence_interval' => self::normalizeInterval($recurrenceInterval),
            'recurrence_mode' => self::normalizeMode($recurrenceMode),
            'recurrence_date' => $recurrenceDate ?: null,
            'id' => $id,
        ]);
    }

    /**
     * Kopieert terugkerende vaste lasten naar een periode. Zoekt niet alleen
     * in de vorige periode, maar over de hele geschiedenis: een jaarlijkse
     * last die twee periodes geleden voor het laatst voorkwam, moet nu nog
     * steeds gevonden worden. Idempotent: een groep die in $toPeriodId al
     * een voorkomen heeft, wordt overgeslagen.
     */
    public static function copyRecurring(int $toPeriodId): void
    {
        $newPeriod = BudgetPeriod::find($toPeriodId);
        if (!$newPeriod) {
            return;
        }

        $pdo = Database::connection();

        // Eén rij per terugkerende reeks: de meest recente voorkomen vóór of
        // op de doelperiode (zie LineItem::copyRecurring() voor waarom deze
        // grens nodig is bij het (opnieuw) niet-chronologisch vullen van
        // periodes via fillFuturePeriods()).
        $stmt = $pdo->prepare(
            "SELECT COALESCE(fc.recurrence_group_id, fc.id) AS group_key, MAX(bp.start_date) AS latest_start
             FROM fixed_costs fc
             JOIN budget_periods bp ON bp.id = fc.period_id
             WHERE fc.is_recurring = 1 AND bp.start_date <= :target_start
             GROUP BY group_key"
        );
        $stmt->bindValue('target_start', $newPeriod['start_date']);
        $stmt->execute();
        $groups = $stmt->fetchAll();

        foreach ($groups as $group) {
            // Losse, expliciete aanwezigheidscheck, zie LineItem::copyRecurring().
            $existsStmt = $pdo->prepare(
                'SELECT 1 FROM fixed_costs WHERE COALESCE(recurrence_group_id, id) = :group_key AND period_id = :period_id LIMIT 1'
            );
            $existsStmt->bindValue('group_key', (int) $group['group_key'], \PDO::PARAM_INT);
            $existsStmt->bindValue('period_id', $toPeriodId, \PDO::PARAM_INT);
            $existsStmt->execute();
            if ($existsStmt->fetchColumn()) {
                continue; // al aanwezig in de doelperiode
            }

            $stmt = $pdo->prepare(
                "SELECT fc.*, bp.start_date AS occurrence_start_date
                 FROM fixed_costs fc
                 JOIN budget_periods bp ON bp.id = fc.period_id
                 WHERE COALESCE(fc.recurrence_group_id, fc.id) = :group_key AND bp.start_date = :latest_start
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

            if (!empty($item['loan_id']) && Loan::remainingAmount((int) $item['loan_id']) <= 0.0) {
                continue; // lening al volledig afgelost
            }

            $groupId = $item['recurrence_group_id'] ? (int) $item['recurrence_group_id'] : (int) $item['id'];

            self::createFull(
                $toPeriodId,
                (string) $item['description'],
                (float) $item['budgeted'],
                null,
                '',
                true,
                (string) $item['recurrence_interval'],
                (string) $item['recurrence_mode'],
                $item['recurrence_date'] ?: null,
                $groupId,
                $item['loan_id'] ? (int) $item['loan_id'] : null
            );
        }
    }

    /**
     * Is deze terugkerende regel aan de beurt in de opgegeven (nieuwe) periode?
     */
    private static function isDueForPeriod(array $item, array $newPeriod): bool
    {
        $interval = self::normalizeInterval((string) $item['recurrence_interval']);
        $months = self::INTERVAL_MONTHS[$interval];

        if ($months <= 1) {
            return true; // maandelijks: elke periode
        }

        $newStart = new DateTime((string) $newPeriod['start_date']);
        $mode = self::normalizeMode((string) $item['recurrence_mode']);

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
