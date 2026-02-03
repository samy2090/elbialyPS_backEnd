<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRecurrence;
use Illuminate\Support\Facades\DB;

class ExpenseReportService
{
    /**
     * Get summary report for a date range
     */
    public function getSummary(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Expense::query();

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        $totalExpenses = $query->count();
        $totalAmount = $query->sum('price');
        $paidAmount = $query->where('status', 'paid')->sum('price');
        $unpaidAmount = $query->where('status', 'unpaid')->sum('price');
        $paidCount = $query->where('status', 'paid')->count();
        $unpaidCount = $query->where('status', 'unpaid')->count();

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'totals' => [
                'total_expenses' => $totalExpenses,
                'total_amount' => round((float) $totalAmount, 2),
                'paid_amount' => round((float) $paidAmount, 2),
                'unpaid_amount' => round((float) $unpaidAmount, 2),
                'paid_count' => $paidCount,
                'unpaid_count' => $unpaidCount,
            ],
        ];
    }

    /**
     * Get expenses grouped by category
     */
    public function getByCategory(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Expense::query()
            ->select(
                'category_id',
                DB::raw('COUNT(*) as expense_count'),
                DB::raw('SUM(price) as total_amount'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN price ELSE 0 END) as paid_amount'),
                DB::raw('SUM(CASE WHEN status = "unpaid" THEN price ELSE 0 END) as unpaid_amount')
            )
            ->groupBy('category_id');

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        $results = $query->get();

        $categories = ExpenseCategory::whereIn('id', $results->pluck('category_id'))->get()->keyBy('id');

        return $results->map(function ($item) use ($categories) {
            $category = $categories->get($item->category_id);
            return [
                'category_id' => $item->category_id,
                'category_name' => $category ? $category->name : 'Unknown',
                'category_path' => $category ? $category->full_path : 'Unknown',
                'expense_count' => $item->expense_count,
                'total_amount' => round((float) $item->total_amount, 2),
                'paid_amount' => round((float) $item->paid_amount, 2),
                'unpaid_amount' => round((float) $item->unpaid_amount, 2),
            ];
        })->toArray();
    }

    /**
     * Get paid vs unpaid breakdown
     */
    public function getPaidVsUnpaid(?string $startDate = null, ?string $endDate = null): array
    {
        $query = Expense::query();

        if ($startDate && $endDate) {
            $query->dateRange($startDate, $endDate);
        }

        $paid = $query->clone()->where('status', 'paid');
        $unpaid = $query->clone()->where('status', 'unpaid');

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'paid' => [
                'count' => $paid->count(),
                'total_amount' => round((float) $paid->sum('price'), 2),
            ],
            'unpaid' => [
                'count' => $unpaid->count(),
                'total_amount' => round((float) $unpaid->sum('price'), 2),
            ],
        ];
    }

    /**
     * Get monthly summary for a year
     */
    public function getMonthlySummary(int $year): array
    {
        $results = Expense::query()
            ->select(
                DB::raw('MONTH(expense_date) as month'),
                DB::raw('COUNT(*) as expense_count'),
                DB::raw('SUM(price) as total_amount'),
                DB::raw('SUM(CASE WHEN status = "paid" THEN price ELSE 0 END) as paid_amount'),
                DB::raw('SUM(CASE WHEN status = "unpaid" THEN price ELSE 0 END) as unpaid_amount')
            )
            ->whereYear('expense_date', $year)
            ->groupBy(DB::raw('MONTH(expense_date)'))
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $data = $results->get($i);
            $months[] = [
                'month' => $i,
                'month_name' => date('F', mktime(0, 0, 0, $i, 1)),
                'expense_count' => $data ? $data->expense_count : 0,
                'total_amount' => $data ? round((float) $data->total_amount, 2) : 0,
                'paid_amount' => $data ? round((float) $data->paid_amount, 2) : 0,
                'unpaid_amount' => $data ? round((float) $data->unpaid_amount, 2) : 0,
            ];
        }

        return [
            'year' => $year,
            'months' => $months,
            'yearly_total' => [
                'expense_count' => array_sum(array_column($months, 'expense_count')),
                'total_amount' => round(array_sum(array_column($months, 'total_amount')), 2),
                'paid_amount' => round(array_sum(array_column($months, 'paid_amount')), 2),
                'unpaid_amount' => round(array_sum(array_column($months, 'unpaid_amount')), 2),
            ],
        ];
    }

    /**
     * Get upcoming recurring expenses (next_payment_date within next N days)
     */
    public function getUpcomingRecurring(int $days = 30): array
    {
        $recurrences = ExpenseRecurrence::dueWithin($days)
            ->with('category')
            ->orderBy('next_payment_date')
            ->get();

        $upcoming = [];
        $now = now();

        foreach ($recurrences as $recurrence) {
            $nextPayment = $recurrence->next_payment_date;
            if (!$nextPayment) {
                continue;
            }
            $nextPaymentCarbon = \Carbon\Carbon::parse($nextPayment);

            $upcoming[] = [
                'id' => $recurrence->id,
                'title' => $recurrence->title,
                'price' => round((float) $recurrence->price, 2),
                'category' => [
                    'id' => $recurrence->category->id,
                    'name' => $recurrence->category->name,
                    'path' => $recurrence->category->full_path,
                ],
                'frequency' => $recurrence->frequency,
                'due_day' => $recurrence->due_day,
                'next_payment_date' => $nextPaymentCarbon->format('Y-m-d'),
                'days_until_due' => (int) $now->diffInDays($nextPaymentCarbon, false),
                'last_reminded_at' => $recurrence->last_reminded_at ? $recurrence->last_reminded_at->format('Y-m-d H:i:s') : null,
            ];
        }

        usort($upcoming, function ($a, $b) {
            return $a['next_payment_date'] <=> $b['next_payment_date'];
        });

        return $upcoming;
    }

    /**
     * Get overdue recurring expenses (next_payment_date in the past)
     */
    public function getOverdueRecurring(): array
    {
        $recurrences = ExpenseRecurrence::overdue()
            ->with('category')
            ->orderBy('next_payment_date')
            ->get();

        $overdue = [];
        $now = now();

        foreach ($recurrences as $recurrence) {
            $nextPayment = $recurrence->next_payment_date;
            if (!$nextPayment) {
                continue;
            }
            $nextPaymentCarbon = \Carbon\Carbon::parse($nextPayment);

            $overdue[] = [
                'id' => $recurrence->id,
                'title' => $recurrence->title,
                'price' => round((float) $recurrence->price, 2),
                'category' => [
                    'id' => $recurrence->category->id,
                    'name' => $recurrence->category->name,
                    'path' => $recurrence->category->full_path,
                ],
                'frequency' => $recurrence->frequency,
                'due_day' => $recurrence->due_day,
                'next_payment_date' => $nextPaymentCarbon->format('Y-m-d'),
                'days_overdue' => (int) $now->diffInDays($nextPaymentCarbon),
                'last_reminded_at' => $recurrence->last_reminded_at ? $recurrence->last_reminded_at->format('Y-m-d H:i:s') : null,
            ];
        }

        usort($overdue, function ($a, $b) {
            return $b['days_overdue'] <=> $a['days_overdue'];
        });

        return $overdue;
    }
}
