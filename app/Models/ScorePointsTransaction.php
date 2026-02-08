<?php

namespace App\Models;

use App\Enums\PointTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ScorePointsTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'points',
        'type',
        'source_type',
        'source_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'points' => 'decimal:2',
        'type' => PointTransactionType::class,
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }
}
