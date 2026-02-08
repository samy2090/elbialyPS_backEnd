<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesUserAvatar;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    use HandlesUserAvatar;
    public function __construct(protected AuthServiceInterface $auth)
    {
    }

    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $result = $this->auth->register($data);

        return response()->json($result, 201);
    }

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login($request->validated());

        return response()->json($result);
    }

    public function logout(Request $request)
    {
        $this->auth->logout($request);

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $user->load('role');
        $avatarOptions = array_map(fn (string $path) => [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], User::PRESET_AVATARS);

        $data = array_merge($user->toArray(), [
            'avatar_options' => array_values($avatarOptions),
            'total_points' => $user->total_points,
        ]);

        return response()->json($data);
    }

    /**
     * Get the list of preset avatars for profile selection.
     * Accessible to any authenticated user.
     */
    public function avatarOptions(): JsonResponse
    {
        $avatars = array_map(fn (string $path) => [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ], User::PRESET_AVATARS);

        return response()->json([
            'status' => 'success',
            'data' => array_values($avatars),
        ]);
    }

    /**
     * Update the authenticated user's own profile (name, username, email, password, phone, avatar).
     * Any authenticated user can update their own profile; role and status remain admin-only.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $this->applyAvatarFromRequest($request, $user, $validated);
        $user->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'data' => $user->fresh()->load('role'),
        ]);
    }
}