<?php

namespace App\Models;

use App\Enums\PostReactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostReaction extends Model
{
    protected $fillable = ['post_id', 'user_id', 'reaction_type'];

    protected function casts(): array
    {
        return [
            'reaction_type' => PostReactionType::class,
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
