<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\CreateGuestUserRequest;
use App\Models\User;
use App\Models\Role;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }
        
        // Pagination
        $perPage = $request->get('per_page', 15);
        $users = $query->latest()->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);
        
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
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();
        
        // Hash password if provided
        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }
        
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
     * Get all available roles and statuses for dropdowns.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'roles' => \App\Models\Role::pluck('name', 'id')->toArray(),
                'statuses' => UserStatus::options()
            ]
        ]);
    }
}