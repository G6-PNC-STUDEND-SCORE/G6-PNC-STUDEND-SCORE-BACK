<?php

namespace App\Providers;

use App\Models\RBAC\Permission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any authentication / authorization services.
     */
    protected $policies = [
        Student::class => \App\Policies\StudentPolicy::class,
        \App\Models\SchoolClass::class => \App\Policies\SchoolClassPolicy::class,
    ];

    public function boot(): void
    {
        // Dynamically register gates for every permission in the database.
        // This means adding a new permission to the DB automatically creates
        // a corresponding Gate without any code changes.
        $this->registerDynamicGates();

        // Register the model policies
        $this->registerPolicies();
    }

    /**
     * Register a Gate for each permission defined in the database.
     *
     * Each gate checks:
     * 1. If the user is an Admin → always allowed (full access).
     * 2. If the user has a role that includes this permission → allowed.
     * 3. Otherwise → denied.
     */
    private function registerDynamicGates(): void
    {
        try {
            // Cache permissions to avoid repeated DB queries
            $permissions = \Illuminate\Support\Facades\Cache::remember('rbac_permissions', 3600, function () {
                return Permission::with('roles')->get();
            });

            foreach ($permissions as $permission) {
                Gate::define($permission->slug, function (User $user) use ($permission) {
                    // Admin bypass — always has full access
                    if ($user->hasRole('admin')) {
                        return true;
                    }

                    // Check if any of the user's roles have this permission
                    return $user->roles()
                        ->whereHas('permissions', function ($query) use ($permission) {
                            $query->where('permissions.id', $permission->id);
                        })
                        ->exists();
                });
            }
        } catch (\Throwable $e) {
            // If the permissions table doesn't exist yet (e.g., during migration),
            // silently skip gate registration
            return;
        }
    }

    /**
     * Clear the cached permissions (call this after any role/permission changes).
     */
    public static function clearPermissionsCache(): void
    {
        \Illuminate\Support\Facades\Cache::forget('rbac_permissions');
    }
}