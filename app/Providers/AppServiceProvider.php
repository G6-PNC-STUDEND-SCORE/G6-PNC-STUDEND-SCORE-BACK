<?php

namespace App\Providers;

use App\Models\RBAC\Role;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Observers\RoleObserver;
use App\Observers\SchoolClassObserver;
use App\Observers\ScoreObserver;
use App\Observers\StudentObserver;
use App\Observers\SubjectObserver;
use App\Observers\TeacherObserver;
use App\Observers\UserObserver;
use App\Services\ActivityLogService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind ActivityLogService as a singleton so the same instance
        // is reused across observers, middleware, and controllers
        $this->app->singleton(ActivityLogService::class, function () {
            return new ActivityLogService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers for automatic activity logging
        // These observers will only create logs when the user is Admin or Teacher
        Student::observe(StudentObserver::class);
        Teacher::observe(TeacherObserver::class);
        SchoolClass::observe(SchoolClassObserver::class);
        Subject::observe(SubjectObserver::class);
        Score::observe(ScoreObserver::class);
        User::observe(UserObserver::class);
        Role::observe(RoleObserver::class);
    }
}