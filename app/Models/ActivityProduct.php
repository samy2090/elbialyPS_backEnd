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
        // When a product is created, recalculate session total
        static::created(function (ActivityProduct $activityProduct) {
            $activity = SessionActivity::find($activityProduct->session_activity_id);
            if ($activity && $activity->session_id) {
                $session = Session::find($activity->session_id);
                if ($session) {
                    $session->calculateTotalPrice();
                }
            }
        });

        // When a product is updated (especially total_price), recalculate session total
        static::updated(function (ActivityProduct $activityProduct) {
            if ($activityProduct->isDirty('total_price')) {
                $activity = SessionActivity::find($activityProduct->session_activity_id);
                if ($activity && $activity->session_id) {
                    $session = Session::find($activity->session_id);
                    if ($session) {
                        $session->calculateTotalPrice();
                    }
                }
            }
        });

        // When a product is deleted, recalculate session total
        static::deleted(function (ActivityProduct $activityProduct) {
            $activity = SessionActivity::find($activityProduct->session_activity_id);
            if ($activity && $activity->session_id) {
                $session = Session::find($activity->session_id);
                if ($session) {
                    $session->calculateTotalPrice();
                }
            }
        });
    }
}
