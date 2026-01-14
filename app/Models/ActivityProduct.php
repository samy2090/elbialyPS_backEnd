<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Session;
use App\Models\SessionActivity;

class ActivityProduct extends Model
{
    use HasFactory;

    protected $table = 'activity_products';

    protected $fillable = [
        'session_activity_id',
        'product_id',
        'quantity',
        'price',
        'total_price',
        'ordered_by_user_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the session activity this product order belongs to
     */
    public function sessionActivity(): BelongsTo
    {
        return $this->belongsTo(SessionActivity::class);
    }

    /**
     * Get the product that was ordered
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who ordered this product
     */
    public function orderedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_user_id');
    }

    /**
     * Boot the model
     */
    protected static function booted(): void
    {
        // Helper method to recalculate activity and session totals
        $recalculateTotals = function (ActivityProduct $activityProduct, $eventType = 'created') {
            $activity = SessionActivity::find($activityProduct->session_activity_id);
            if (!$activity) {
                return;
            }

            // Get current activity total before change
            $currentActivityTotal = (float) ($activity->total_price ?? 0);
            
            // Calculate old products total (before this change)
            $oldProductsTotal = (float) ($activity->products()->sum('total_price') ?? 0);
            
            // Adjust for the current change
            if ($eventType === 'created') {
                // productsTotal already includes the new product, so subtract it to get old total
                $oldProductsTotal = $oldProductsTotal - (float) $activityProduct->total_price;
            } elseif ($eventType === 'deleted') {
                // productsTotal doesn't include deleted product, so add it to get old total
                $oldProductsTotal = $oldProductsTotal + (float) $activityProduct->total_price;
            } elseif ($eventType === 'updated') {
                // productsTotal includes updated product, need to subtract the change
                $oldTotalPrice = (float) ($activityProduct->getOriginal('total_price') ?? $activityProduct->total_price);
                $oldProductsTotal = $oldProductsTotal - (float) $activityProduct->total_price + $oldTotalPrice;
            }
            
            // Calculate device usage price (preserved from before the change)
            $deviceUsagePrice = max(0, $currentActivityTotal - $oldProductsTotal);
            
            // Refresh activity to get latest data including products
            $activity->refresh();
            
            // Calculate new products total (after the change)
            $newProductsTotal = (float) ($activity->products()->sum('total_price') ?? 0);
            
            // For ended activities, recalculate full activity total using calculateDuration
            // This ensures device usage is recalculated correctly based on mode periods and pauses
            if ($activity->ended_at) {
                $sessionActivityService = app(\App\Services\SessionActivityService::class);
                $sessionActivityService->calculateDuration($activity->id);
            } else {
                // For active/paused activities, update: new total = device usage + new products total
                $newActivityTotal = max(0, $deviceUsagePrice + $newProductsTotal);
                $activity->update(['total_price' => round($newActivityTotal, 2)]);
            }

            // Recalculate session total
            if ($activity->session_id) {
                $session = Session::find($activity->session_id);
                if ($session) {
                    $session->calculateTotalPrice();
                }
            }
        };

        // When a product is created, recalculate activity and session totals
        static::created(function ($activityProduct) use ($recalculateTotals) {
            $recalculateTotals($activityProduct, 'created');
        });

        // When a product is updated (especially total_price or quantity), recalculate totals
        static::updated(function (ActivityProduct $activityProduct) use ($recalculateTotals) {
            if ($activityProduct->isDirty(['total_price', 'quantity', 'product_id'])) {
                $recalculateTotals($activityProduct, 'updated');
            }
        });

        // When a product is deleted, recalculate activity and session totals
        static::deleted(function ($activityProduct) use ($recalculateTotals) {
            $recalculateTotals($activityProduct, 'deleted');
        });
    }
}
