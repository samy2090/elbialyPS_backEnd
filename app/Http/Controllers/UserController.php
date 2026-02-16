<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesUserAvatar;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\CreateGuestUserRequest;
use App\Models\User;
use App\Models\Role;
use App\Enums\UserStatus;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use HandlesUserAvatar;

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
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
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
        $user->load('role');

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

        $this->applyAvatarFromRequest($request, $user, $validated);

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
    public function restore(int $id): JsonResponse
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
    public function forceDelete(int $id): JsonResponse
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
                'roles' => Role::pluck('name', 'id')->toArray(),
                'statuses' => UserStatus::options(),
                'avatar_options' => $avatarOptions,
            ]
        ]);
    }

}