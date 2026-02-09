<?php

namespace App\Models;

use App\Enums\SpinWheelRewardType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SpinWheelOption extends Model
{
    protected $fillable = [
        'label',
        'reward_type',
        'value',
        'product_id',
        'weight',
        'max_claims_per_period',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'weight' => 'integer',
        'max_claims_per_period' => 'integer',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function rewardTypeEnum(): ?SpinWheelRewardType
    {
        return SpinWheelRewardType::tryFrom($this->reward_type);
    }

    public function getRewardValueForDisplay(): array
    {
        $type = $this->rewardTypeEnum();
        return match ($type) {
            SpinWheelRewardType::POINTS => [
                'type' => 'points',
                'value' => (float) $this->value,
                'label' => (int) $this->value . ' Points',
            ],
            SpinWheelRewardType::PERCENT_DISCOUNT => [
                'type' => 'percent_discount',
                'value' => (float) $this->value,
                'label' => (int) $this->value . '% Off Next Session',
            ],
            SpinWheelRewardType::FREE_MINUTES => [
                'type' => 'free_minutes',
                'value' => (int) $this->value,
                'label' => (int) $this->value . ' Free Minutes',
            ],
            SpinWheelRewardType::FREE_PRODUCT => [
                'type' => 'free_product',
                'value' => $this->product_id,
                'label' => $this->product?->name ?? 'Free Product',
                'product' => $this->product ? [
                    'id' => $this->product->id,
                    'name' => $this->product->name,
                ] : null,
            ],
            default => ['type' => 'unknown', 'value' => null, 'label' => $this->label],
        };
    }
}
