<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseRecurrence extends Model
{
    protected $fillable = [
        'title',
        'price',
        'category_id',
        'frequency',
        'due_day',
        'start_date',
        'end_date',
        'last_reminded_at',
        'last_payment_date',
        'next_payment_date',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_reminded_at' => 'datetime',
        'last_payment_date' => 'date',
        'next_payment_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    /**
     * Get the expenses created from this recurrence
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'recurring_id');
    }

    /**
     * Check if recurrence is overdue (next_payment_date is in the past)
     */
    public function isOverdue(): bool
    {
        return $this->next_payment_date && Carbon::parse($this->next_payment_date)->isPast();
    }

    /**
     * Check if recurrence is due soon (next_payment_date in the future, within X days)
     */
    public function isDueSoon(int $days = 7): bool
    {
        if (!$this->next_payment_date) {
            return false;
        }
        $next = Carbon::parse($this->next_payment_date);
        return $next->isFuture() && $next->diffInDays(now()) <= $days;
    }

    /**
     * Scope to get only active recurrences
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get overdue recurrences (next_payment_date in the past)
     */
    public function scopeOverdue($query)
    {
        return $query->active()
            ->whereNotNull('next_payment_date')
            ->where('next_payment_date', '<', now()->format('Y-m-d'))
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->format('Y-m-d'));
            });
    }

    /**
     * Scope to get recurrences due within X days (next_payment_date in the future, within N days)
     */
    public function scopeDueWithin($query, int $days = 30)
    {
        $today = now()->format('Y-m-d');
        $futureLimit = now()->addDays($days)->format('Y-m-d');

        return $query->active()
            ->whereNotNull('next_payment_date')
            ->where('next_payment_date', '>=', $today)
            ->where('next_payment_date', '<=', $futureLimit)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now()->format('Y-m-d'));
            });
    }

    /**
     * Compute the first due date on or after start_date with day = due_day.
     * Used when creating a new recurrence (no payments yet).
     */
    public static function computeFirstDueDate(string $startDate, int $dueDay, string $frequency, ?string $endDate = null): ?Carbon
    {
        $start = Carbon::parse($startDate);
        $dueDay = max(1, min(31, $dueDay));

        if ($frequency === 'monthly') {
            // First occurrence: same month if start day <= due_day, else next month
            $first = $start->copy()->startOfMonth();
            $daysInMonth = $first->daysInMonth;
            $day = min($dueDay, $daysInMonth);
            $first->day($day);
            if ($first->lt($start)) {
                $first->addMonth();
                $day = min($dueDay, $first->daysInMonth);
                $first->day($day);
            }
        } else {
            // yearly: same month and day in start year, or next year if that date is before start
            $first = $start->copy();
            $daysInMonth = $first->daysInMonth;
            $day = min($dueDay, $daysInMonth);
            $first->day($day);
            if ($first->lt($start)) {
                $first->addYear();
                $day = min($dueDay, $first->daysInMonth);
                $first->day($day);
            }
        }

        if ($endDate && $first->gt(Carbon::parse($endDate))) {
            return null;
        }

        return $first;
    }

    /**
     * Compute the next due date after currentDueDate (one period ahead, day = due_day).
     * Used when confirming payment.
     */
    public static function computeNextDueDate(string $currentDueDate, int $dueDay, string $frequency, ?string $endDate = null): ?Carbon
    {
        $current = Carbon::parse($currentDueDate);
        $dueDay = max(1, min(31, $dueDay));

        if ($frequency === 'monthly') {
            $next = $current->copy()->addMonth();
            $day = min($dueDay, $next->daysInMonth);
            $next->day($day);
        } else {
            $next = $current->copy()->addYear();
            $day = min($dueDay, $next->daysInMonth);
            $next->day($day);
        }

        if ($endDate && $next->gt(Carbon::parse($endDate))) {
            return null;
        }

        return $next;
    }
}
