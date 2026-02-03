<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the parent category
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    /**
     * Get the sub-categories
     */
    public function children(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id');
    }

    /**
     * Get the expenses in this category
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    /**
     * Get the expense recurrences in this category
     */
    public function expenseRecurrences(): HasMany
    {
        return $this->hasMany(ExpenseRecurrence::class, 'category_id');
    }

    /**
     * Get the user who created this category
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this category
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if category is a main category (has no parent)
     */
    public function isMainCategory(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if category is a sub-category (has a parent)
     */
    public function isSubCategory(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Check if category has sub-categories
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Check if category has expenses
     */
    public function hasExpenses(): bool
    {
        return $this->expenses()->exists();
    }

    /**
     * Get full category path (e.g., "Office → Utilities → Internet")
     */
    public function getFullPathAttribute(): string
    {
        $path = [$this->name];
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            array_unshift($path, $current->name);
        }

        return implode(' → ', $path);
    }

    /**
     * Scope to get only main categories
     */
    public function scopeMainCategories($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope to get only sub-categories
     */
    public function scopeSubCategories($query)
    {
        return $query->whereNotNull('parent_id');
    }

    /**
     * Scope to get only active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the main "Products" category id (for product expenses).
     * Returns null if not found.
     */
    public static function getProductsCategoryId(): ?int
    {
        $cat = static::where('name', 'Products')->whereNull('parent_id')->first();
        return $cat ? (int) $cat->id : null;
    }
}
