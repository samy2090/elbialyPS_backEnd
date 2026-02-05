<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HandlesUserAvatar
{
    /**
     * Delete the user's current avatar file only if it was uploaded (not a preset).
     */
    protected function deleteUserUploadedAvatar(User $user): void
    {
        if (!$user->avatar) {
            return;
        }
        if (in_array($user->avatar, User::PRESET_AVATARS, true)) {
            return;
        }
        Storage::disk('public')->delete($user->avatar);
    }

    /**
     * Extract raw base64 string from avatar payload (array from JSON).
     */
    protected function extractBase64FromAvatarPayload(array $payload): ?string
    {
        $b64 = $payload['data'] ?? $payload['content'] ?? $payload['base64'] ?? $payload['file'] ?? $payload['image'] ?? null;
        if (is_string($b64)) {
            return preg_match('/^data:image\/\w+;base64,(.+)$/s', $b64, $m) ? $m[1] : $b64;
        }
        $first = reset($payload);
        if (is_array($first)) {
            $b64 = $first['data'] ?? $first['content'] ?? $first['base64'] ?? $first['file'] ?? $first['image'] ?? null;
        } elseif (is_string($first)) {
            $b64 = preg_match('/^data:image\/\w+;base64,(.+)$/s', $first, $m) ? $m[1] : $first;
        }
        if (is_string($b64)) {
            return preg_match('/^data:image\/\w+;base64,(.+)$/s', $b64, $m) ? $m[1] : $b64;
        }
        foreach ($payload as $v) {
            if (is_string($v) && strlen($v) > 100) {
                return preg_match('/^data:image\/\w+;base64,(.+)$/s', $v, $m) ? $m[1] : $v;
            }
        }
        return null;
    }

    /**
     * Decode base64 image and store under avatars; return path (e.g. avatars/xxx.png).
     */
    protected function storeBase64Avatar(string $base64): string
    {
        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 avatar data.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($decoded);
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'png',
        };
        $filename = Str::random(40) . '.' . $ext;
        Storage::disk('public')->put('avatars/' . $filename, $decoded);
        return 'avatars/' . $filename;
    }

    /**
     * Apply avatar from request to validated array and user.
     * Modifies $validated in place and optionally updates user.
     */
    protected function applyAvatarFromRequest(Request $request, User $user, array &$validated): void
    {
        $avatarFile = $request->file('avatar') ?? $request->file('image') ?? $request->file('file');
        if (!$avatarFile && isset($validated['avatar']) && is_object($validated['avatar']) && method_exists($validated['avatar'], 'getClientOriginalName')) {
            $avatarFile = $validated['avatar'];
        }
        if (!$avatarFile && $request->allFiles()) {
            $avatarFile = reset($request->allFiles());
            if (is_array($avatarFile)) {
                $avatarFile = reset($avatarFile);
            }
        }
        if ($avatarFile) {
            $this->deleteUserUploadedAvatar($user);
            $avatarPath = $avatarFile->store('avatars', 'public');
            $validated['avatar'] = $avatarPath;
        } elseif (isset($validated['avatar']) && is_array($validated['avatar'])) {
            $b64 = $this->extractBase64FromAvatarPayload($validated['avatar']);
            if (is_string($b64)) {
                $this->deleteUserUploadedAvatar($user);
                $validated['avatar'] = $this->storeBase64Avatar($b64);
            } else {
                unset($validated['avatar']);
            }
        } elseif (isset($validated['avatar']) && is_string($validated['avatar'])) {
            if (str_starts_with($validated['avatar'], 'data:image') && preg_match('/^data:image\/\w+;base64,(.+)$/s', $validated['avatar'], $m)) {
                $this->deleteUserUploadedAvatar($user);
                $validated['avatar'] = $this->storeBase64Avatar($m[1]);
            } elseif (in_array($validated['avatar'], User::PRESET_AVATARS, true)) {
                $this->deleteUserUploadedAvatar($user);
                // keep validated['avatar'] as preset path
            } else {
                unset($validated['avatar']);
            }
        } else {
            unset($validated['avatar']);
        }
        unset($validated['image'], $validated['file']);
    }
}
