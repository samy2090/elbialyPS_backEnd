<?php

namespace App\Observers;

use App\Models\Expense;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExpenseObserver
{
    /**
     * Handle the Expense "creating" event.
     */
    public function creating(Expense $expense): void
    {
        if (auth()->check()) {
            $expense->created_by = auth()->id();
            $expense->updated_by = auth()->id();
        }

        // Auto-generate expense number if not provided
        if (empty($expense->expense_number)) {
            $expense->expense_number = $this->generateExpenseNumber();
        }

        // Auto-set paid_at if status is paid and paid_at is not set
        if ($expense->status === 'paid' && !$expense->paid_at) {
            $expense->paid_at = now();
        }
    }

    /**
     * Handle the Expense "updating" event.
     */
    public function updating(Expense $expense): void
    {
        if (auth()->check()) {
            $expense->updated_by = auth()->id();
        }

        // Auto-set paid_at when status changes to paid
        if ($expense->isDirty('status')) {
            if ($expense->status === 'paid' && !$expense->paid_at) {
                $expense->paid_at = now();
            } elseif ($expense->status === 'unpaid') {
                $expense->paid_at = null;
            }
        }
    }

    /**
     * Generate unique expense number (EXP-YYYY-NNNN).
     * Uses a cache lock + DB transaction with row lock to prevent race conditions
     * when two expenses are created at the same time (including first of the year).
     */
    private function generateExpenseNumber(): string
    {
        $year = now()->year;
        $lockKey = "expense_number_gen_{$year}";

        return Cache::lock($lockKey, 10)->block(10, function () use ($year) {
            return DB::transaction(function () use ($year) {
                $prefix = "EXP-{$year}-";

                // Include soft-deleted: expense_number is unique for all rows, so we must not reuse a deleted number
                $lastExpense = Expense::withTrashed()
                    ->where('expense_number', 'like', $prefix . '%')
                    ->orderBy('expense_number', 'desc')
                    ->lockForUpdate()
                    ->first();

                $nextNumber = 1;
                if ($lastExpense && preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $lastExpense->expense_number, $matches)) {
                    $nextNumber = (int) $matches[1] + 1;
                }

                return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
            });
        });
    }

    /**
     * Handle the Expense "deleted" event (soft delete): reverse product stock.
     */
    public function deleted(Expense $expense): void
    {
        if ($expense->isProductExpense()) {
            Product::where('id', $expense->product_id)->decrement('stock', $expense->quantity);
        }
    }

    /**
     * Handle the Expense "restored" event: re-apply product stock.
     */
    public function restored(Expense $expense): void
    {
        if ($expense->isProductExpense()) {
            Product::where('id', $expense->product_id)->increment('stock', $expense->quantity);
        }
    }
}
