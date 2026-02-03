<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'expense_number',
        'title',
        'description',
        'price',
        'expense_date',
        'category_id',
        'is_recurring',
        'recurring_id',
        'product_id',
        'quantity',
        'status',
        'paid_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'expense_date' => 'date',
        'is_recurring' => 'boolean',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * Get the recurrence (if this expense is from a recurring expense)
     */
    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(ExpenseRecurrence::class, 'recurring_id');
    }

    /**
     * Get the product (if this is a product expense)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Check if this expense is a product expense (stock-in)
     */
    public function isProductExpense(): bool
    {
        return $this->product_id !== null && $this->quantity !== null;
    }

    /**
     * Get the attachments
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ExpenseAttachment::class, 'expense_id');
    }

    /**
     * Get the user who created this expense
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this expense
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if expense is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if expense is unpaid
     */
    public function isUnpaid(): bool
    {
        return $this->status === 'unpaid';
    }

    /**
     * Mark expense as paid
     */
    public function markAsPaid(): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Mark expense as unpaid
     */
    public function markAsUnpaid(): void
    {
        $this->update([
            'status' => 'unpaid',
            'paid_at' => null,
        ]);
    }

    /**
     * Scope to get only paid expenses
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope to get only unpaid expenses
     */
    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    /**
     * Scope to get expenses by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get recurring expenses
     */
    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true);
    }

    /**
     * Scope to get expenses by category
     */
    public function scopeByCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }
}
