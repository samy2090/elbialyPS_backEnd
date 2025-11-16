<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Repositories\UserRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService implements AuthServiceInterface
{
    public function __construct(protected UserRepositoryInterface $users)
    {
    }

    public function register(array $data): array
    {
        // Check if phone is already in use
        if ($this->users->findByPhone($data['phone'])) {
            throw ValidationException::withMessages(['phone' => ['Phone number already in use']]);
        }

        // Check if email is provided and already in use
        if (!empty($data['email']) && $this->users->findByEmail($data['email'])) {
            throw ValidationException::withMessages(['email' => ['Email already in use']]);
        }

        // Generate username based on available information
        if (!empty($data['email'])) {
            // Generate username from email (part before @)
            $username = $this->generateUsernameFromEmail($data['email']);
        } else {
            // Generate random 8-character username if only phone is provided
            $username = $this->generateRandomUsername();
        }

        // Ensure username is unique
        $username = $this->ensureUniqueUsername($username);

        // Get the customer role
        $customerRole = \App\Models\Role::where('name', 'customer')->first();
        
        $userData = [
            'name' => $data['name'],
            'username' => $username,
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'],
            'role_id' => $customerRole?->id, // Default role is customer
            'status' => UserStatus::ACTIVE->value, // Default status
        ];

        $user = $this->users->create($userData);

        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function login(array $credentials): array
    {
        $login = $credentials['login'];
        
        // Determine if login is email, phone, or username
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            // It's an email
            $user = $this->users->findByEmail($login);
        } elseif (preg_match('/^01[0-9]{9}$/', $login)) {
            // It's a phone number (11 digits starting with 01)
            $user = $this->users->findByPhone($login);
        } else {
            // It's a username
            $user = $this->users->findByUsername($login);
        }

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages(['login' => ['The provided credentials are incorrect.']]);
        }

        // Update last login timestamp
        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('api-token')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    public function logout(Request $request): void
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }
    }

    /**
     * Generate username from email (part before @)
     */
    private function generateUsernameFromEmail(string $email): string
    {
        return explode('@', $email)[0];
    }

    /**
     * Generate random 8-character username
     */
    private function generateRandomUsername(): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $username = '';
        
        for ($i = 0; $i < 8; $i++) {
            $username .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $username;
    }

    /**
     * Ensure username is unique by appending numbers if needed
     */
    private function ensureUniqueUsername(string $baseUsername): string
    {
        $username = $baseUsername;
        $counter = 1;

        while ($this->users->findByUsername($username)) {
            if (strlen($baseUsername) === 8 && ctype_alnum($baseUsername)) {
                // If it's a generated random username, generate a new random one
                $username = $this->generateRandomUsername();
            } else {
                // If it's from email, append counter
                $username = $baseUsername . $counter;
                $counter++;
            }
        }

        return $username;
    }
}
