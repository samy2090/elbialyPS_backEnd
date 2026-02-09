<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRankPublicResource extends JsonResource
{
    /**
     * Transform the resource into an array (non-privacy: id, name, username, avatar, role name, rank, points).
     * Accepts User model or array/object with 'user', 'rank', 'period_points'.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->getUser();
        $rank = $this->getRank();
        $points = $this->getPeriodPoints();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'role' => $user->relationLoaded('role') && $user->role
                ? $user->role->name
                : null,
            'rank' => $rank,
            'points' => $points,
        ];
    }

    private function getUser(): object
    {
        $r = $this->resource;
        if (is_array($r)) {
            return $r['user'] ?? (object) $r;
        }
        return $r->user ?? $r;
    }

    private function getRank(): ?int
    {
        $r = $this->resource;
        $rank = is_array($r) ? ($r['rank'] ?? null) : ($r->rank ?? null);
        return $rank !== null ? (int) $rank : null;
    }

    private function getPeriodPoints(): ?float
    {
        $r = $this->resource;
        $p = is_array($r) ? ($r['period_points'] ?? null) : ($r->period_points ?? null);
        return $p !== null ? (float) $p : null;
    }
}
