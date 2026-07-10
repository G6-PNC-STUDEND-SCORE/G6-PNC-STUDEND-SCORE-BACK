<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\RBAC\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'gender',
        'date_of_birth',
        'avatar',
        'google_id',
        'status',
        'last_login_at',
        'email_verified_at',
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
            'date_of_birth' => 'date',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The activity logs for this user.
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Get the teacher record associated with the user.
     */
    public function teacher(): HasOne
    {
        return $this->hasOne(Teacher::class);
    }

    /**
     * Get the student record associated with the user.
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * The roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withTimestamps();
    }

    /**
     * Check if the user has a specific role.
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : func_get_args();
        return $this->roles()->whereIn('slug', $roles)->exists();
    }

    /**
     * Check if the user is an Administrator.
     * Administrators bypass all permission checks and have unrestricted access.
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Check if the user is a Teacher.
     */
    public function isTeacher(): bool
    {
        return $this->hasRole('teacher');
    }

    /**
     * Check if the user is a Student.
     */
    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    /**
     * Check if the user has a specific permission through their assigned roles.
     *
     * Permission flow:
     *   User → Assigned Role → Role has Permissions → User inherits permissions
     *
     * Administrators automatically bypass all permission checks.
     *
     * @param string $permissionSlug The permission slug (e.g., "view-students", "manage-scores")
     * @return bool
     */
    public function hasPermission(string $permissionSlug): bool
    {
        // Admin bypass — always has full access
        if ($this->isAdmin()) {
            return true;
        }

        // Check if any of the user's roles have this permission
        return $this->roles()
            ->whereHas('permissions', function ($query) use ($permissionSlug) {
                $query->where('slug', $permissionSlug);
            })
            ->exists();
    }

    /**
     * Get all permissions that the user has through their roles.
     * Useful for frontend to load available permissions.
     *
     * @return \Illuminate\Support\Collection List of permission slugs
     */
    public function getAllPermissions(): \Illuminate\Support\Collection
    {
        if ($this->isAdmin()) {
            // Admin has all permissions — return all from DB
            return \App\Models\RBAC\Permission::pluck('slug');
        }

        return $this->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('slug')
            ->unique()
            ->values();
    }

    /**
     * Check if the user is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope a query to only include active users.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}