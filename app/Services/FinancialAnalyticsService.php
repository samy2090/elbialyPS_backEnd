<?php

namespace App\Services;

use App\Enums\FinancialPeriod;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FinancialAnalyticsService
{
    private const SESSIONS_TABLE      = 'game_sessions';
    private const ACTIVITIES_TABLE    = 'session_activities';
    private const ACTIVITY_PROD_TABLE = 'activity_products';
    private const PRODUCTS_TABLE      = 'products';
    private const EXPENSES_TABLE      = 'expenses';
    private const EXPENSE_CAT_TABLE   = 'expense_categories';

    private const SESSION_DATE_COL = 'ended_at';
    private const EXPENSE_DATE_COL = 'COALESCE(paid_at, expense_date)';

    private const CACHE_TTL = 60;

    public function getRevenues(FinancialPeriod $period, ?string $from, ?string $to): array
    {
        [$start, $end] = $this->resolveRange($period, $from, $to);

        return $this->remember(['revenues', $period->value, $start, $end], $end, function () use ($period, $start, $end) {
            $rows = $this->fetchRevenueBuckets($period, $start, $end);
            $buckets = $this->fillBuckets($period, $start, $end, $rows, fn ($row) => [
                'gross_revenue' => $this->money($row->gross_revenue),
                'discount'      => $this->money($row->discount),
                'net_revenue'   => $this->money($row->net_revenue),
                'session_count' => (int) $row->session_count,
            ], [
                'gross_revenue' => 0.0,
                'discount'      => 0.0,
                'net_revenue'   => 0.0,
                'session_count' => 0,
            ]);

            return [
                'period'  => $period->value,
                'range'   => $this->rangePayload($start, $end),
                'buckets' => $buckets,
                'totals'  => $this->sumNumericColumns($buckets, ['gross_revenue', 'discount', 'net_revenue', 'session_count']),
            ];
        });
    }

    public function getProfits(FinancialPeriod $period, ?string $from, ?string $to, string $mode = 'actual'): array
    {
        [$start, $end] = $this->resolveRange($period, $from, $to);
        $smoothed = ($mode === 'smoothed') && in_array($period, [FinancialPeriod::Daily, FinancialPeriod::Weekly], true);

        return $this->remember(['profits', $period->value, $start, $end, $smoothed ? 'smoothed' : 'actual'], $end, function () use ($period, $start, $end, $smoothed) {
            $revenueByBucket  = $this->indexBy($this->fetchRevenueBuckets($period, $start, $end), 'bucket');
            $variableByBucket = $this->indexBy($this->fetchExpenseBuckets($period, $start, $end, isRecurring: false), 'bucket');
            $cogsByBucket     = $this->indexBy($this->fetchCogsBuckets($period, $start, $end), 'bucket');
            $fixedByBucket    = $smoothed
                ? $this->smoothFixedExpenses($period, $start, $end)
                : $this->indexBy($this->fetchExpenseBuckets($period, $start, $end, isRecurring: true), 'bucket');

            $buckets = $this->fillBuckets($period, $start, $end, collect(), function () { return []; }, []);

            $buckets = array_map(function (array $bucket) use ($revenueByBucket, $variableByBucket, $cogsByBucket, $fixedByBucket) {
                $key = $bucket['bucket_start'];
                $netRevenue       = $this->money(optional($revenueByBucket[$key]  ?? null)->net_revenue   ?? 0);
                $grossRevenue     = $this->money(optional($revenueByBucket[$key]  ?? null)->gross_revenue ?? 0);
                $discount         = $this->money(optional($revenueByBucket[$key]  ?? null)->discount      ?? 0);
                $variableExpenses = $this->money(optional($variableByBucket[$key] ?? null)->amount        ?? 0);
                $productCogs      = $this->money(optional($cogsByBucket[$key]     ?? null)->amount        ?? 0);

                $fixedExpenses = is_array($fixedByBucket)
                    ? $this->money($fixedByBucket[$key] ?? 0)
                    : $this->money(optional($fixedByBucket[$key] ?? null)->amount ?? 0);

                $operatingProfit = round($netRevenue - $productCogs - $variableExpenses, 2);
                $netProfit       = round($operatingProfit - $fixedExpenses, 2);

                return array_merge($bucket, [
                    'gross_revenue'     => $grossRevenue,
                    'discount'          => $discount,
                    'net_revenue'       => $netRevenue,
                    'product_cogs'      => $productCogs,
                    'variable_expenses' => $variableExpenses,
                    'fixed_expenses'    => $fixedExpenses,
                    'operating_profit'  => $operatingProfit,
                    'operating_margin'  => $this->margin($operatingProfit, $netRevenue),
                    'net_profit'        => $netProfit,
                    'net_margin'        => $this->margin($netProfit, $netRevenue),
                ]);
            }, $buckets);

            return [
                'period'  => $period->value,
                'mode'    => $smoothed ? 'smoothed' : 'actual',
                'range'   => $this->rangePayload($start, $end),
                'buckets' => $buckets,
                'totals'  => $this->profitTotals($buckets),
            ];
        });
    }

    public function getSummary(?string $from, ?string $to): array
    {
        [$start, $end] = $this->resolveExactRange(FinancialPeriod::Monthly, $from, $to);

        return $this->remember(['summary', $start, $end], $end, function () use ($start, $end) {
            $revenue = DB::table(self::SESSIONS_TABLE)
                ->selectRaw('COUNT(*) AS session_count')
                ->selectRaw('COALESCE(SUM(total_price + discount), 0) AS gross_revenue')
                ->selectRaw('COALESCE(SUM(discount), 0) AS discount')
                ->selectRaw('COALESCE(SUM(total_price), 0) AS net_revenue')
                ->where('status', 'ended')
                ->whereBetween(self::SESSION_DATE_COL, [$start, $end])
                ->first();

            // Operating expenses only — stock-purchase expenses are excluded
            // (those costs hit profit via COGS-at-sale below to avoid double-counting).
            $expenses = DB::table(self::EXPENSES_TABLE)
                ->selectRaw('COALESCE(SUM(CASE WHEN is_recurring = 0 THEN price ELSE 0 END), 0) AS variable_amount')
                ->selectRaw('COALESCE(SUM(CASE WHEN is_recurring = 1 THEN price ELSE 0 END), 0) AS fixed_amount')
                ->selectRaw('SUM(CASE WHEN is_recurring = 0 THEN 1 ELSE 0 END) AS variable_count')
                ->selectRaw('SUM(CASE WHEN is_recurring = 1 THEN 1 ELSE 0 END) AS fixed_count')
                ->where('status', 'paid')
                ->where(function ($q) {
                    $q->whereNull('product_id')->orWhereNull('quantity');
                })
                ->whereBetween(DB::raw(self::EXPENSE_DATE_COL), [$start, $end])
                ->first();

            // COGS for products sold in the range — uses current products.cost.
            $productCogs = (float) DB::table(self::ACTIVITY_PROD_TABLE . ' as ap')
                ->join(self::ACTIVITIES_TABLE . ' as sa', 'sa.id', '=', 'ap.session_activity_id')
                ->join(self::SESSIONS_TABLE . ' as gs', 'gs.id', '=', 'sa.session_id')
                ->leftJoin(self::PRODUCTS_TABLE . ' as p', 'p.id', '=', 'ap.product_id')
                ->where('gs.status', 'ended')
                ->whereBetween('gs.' . self::SESSION_DATE_COL, [$start, $end])
                ->sum(DB::raw('COALESCE(p.cost, 0) * ap.quantity'));

            $netRevenue       = $this->money($revenue->net_revenue);
            $variableExpenses = $this->money($expenses->variable_amount);
            $fixedExpenses    = $this->money($expenses->fixed_amount);
            $cogs             = $this->money($productCogs);
            $operatingProfit  = round($netRevenue - $cogs - $variableExpenses, 2);
            $netProfit        = round($operatingProfit - $fixedExpenses, 2);

            return [
                'range' => $this->rangePayload($start, $end),
                'revenue' => [
                    'session_count' => (int) $revenue->session_count,
                    'gross_revenue' => $this->money($revenue->gross_revenue),
                    'discount'      => $this->money($revenue->discount),
                    'net_revenue'   => $netRevenue,
                ],
                'cogs' => [
                    'product_cogs' => $cogs,
                ],
                'expenses' => [
                    'variable_count'  => (int) $expenses->variable_count,
                    'variable_amount' => $variableExpenses,
                    'fixed_count'     => (int) $expenses->fixed_count,
                    'fixed_amount'    => $fixedExpenses,
                    'total_amount'    => round($variableExpenses + $fixedExpenses, 2),
                ],
                'profit' => [
                    'operating_profit' => $operatingProfit,
                    'operating_margin' => $this->margin($operatingProfit, $netRevenue),
                    'net_profit'       => $netProfit,
                    'net_margin'       => $this->margin($netProfit, $netRevenue),
                ],
            ];
        });
    }

    public function getBreakdown(?string $from, ?string $to): array
    {
        [$start, $end] = $this->resolveExactRange(FinancialPeriod::Monthly, $from, $to);

        return $this->remember(['breakdown', $start, $end], $end, function () use ($start, $end) {
            // session_activities.total_price is stored as (device_usage + products_in_activity)
            // per the team's pricing logic — see PRICING_FIXES_SESSION_TOTAL.md. So summing
            // it directly would double-count products. We subtract the products total to
            // recover the pure play-time revenue.
            $activitiesTotal = (float) DB::table(self::ACTIVITIES_TABLE . ' as sa')
                ->join(self::SESSIONS_TABLE . ' as gs', 'gs.id', '=', 'sa.session_id')
                ->where('gs.status', 'ended')
                ->whereBetween('gs.' . self::SESSION_DATE_COL, [$start, $end])
                ->sum('sa.total_price');

            $productRevenue = (float) DB::table(self::ACTIVITY_PROD_TABLE . ' as ap')
                ->join(self::ACTIVITIES_TABLE . ' as sa', 'sa.id', '=', 'ap.session_activity_id')
                ->join(self::SESSIONS_TABLE . ' as gs', 'gs.id', '=', 'sa.session_id')
                ->where('gs.status', 'ended')
                ->whereBetween('gs.' . self::SESSION_DATE_COL, [$start, $end])
                ->sum('ap.total_price');

            $rentalRevenue = max(0, round($activitiesTotal - $productRevenue, 2));

            // Cost of goods sold for products sold in the period. Uses the CURRENT
            // products.cost (cost is not snapshotted on activity_products at sale time),
            // so if cost has changed since the sale, this is an approximation. Products
            // with a deleted/missing cost contribute 0 to COGS.
            $productCogs = (float) DB::table(self::ACTIVITY_PROD_TABLE . ' as ap')
                ->join(self::ACTIVITIES_TABLE . ' as sa', 'sa.id', '=', 'ap.session_activity_id')
                ->join(self::SESSIONS_TABLE . ' as gs', 'gs.id', '=', 'sa.session_id')
                ->leftJoin(self::PRODUCTS_TABLE . ' as p', 'p.id', '=', 'ap.product_id')
                ->where('gs.status', 'ended')
                ->whereBetween('gs.' . self::SESSION_DATE_COL, [$start, $end])
                ->sum(DB::raw('COALESCE(p.cost, 0) * ap.quantity'));

            $expensesByCategory = DB::table(self::EXPENSES_TABLE . ' as e')
                ->leftJoin(self::EXPENSE_CAT_TABLE . ' as ec', 'ec.id', '=', 'e.category_id')
                ->selectRaw('ec.id AS category_id')
                ->selectRaw('COALESCE(ec.name, "Uncategorized") AS category_name')
                ->selectRaw('COUNT(*) AS expense_count')
                ->selectRaw('COALESCE(SUM(e.price), 0) AS amount')
                ->selectRaw('COALESCE(SUM(CASE WHEN e.is_recurring = 1 THEN e.price ELSE 0 END), 0) AS fixed_amount')
                ->selectRaw('COALESCE(SUM(CASE WHEN e.is_recurring = 0 THEN e.price ELSE 0 END), 0) AS variable_amount')
                ->where('e.status', 'paid')
                ->whereBetween(DB::raw(self::EXPENSE_DATE_COL_ALIAS('e')), [$start, $end])
                ->groupBy('ec.id', 'ec.name')
                ->orderByDesc('amount')
                ->get()
                ->map(fn ($row) => [
                    'category_id'     => $row->category_id !== null ? (int) $row->category_id : null,
                    'category_name'   => (string) $row->category_name,
                    'expense_count'   => (int) $row->expense_count,
                    'amount'          => $this->money($row->amount),
                    'fixed_amount'    => $this->money($row->fixed_amount),
                    'variable_amount' => $this->money($row->variable_amount),
                ])
                ->all();

            $totalRevenue       = round($rentalRevenue + $productRevenue, 2);
            $productGrossMargin = round($productRevenue - $productCogs, 2);

            return [
                'range' => $this->rangePayload($start, $end),
                'revenue_breakdown' => [
                    'rental_revenue'  => $this->money($rentalRevenue),
                    'product_revenue' => $this->money($productRevenue),
                    'total_revenue'   => $totalRevenue,
                    'rental_share'    => $this->share($rentalRevenue, $totalRevenue),
                    'product_share'   => $this->share($productRevenue, $totalRevenue),
                    // Product profitability — informational. COGS uses CURRENT products.cost
                    // since it's not snapshotted at sale time. Does NOT affect the main
                    // profit calculation; product purchases hit profit via the expense
                    // ledger when stock is bought (see project_financial_analytics_rules.md).
                    'product_cogs'         => $this->money($productCogs),
                    'product_gross_margin' => $productGrossMargin,
                    'product_margin_pct'   => $this->margin($productGrossMargin, $productRevenue),
                ],
                'expenses_by_category' => $expensesByCategory,
            ];
        });
    }

    private static function EXPENSE_DATE_COL_ALIAS(string $alias): string
    {
        return "COALESCE({$alias}.paid_at, {$alias}.expense_date)";
    }

    private function fetchRevenueBuckets(FinancialPeriod $period, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $bucketExpr = $period->bucketExpression(self::SESSION_DATE_COL);

        return DB::table(self::SESSIONS_TABLE)
            ->selectRaw("{$bucketExpr} AS bucket")
            ->selectRaw('COUNT(*) AS session_count')
            ->selectRaw('COALESCE(SUM(total_price + discount), 0) AS gross_revenue')
            ->selectRaw('COALESCE(SUM(discount), 0) AS discount')
            ->selectRaw('COALESCE(SUM(total_price), 0) AS net_revenue')
            ->where('status', 'ended')
            ->whereBetween(self::SESSION_DATE_COL, [$start, $end])
            ->groupBy(DB::raw($bucketExpr))
            ->orderBy('bucket')
            ->get();
    }

    private function fetchExpenseBuckets(FinancialPeriod $period, CarbonInterface $start, CarbonInterface $end, bool $isRecurring): Collection
    {
        $dateExpr   = self::EXPENSE_DATE_COL;
        $bucketExpr = $period->bucketExpression($dateExpr);

        // Exclude stock-purchase expenses (product_id + quantity set). Per the
        // COGS-at-sale model, those costs hit profit when the product is SOLD,
        // not when stock is bought. Including them here would double-count COGS.
        return DB::table(self::EXPENSES_TABLE)
            ->selectRaw("{$bucketExpr} AS bucket")
            ->selectRaw('COUNT(*) AS expense_count')
            ->selectRaw('COALESCE(SUM(price), 0) AS amount')
            ->where('status', 'paid')
            ->where('is_recurring', $isRecurring)
            ->where(function ($q) {
                $q->whereNull('product_id')->orWhereNull('quantity');
            })
            ->whereBetween(DB::raw($dateExpr), [$start, $end])
            ->groupBy(DB::raw($bucketExpr))
            ->orderBy('bucket')
            ->get();
    }

    /**
     * COGS per bucket: for products sold inside ended sessions, sum
     * (quantity × current products.cost). Bucketed by the session's ended_at
     * (revenue-recognition date) so COGS lines up with the revenue it offsets.
     * Uses the CURRENT products.cost since cost is not snapshotted at sale.
     */
    private function fetchCogsBuckets(FinancialPeriod $period, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $bucketExpr = $period->bucketExpression('gs.' . self::SESSION_DATE_COL);

        return DB::table(self::ACTIVITY_PROD_TABLE . ' as ap')
            ->join(self::ACTIVITIES_TABLE . ' as sa', 'sa.id', '=', 'ap.session_activity_id')
            ->join(self::SESSIONS_TABLE . ' as gs', 'gs.id', '=', 'sa.session_id')
            ->leftJoin(self::PRODUCTS_TABLE . ' as p', 'p.id', '=', 'ap.product_id')
            ->selectRaw("{$bucketExpr} AS bucket")
            ->selectRaw('COALESCE(SUM(COALESCE(p.cost, 0) * ap.quantity), 0) AS amount')
            ->where('gs.status', 'ended')
            ->whereBetween('gs.' . self::SESSION_DATE_COL, [$start, $end])
            ->groupBy(DB::raw($bucketExpr))
            ->orderBy('bucket')
            ->get();
    }

    /**
     * Smoothed mode: each fixed expense's amount is amortized across the
     * calendar month it was paid in. Per-day allocation = amount / days_in_month.
     * Per-bucket allocation = sum of per-day allocations for days inside the
     * bucket window (intersected with the overall query range).
     */
    private function smoothFixedExpenses(FinancialPeriod $period, CarbonInterface $start, CarbonInterface $end): array
    {
        $expandedStart = $start->copy()->startOfMonth();
        $expandedEnd   = $end->copy()->endOfMonth();

        $expenses = DB::table(self::EXPENSES_TABLE)
            ->selectRaw('price')
            ->selectRaw(self::EXPENSE_DATE_COL . ' AS effective_date')
            ->where('status', 'paid')
            ->where('is_recurring', true)
            ->whereBetween(DB::raw(self::EXPENSE_DATE_COL), [$expandedStart, $expandedEnd])
            ->get();

        $dailyAllocation = [];
        foreach ($expenses as $expense) {
            $paidDate    = Carbon::parse($expense->effective_date);
            $monthStart  = $paidDate->copy()->startOfMonth();
            $monthEnd    = $paidDate->copy()->endOfMonth();
            $daysInMonth = $monthStart->daysInMonth;
            $perDay      = ((float) $expense->price) / $daysInMonth;

            for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
                if ($day->lt($start) || $day->gt($end)) {
                    continue;
                }
                $key = $day->format('Y-m-d');
                $dailyAllocation[$key] = ($dailyAllocation[$key] ?? 0) + $perDay;
            }
        }

        $bucketAllocation = [];
        foreach ($dailyAllocation as $dateKey => $amount) {
            $bucketStart = $period->startOf(Carbon::parse($dateKey))->format('Y-m-d');
            $bucketAllocation[$bucketStart] = ($bucketAllocation[$bucketStart] ?? 0) + $amount;
        }

        return $bucketAllocation;
    }

    private function resolveRange(FinancialPeriod $period, ?string $from, ?string $to): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        if ($from && $to) {
            $start = Carbon::parse($from, config('app.timezone'))->startOfDay();
            $end   = Carbon::parse($to, config('app.timezone'))->endOfDay();
        } else {
            [$start, $end] = $period->defaultRange($now);
        }

        $start = $period->startOf($start);
        $end   = $period->endOf($end);

        return [$start, $end];
    }

    /**
     * Like resolveRange but honors the user's exact dates without snapping to
     * the period boundary. Used by non-bucketed endpoints (summary, breakdown)
     * so a request for 2026-02-05..2026-02-10 doesn't get widened to the
     * surrounding whole month.
     */
    private function resolveExactRange(FinancialPeriod $period, ?string $from, ?string $to): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));

        if ($from && $to) {
            return [
                Carbon::parse($from, config('app.timezone'))->startOfDay(),
                Carbon::parse($to, config('app.timezone'))->endOfDay(),
            ];
        }

        return $period->defaultRange($now);
    }

    /**
     * Build a complete bucket series with zero-filled gaps. Period-aware:
     * advances by the period's natural step (day/week/month/year).
     */
    private function fillBuckets(FinancialPeriod $period, CarbonInterface $start, CarbonInterface $end, Collection $rows, callable $row, array $defaults): array
    {
        $indexed = $rows->keyBy(function ($r) {
            return Carbon::parse($r->bucket)->format('Y-m-d');
        });

        $buckets = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor = $period->next($cursor)) {
            $bucketStart = $period->startOf($cursor);
            $bucketEnd   = $period->endOf($cursor);
            $key         = $bucketStart->format('Y-m-d');

            $data = $indexed->has($key) ? $row($indexed->get($key)) : $defaults;

            $buckets[] = array_merge([
                'label'        => $period->label($bucketStart),
                'bucket_start' => $bucketStart->format('Y-m-d'),
                'bucket_end'   => $bucketEnd->format('Y-m-d'),
            ], $data);
        }

        return $buckets;
    }

    private function indexBy(Collection $rows, string $key): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[Carbon::parse($row->{$key})->format('Y-m-d')] = $row;
        }
        return $out;
    }

    private function sumNumericColumns(array $buckets, array $columns): array
    {
        $totals = array_fill_keys($columns, 0);
        foreach ($buckets as $bucket) {
            foreach ($columns as $col) {
                $totals[$col] += (float) ($bucket[$col] ?? 0);
            }
        }
        foreach ($columns as $col) {
            $totals[$col] = $col === 'session_count' ? (int) $totals[$col] : round((float) $totals[$col], 2);
        }
        return $totals;
    }

    private function profitTotals(array $buckets): array
    {
        $sums = $this->sumNumericColumns($buckets, [
            'gross_revenue', 'discount', 'net_revenue',
            'product_cogs',
            'variable_expenses', 'fixed_expenses',
            'operating_profit', 'net_profit',
        ]);

        return array_merge($sums, [
            'operating_margin' => $this->margin($sums['operating_profit'], $sums['net_revenue']),
            'net_margin'       => $this->margin($sums['net_profit'], $sums['net_revenue']),
        ]);
    }

    private function money($value): float
    {
        return round((float) $value, 2);
    }

    private function margin(float $numerator, float $denominator): ?float
    {
        if ($denominator <= 0) {
            return null;
        }
        return round(($numerator / $denominator) * 100, 2);
    }

    private function share(float $part, float $whole): ?float
    {
        if ($whole <= 0) {
            return null;
        }
        return round(($part / $whole) * 100, 2);
    }

    private function rangePayload(CarbonInterface $start, CarbonInterface $end): array
    {
        return [
            'from'     => $start->format('Y-m-d'),
            'to'       => $end->format('Y-m-d'),
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * Cache only when the requested range is fully in the past. Current/future
     * buckets stay live so freshly ended sessions and freshly paid expenses
     * show up immediately.
     */
    private function remember(array $keyParts, CarbonInterface $rangeEnd, \Closure $compute)
    {
        $now = Carbon::now(config('app.timezone'));
        if ($rangeEnd->greaterThanOrEqualTo($now->startOfDay())) {
            return $compute();
        }

        $key = 'fin_analytics:' . md5(json_encode(array_map(
            fn ($p) => $p instanceof CarbonInterface ? $p->toIso8601String() : $p,
            $keyParts
        )));

        return Cache::remember($key, self::CACHE_TTL, $compute);
    }
}
