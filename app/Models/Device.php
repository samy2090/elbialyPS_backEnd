<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
        'device_type',
        'status',
        'price_per_hour',
        'multi_price',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'device_type' => DeviceType::class,
            'status' => DeviceStatus::class,
            'price_per_hour' => 'decimal:2',
            'multi_price' => 'decimal:2',
        ];
    }

    /**
     * Check if device has given type
     */
    public function hasType(string|DeviceType $type): bool
    {
        $value = $type instanceof DeviceType ? $type->value : $type;
        return $this->device_type->value === $value;
    }

    /**
     * Check if device has given status
     */
    public function hasStatus(string|DeviceStatus $status): bool
    {
        $value = $status instanceof DeviceStatus ? $status->value : $status;
        return $this->status->value === $value;
    }

    /**
     * Check if device is available
     */
    public function isAvailable(): bool
    {
        return $this->status === DeviceStatus::AVAILABLE;
    }

    /**
     * Check if device is in use
     */
    public function isInUse(): bool
    {
        return $this->status === DeviceStatus::IN_USE;
    }

    /**
     * Check if device is in maintenance
     */
    public function isInMaintenance(): bool
    {
        return $this->status === DeviceStatus::MAINTENANCE;
    }

    /**
     * Check if device can be used
     */
    public function canBeUsed(): bool
    {
        return $this->status->canBeUsed();
    }

    /**
     * Check if device is a game console
     */
    public function isGameConsole(): bool
    {
        return $this->device_type->isGameConsole();
    }

    /**
     * Check if device is a billiard
     */
    public function isBilliard(): bool
    {
        return $this->device_type->isBilliard();
    }

    /**
     * Get formatted price per hour (in Egyptian Pounds)
     */
    public function getFormattedPricePerHourAttribute(): string
    {
        return number_format((float) $this->price_per_hour, 2);
    }

    /**
     * Get formatted multi price (in Egyptian Pounds)
     */
    public function getFormattedMultiPriceAttribute(): ?string
    {
        return $this->multi_price ? number_format((float) $this->multi_price, 2) : null;
    }

    /**
     * Scope: Filter by device type
     */
    public function scopeOfType($query, DeviceType|string $type)
    {
        $value = $type instanceof DeviceType ? $type->value : $type;
        return $query->where('device_type', $value);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeWithStatus($query, DeviceStatus|string $status)
    {
        $value = $status instanceof DeviceStatus ? $status->value : $status;
        return $query->where('status', $value);
    }

    /**
     * Scope: Get available devices
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', DeviceStatus::AVAILABLE->value);
    }

    /**
     * Scope: Get game consoles
     */
    public function scopeGameConsoles($query)
    {
        return $query->whereIn('device_type', [DeviceType::PS4->value, DeviceType::PS5->value]);
    }

    /**
     * Scope: Get billiards
     */
    public function scopeBilliards($query)
    {
        return $query->where('device_type', DeviceType::BILLIARD->value);
    }
}
