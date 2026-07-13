<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    public static function log(
        string $action,
        string $module,
        string $description,
        ?Model $model = null,
        array $oldValues = [],
        array $newValues = []
    ): void {
        ActivityLog::create([
            'user_id'     => Auth::id(),
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'model_type'  => $model ? get_class($model) : null,
            'model_id'    => $model?->getKey(),
            'old_values'  => $oldValues ?: null,
            'new_values'  => $newValues ?: null,
            'ip_address'  => Request::ip(),
            'user_agent'  => Request::userAgent(),
        ]);
    }

    public static function logCreate(string $module, Model $model, string $description = ''): void
    {
        static::log('create', $module, $description ?: "Created {$module} #{$model->getKey()}", $model, [], $model->toArray());
    }

    public static function logUpdate(string $module, Model $model, array $oldValues, string $description = ''): void
    {
        static::log('update', $module, $description ?: "Updated {$module} #{$model->getKey()}", $model, $oldValues, $model->toArray());
    }

    public static function logDelete(string $module, Model $model, string $description = ''): void
    {
        static::log('delete', $module, $description ?: "Deleted {$module} #{$model->getKey()}", $model, $model->toArray(), []);
    }

    public static function logLogin(Model $user): void
    {
        static::log('login', 'auth', "User {$user->name} logged in", $user);
    }

    public static function logLogout(Model $user): void
    {
        static::log('logout', 'auth', "User {$user->name} logged out", $user);
    }
}
