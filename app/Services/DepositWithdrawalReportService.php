<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\WithdrawStatus;
use App\Models\DepositWithdrawalReportRow;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use PDO;
use PDOStatement;

/**
 * Aggregates approved deposits and withdrawals per day and country.
 *
 * Only Approved rows count, dated by when they actually settled (a
 * deposit's approved_at, a withdrawal's processed_at) rather than when they
 * were requested: a still-Pending withdrawal or a Disapproved deposit never
 * moved any money, so neither should appear in the report.
 */
final readonly class DepositWithdrawalReportService
{
    /**
     * Two CTEs scoped to the requested range and Approved status, and a
     * third listing every distinct (date, country) pair either produced -
     * shared by both the count query and the page query below.
     */
    private const string ACTIVITY_CTE = <<<'SQL'
        WITH deposit_activity AS (
            SELECT DATE(d.approved_at) AS activity_date, c.country, d.customer_id, d.amount
            FROM deposits d
            INNER JOIN customers c ON c.id = d.customer_id
            WHERE d.status_id = :deposit_status
              AND d.approved_at >= :deposit_from
              AND d.approved_at < :deposit_to
        ),
        withdrawal_activity AS (
            SELECT DATE(w.processed_at) AS activity_date, c.country, w.customer_id, w.amount
            FROM withdrawals w
            INNER JOIN customers c ON c.id = w.customer_id
            WHERE w.status_id = :withdrawal_status
              AND w.processed_at >= :withdrawal_from
              AND w.processed_at < :withdrawal_to
        ),
        report_keys AS (
            SELECT activity_date, country FROM deposit_activity
            UNION
            SELECT activity_date, country FROM withdrawal_activity
        )
        SQL;

    private const string PAGE_QUERY_SUFFIX = <<<'SQL'
        ,
        unique_customer_counts AS (
            SELECT activity_date, country, COUNT(DISTINCT customer_id) AS unique_customers
            FROM (
                SELECT activity_date, country, customer_id FROM deposit_activity
                UNION
                SELECT activity_date, country, customer_id FROM withdrawal_activity
            ) combined_activity
            GROUP BY activity_date, country
        ),
        deposit_totals AS (
            SELECT activity_date, country, COUNT(*) AS deposits_count, SUM(amount) AS deposits_total
            FROM deposit_activity
            GROUP BY activity_date, country
        ),
        withdrawal_totals AS (
            SELECT activity_date, country, COUNT(*) AS withdrawals_count, SUM(amount) AS withdrawals_total
            FROM withdrawal_activity
            GROUP BY activity_date, country
        )
        SELECT
            k.activity_date,
            k.country,
            COALESCE(u.unique_customers, 0) AS unique_customers,
            COALESCE(dt.deposits_count, 0) AS deposits_count,
            COALESCE(dt.deposits_total, 0) AS deposits_total,
            COALESCE(wt.withdrawals_count, 0) AS withdrawals_count,
            COALESCE(wt.withdrawals_total, 0) AS withdrawals_total
        FROM report_keys k
        LEFT JOIN unique_customer_counts u ON u.activity_date = k.activity_date AND u.country = k.country
        LEFT JOIN deposit_totals dt ON dt.activity_date = k.activity_date AND dt.country = k.country
        LEFT JOIN withdrawal_totals wt ON wt.activity_date = k.activity_date AND wt.country = k.country
        ORDER BY k.activity_date DESC, k.country ASC
        LIMIT :limit OFFSET :offset
        SQL;

    public function __construct(private PDO $pdo) {}

    /**
     * @return LengthAwarePaginator<int, DepositWithdrawalReportRow>
     */
    public function summarize(CarbonImmutable $from, CarbonImmutable $to, int $page, int $perPage): LengthAwarePaginator
    {
        $total = $this->countGroups($from, $to);
        $items = $this->fetchPage($from, $to, $page, $perPage);

        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    private function countGroups(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $statement = $this->pdo->prepare(self::ACTIVITY_CTE.' SELECT COUNT(*) FROM report_keys');
        $this->bindRange($statement, $from, $to);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<DepositWithdrawalReportRow>
     */
    private function fetchPage(CarbonImmutable $from, CarbonImmutable $to, int $page, int $perPage): array
    {
        $statement = $this->pdo->prepare(self::ACTIVITY_CTE.self::PAGE_QUERY_SUFFIX);
        $this->bindRange($statement, $from, $to);
        $statement->bindValue('limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue('offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();

        $rows = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $rows[] = DepositWithdrawalReportRow::fromDatabaseRow($row);
        }

        return $rows;
    }

    /**
     * The upper bound is the day after $to, exclusive, so the full day of
     * $to is included regardless of what time of day it currently is.
     */
    private function bindRange(PDOStatement $statement, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $rangeStart = $from->startOfDay()->toDateTimeString();
        $rangeEnd = $to->startOfDay()->addDay()->toDateTimeString();

        $statement->bindValue('deposit_status', DepositStatus::Approved->value, PDO::PARAM_INT);
        $statement->bindValue('deposit_from', $rangeStart, PDO::PARAM_STR);
        $statement->bindValue('deposit_to', $rangeEnd, PDO::PARAM_STR);
        $statement->bindValue('withdrawal_status', WithdrawStatus::Approved->value, PDO::PARAM_INT);
        $statement->bindValue('withdrawal_from', $rangeStart, PDO::PARAM_STR);
        $statement->bindValue('withdrawal_to', $rangeEnd, PDO::PARAM_STR);
    }
}
