<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'role_id',
        'status',
        'last_login_at',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get the role that owns the user
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the user who created this user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this user
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the users created by this user
     */
    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by');
    }

    /**
     * Get the users updated by this user
     */
    public function updatedUsers()
    {
        return $this->hasMany(User::class, 'updated_by');
    }

    /**
     * Check if user has given role
     */
    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->role && in_array($this->role->name, $roleNames);
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission(string $resource, string $action): bool
    {
        if (!$this->role || !is_array($this->role->permissions)) {
            return false;
        }

        $permissions = $this->role->permissions;
        return isset($permissions[$resource]) && in_array($action, $permissions[$resource]);
    }

    /**
     * Check if user has any of the specified permissions for a resource
     */
    public function hasAnyPermission(string $resource, array $actions): bool
    {
        if (!$this->role || !is_array($this->role->permissions)) {
            return false;
        }

        $permissions = $this->role->permissions;
        if (!isset($permissions[$resource])) {
            return false;
        }

        return !empty(array_intersect($actions, $permissions[$resource]));
    }

    /**
     * Get all permissions for a specific resource
     */
    public function getPermissionsFor(string $resource): array
    {
        if (!$this->role || !is_array($this->role->permissions)) {
            return [];
        }

        return $this->role->permissions[$resource] ?? [];
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if user is staff
     */
    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    /**
     * Check if user is admin or staff
     */
    public function isAdminOrStaff(): bool
    {
        return $this->hasAnyRole(['admin', 'staff']);
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === UserStatus::ACTIVE;
    }

    /**
     * Check if user is banned
     */
    public function isBanned(): bool
    {
        return $this->status === UserStatus::BANNED;
    }
}
