<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\CreateGuestUserRequest;
use App\Models\User;
use App\Models\Role;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(protected AuthService $authService)
    {
    }

    /**
     * Display a listing of the users.
     * 
     * Supports optional pagination via paginate=true or paginate=1 query parameter.
     * When pagination is enabled, accepts per_page (default: 10) and page query parameters.
     * When pagination is disabled, returns all users limited to a safe maximum (500).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->with('role');
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('role')) {
            $role = $request->role;
            if (is_numeric($role)) {
                $query->where('role_id', $role);
            } else {
                $query->whereHas('role', fn ($q) => $q->where('name', $role));
            }
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }
        
        // Apply sorting
        $query->latest();
        
        // Check if pagination is requested
        if ($request->boolean('paginate')) {
            // Paginated response
            $perPage = min((int) $request->get('per_page', 10), 100); // Max 100 per page
            $perPage = max($perPage, 1); // Minimum 1 per page
            
            $users = $query->paginate($perPage);
            
            // Laravel's paginator automatically includes pagination metadata when serialized
            return response()->json([
                'status' => 'success',
                'data' => $users
            ]);
        } else {
            // Non-paginated response with safe maximum limit
            $maxLimit = 500; // Safe maximum to prevent memory issues
            $users = $query->limit($maxLimit)->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $users,
                'count' => $users->count(),
                'note' => $users->count() >= $maxLimit 
                    ? "Results limited to {$maxLimit} records. Use paginate=true for full results." 
                    : null
            ]);
        }
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);

        // Same as registration: username from email (part before @) or random, then unique
        $validated['username'] = $this->authService->generateUniqueUsername($validated['email'] ?? null);
        $validated['email'] = $validated['email'] ?? null;

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        } else {
            $validated['avatar'] = User::DEFAULT_AVATAR;
        }

        if (auth()->check()) {
            $validated['created_by'] = auth()->id();
        }

        $user = User::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $user
        ]);
    }

    /**
     * Update the specified user in storage.
     * Only submitted fields are updated; others (including password) stay unchanged.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        // Only update password when a new one is provided; otherwise leave it unchanged
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // File upload: accept "avatar", "image", "file", or validated avatar (file object), or first file in request
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
            $user->avatar = $avatarPath;
            $user->save();
        } elseif (isset($validated['avatar']) && is_array($validated['avatar'])) {
            $b64 = $this->extractBase64FromAvatarPayload($validated['avatar']);
            if (is_string($b64)) {
                $this->deleteUserUploadedAvatar($user);
                $validated['avatar'] = $this->storeBase64Avatar($b64);
            } else {
                unset($validated['avatar']);
            }
        } elseif (isset($validated['avatar']) && is_string($validated['avatar'])) {
            if (str_starts_with($validated['avatar'], 'data:image')) {
                if (preg_match('/^data:image\/\w+;base64,(.+)$/s', $validated['avatar'], $m)) {
                    $this->deleteUserUploadedAvatar($user);
                    $validated['avatar'] = $this->storeBase64Avatar($m[1]);
                } else {
                    unset($validated['avatar']);
                }
            } elseif (in_array($validated['avatar'], User::PRESET_AVATARS, true)) {
                $this->deleteUserUploadedAvatar($user);
                // keep validated['avatar'] as preset path
            } else {
                unset($validated['avatar']);
            }
        } else {
            unset($validated['avatar']);
        }

        // Don't pass file keys to the model
        unset($validated['image'], $validated['file']);

        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $user->fresh()
        ]);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user): JsonResponse
    {
        // Prevent deleting own account
        if (auth()->id() === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot delete your own account'
            ], 403);
        }
        
        $user->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Restore a soft deleted user.
     */
    public function restore($id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        
        return response()->json([
            'status' => 'success',
            'message' => 'User restored successfully',
            'data' => $user
        ]);
    }

    /**
     * Force delete a user permanently.
     */
    public function forceDelete($id): JsonResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        
        // Prevent force deleting own account
        if (auth()->id() === $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot permanently delete your own account'
            ], 403);
        }
        
        $user->forceDelete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'User permanently deleted'
        ]);
    }

    /**
     * Create a guest user with minimal data (name and role only).
     * This endpoint is used when creating a session and no existing customer matches.
     */
    public function createGuest(CreateGuestUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Get the guest role from the roles table
        $guestRole = Role::where('name', 'guest')->first();
        
        if (!$guestRole) {
            return response()->json([
                'status' => 'error',
                'message' => 'Guest role not found in the system'
            ], 500);
        }
        
        // Generate a unique username from the name
        $baseUsername = Str::slug($validated['name'], '');
        $username = $baseUsername;
        $counter = 1;
        
        // Ensure username is unique
        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        // Prepare user data with defaults
        $userData = [
            'name' => $validated['name'],
            'username' => $username,
            'email' => null, // Email is null for guest users
            'password' => null, // Password is null for guest users
            'phone' => null, // Phone is null for guest users
            'role_id' => $guestRole->id,
            'status' => UserStatus::ACTIVE->value,
            'avatar' => User::DEFAULT_AVATAR,
        ];
        
        // Set created_by if user is authenticated
        if (auth()->check()) {
            $userData['created_by'] = auth()->id();
        }
        
        $user = User::create($userData);
        
        // Load the role relationship
        $user->load('role');
        
        return response()->json([
            'status' => 'success',
            'message' => 'Guest user created successfully',
            'data' => $user
        ], 201);
    }

    /**
     * Get all available roles, statuses, and avatar presets for dropdowns/choices.
     */
    public function options(): JsonResponse
    {
        $avatarOptions = array_map(function (string $path) {
            return [
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ];
        }, User::PRESET_AVATARS);

        return response()->json([
            'status' => 'success',
            'data' => [
                'roles' => \App\Models\Role::pluck('name', 'id')->toArray(),
                'statuses' => UserStatus::options(),
                'avatar_options' => $avatarOptions,
            ]
        ]);
    }

    /**
     * Delete the user's current avatar file only if it was uploaded (not a preset).
     */
    private function deleteUserUploadedAvatar(User $user): void
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
    private function extractBase64FromAvatarPayload(array $payload): ?string
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
    private function storeBase64Avatar(string $base64): string
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
}