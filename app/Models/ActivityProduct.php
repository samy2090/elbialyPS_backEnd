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
        // Helper method to add product price to activity total
        $addProductPrice = function (ActivityProduct $activityProduct, $eventType = 'created') {
            $activity = SessionActivity::find($activityProduct->session_activity_id);
            if (!$activity) {
                return;
            }

            // Refresh activity to get latest data
            $activity->refresh();
            
            // Get current activity total price
            $currentTotal = (float) ($activity->total_price ?? 0);
            
            // Calculate product price change
            $productPriceChange = 0;
            if ($eventType === 'created') {
                $productPriceChange = (float) $activityProduct->total_price;
            } elseif ($eventType === 'deleted') {
                $productPriceChange = -(float) $activityProduct->total_price;
            } elseif ($eventType === 'updated') {
                $oldPrice = (float) ($activityProduct->getOriginal('total_price') ?? 0);
                $newPrice = (float) $activityProduct->total_price;
                $productPriceChange = $newPrice - $oldPrice;
            }
            
            // Add product price change to current total
            $newTotal = $currentTotal + $productPriceChange;
            
            // Update activity total price
            $activity->update(['total_price' => round($newTotal, 2)]);

            // Recalculate session total
            if ($activity->session_id) {
                $session = Session::find($activity->session_id);
                if ($session) {
                    $session->calculateTotalPrice();
                }
            }
        };

        // When a product is created, add its price to activity total
        static::created(function ($activityProduct) use ($addProductPrice) {
            $addProductPrice($activityProduct, 'created');
        });

        // When a product is updated (especially total_price or quantity), update activity total
        static::updated(function (ActivityProduct $activityProduct) use ($addProductPrice) {
            if ($activityProduct->isDirty(['total_price', 'quantity', 'product_id'])) {
                $addProductPrice($activityProduct, 'updated');
            }
        });

        // When a product is deleted, subtract its price from activity total
        static::deleted(function ($activityProduct) use ($addProductPrice) {
            $addProductPrice($activityProduct, 'deleted');
        });
    }
}
