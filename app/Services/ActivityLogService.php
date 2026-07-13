<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

/**
 * Centralized service for recording activity logs.
 *
 * Only logs activities for Admin and Teacher roles.
 * Student activities are never logged.
 *
 * This service should be the single point of entry for all activity logging,
 * keeping controllers clean and logic maintainable.
 */
class ActivityLogService
{
    /**
     * The set of roles that are eligible for activity logging.
     */
    private const LOGGABLE_ROLES = ['admin', 'teacher'];

    /**
     * Log an action performed by a user.
     *
     * @param User|null   $user       The user performing the action. If null, uses auth()->user().
     * @param string      $action     The action name (Create, Update, Delete, Login, etc.)
     * @param string      $module     The module name (Students, Teachers, Classes, etc.)
     * @param string      $description Human-readable description of what happened
     * @param Model|null  $model      The Eloquent model instance that was affected (optional)
     * @param array|null  $oldValues  The values before the change (optional)
     * @param array|null  $newValues  The values after the change (optional)
     *
     * @return ActivityLog|null Returns the created log, or null if the user is not loggable.
     */
    public function log(
        ?User $user,
        string $action,
        string $module,
        string $description,
        ?Model $model = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ?ActivityLog {
        // Determine the user
        $user = $user ?? request()->user();

        // Only log for Admin and Teacher roles
        if (!$this->isLoggable($user)) {
            return null;
        }

        // Build the log data
        $data = [
            'user_id' => $user->id,
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'model_type' => $model ? get_class($model) : null,
            'model_id' => $model ? $model->getKey() : null,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ];

        try {
            return ActivityLog::create($data);
        } catch (\Throwable $e) {
            // Log the error but don't break the application
            Log::warning('Failed to record activity log: ' . $e->getMessage(), [
                'user_id' => $user->id,
                'action' => $action,
                'module' => $module,
            ]);
            return null;
        }
    }

    /**
     * Easy-access method for logging a create action.
     */
    public function logCreate(?User $user, string $module, string $description, Model $model, array $newValues = []): ?ActivityLog
    {
        return $this->log($user, 'Create', $module, $description, $model, null, $newValues);
    }

    /**
     * Easy-access method for logging an update action with old/new value comparison.
     */
    public function logUpdate(?User $user, string $module, string $description, Model $model, array $oldValues = [], array $newValues = []): ?ActivityLog
    {
        return $this->log($user, 'Update', $module, $description, $model, $oldValues, $newValues);
    }

    /**
     * Easy-access method for logging a delete action.
     */
    public function logDelete(?User $user, string $module, string $description, Model $model, array $oldValues = []): ?ActivityLog
    {
        return $this->log($user, 'Delete', $module, $description, $model, $oldValues, null);
    }

    /**
     * Easy-access method for logging a login action via request context.
     */
    public function logLogin(User $user): ?ActivityLog
    {
        return $this->log($user, 'Login', 'Auth', "User {$user->name} logged in.");
    }

    /**
     * Easy-access method for logging a logout action.
     */
    public function logLogout(User $user): ?ActivityLog
    {
        return $this->log($user, 'Logout', 'Auth', "User {$user->name} logged out.");
    }

    /**
     * Log an export action.
     */
    public function logExport(?User $user, string $module, string $description): ?ActivityLog
    {
        return $this->log($user, 'Export', $module, $description);
    }

    /**
     * Log an import action.
     */
    public function logImport(?User $user, string $module, string $description): ?ActivityLog
    {
        return $this->log($user, 'Import', $module, $description);
    }

    /**
     * Check if the given user is eligible for activity logging.
     * Only Admins and Teachers are logged.
     */
    private function isLoggable(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole(self::LOGGABLE_ROLES);
    }

    /**
     * Get recently changed values from a model for logging.
     * Returns [old_values, new_values] arrays.
     *
     * @param Model $model The model with changes (must have getChanges() etc.)
     * @return array{old: array|null, new: array|null}
     */
    public static function getModelChanges(Model $model): array
    {
        if (!$model->exists) {
            return ['old' => null, 'new' => $model->toArray()];
        }

        $original = $model->getOriginal();
        $changes = $model->getChanges();

        if (empty($changes)) {
            return ['old' => null, 'new' => null];
        }

        $oldValues = [];
        $newValues = [];

        foreach ($changes as $key => $newValue) {
            // Skip timestamps to reduce noise
            if (in_array($key, ['updated_at', 'created_at'])) {
                continue;
            }
            $oldValues[$key] = $original[$key] ?? null;
            $newValues[$key] = $newValue;
        }

        return [
            'old' => !empty($oldValues) ? $oldValues : null,
            'new' => !empty($newValues) ? $newValues : null,
        ];
    }
}