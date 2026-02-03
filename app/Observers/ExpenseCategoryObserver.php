<?php

namespace App\Observers;

use App\Models\ExpenseCategory;

class ExpenseCategoryObserver
{
    /**
     * Handle the ExpenseCategory "creating" event.
     */
    public function creating(ExpenseCategory $expenseCategory): void
    {
        if (auth()->check()) {
            $expenseCategory->created_by = auth()->id();
            $expenseCategory->updated_by = auth()->id();
        }
    }

    /**
     * Handle the ExpenseCategory "updating" event.
     */
    public function updating(ExpenseCategory $expenseCategory): void
    {
        if (auth()->check()) {
            $expenseCategory->updated_by = auth()->id();
        }
    }

    /**
     * Handle the ExpenseCategory "deleting" event.
     */
    public function deleting(ExpenseCategory $expenseCategory): bool
    {
        // Prevent deletion if category has expenses
        if ($expenseCategory->hasExpenses()) {
            throw new \Exception('Cannot delete category with existing expenses. Please mark it as inactive instead.');
        }

        // Prevent deletion if category has sub-categories
        if ($expenseCategory->hasChildren()) {
            throw new \Exception('Cannot delete category with sub-categories. Please delete or reassign sub-categories first.');
        }

        return true;
    }
}
