<?php

namespace App\Observers;

use App\Models\ExpenseAttachment;
use Illuminate\Support\Facades\Storage;

class ExpenseAttachmentObserver
{
    /**
     * Handle the ExpenseAttachment "creating" event.
     */
    public function creating(ExpenseAttachment $expenseAttachment): void
    {
        if (auth()->check()) {
            $expenseAttachment->created_by = auth()->id();
            $expenseAttachment->updated_by = auth()->id();
        }
    }

    /**
     * Handle the ExpenseAttachment "updating" event.
     */
    public function updating(ExpenseAttachment $expenseAttachment): void
    {
        if (auth()->check()) {
            $expenseAttachment->updated_by = auth()->id();
        }
    }

    /**
     * Handle the ExpenseAttachment "deleted" event.
     */
    public function deleted(ExpenseAttachment $expenseAttachment): void
    {
        // Delete the file from storage when attachment is soft deleted
        if ($expenseAttachment->file_path && Storage::disk('public')->exists($expenseAttachment->file_path)) {
            Storage::disk('public')->delete($expenseAttachment->file_path);
        }
    }

    /**
     * Handle the ExpenseAttachment "force deleted" event.
     */
    public function forceDeleted(ExpenseAttachment $expenseAttachment): void
    {
        // Delete the file from storage when attachment is permanently deleted
        if ($expenseAttachment->file_path && Storage::disk('public')->exists($expenseAttachment->file_path)) {
            Storage::disk('public')->delete($expenseAttachment->file_path);
        }
    }
}
