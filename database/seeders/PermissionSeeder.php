<?php

namespace Database\Seeders;

use App\Models\RBAC\Permission;
use App\Models\RBAC\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // All permissions grouped by feature/page
        $permissions = [
            // Students
            ['group' => 'students', 'slug' => 'view-students',   'name' => 'View Students'],
            ['group' => 'students', 'slug' => 'create-students', 'name' => 'Create Students'],
            ['group' => 'students', 'slug' => 'update-students', 'name' => 'Update Students'],
            ['group' => 'students', 'slug' => 'delete-students', 'name' => 'Delete Students'],

            // Teachers
            ['group' => 'teachers', 'slug' => 'view-teachers',   'name' => 'View Teachers'],
            ['group' => 'teachers', 'slug' => 'create-teachers', 'name' => 'Create Teachers'],
            ['group' => 'teachers', 'slug' => 'update-teachers', 'name' => 'Update Teachers'],
            ['group' => 'teachers', 'slug' => 'delete-teachers', 'name' => 'Delete Teachers'],

            // Classes
            ['group' => 'classes', 'slug' => 'view-classes',   'name' => 'View Classes'],
            ['group' => 'classes', 'slug' => 'create-classes', 'name' => 'Create Classes'],
            ['group' => 'classes', 'slug' => 'update-classes', 'name' => 'Update Classes'],
            ['group' => 'classes', 'slug' => 'delete-classes', 'name' => 'Delete Classes'],

            // Subjects
            ['group' => 'subjects', 'slug' => 'view-subjects',   'name' => 'View Subjects'],
            ['group' => 'subjects', 'slug' => 'create-subjects', 'name' => 'Create Subjects'],
            ['group' => 'subjects', 'slug' => 'update-subjects', 'name' => 'Update Subjects'],
            ['group' => 'subjects', 'slug' => 'delete-subjects', 'name' => 'Delete Subjects'],

            // Scores
            ['group' => 'scores', 'slug' => 'view-scores',   'name' => 'View Scores'],
            ['group' => 'scores', 'slug' => 'create-scores', 'name' => 'Create Scores'],
            ['group' => 'scores', 'slug' => 'update-scores', 'name' => 'Update Scores'],
            ['group' => 'scores', 'slug' => 'delete-scores', 'name' => 'Delete Scores'],

            // Departments
            ['group' => 'departments', 'slug' => 'view-departments',   'name' => 'View Departments'],
            ['group' => 'departments', 'slug' => 'create-departments', 'name' => 'Create Departments'],
            ['group' => 'departments', 'slug' => 'update-departments', 'name' => 'Update Departments'],
            ['group' => 'departments', 'slug' => 'delete-departments', 'name' => 'Delete Departments'],

            // Generations
            ['group' => 'generations', 'slug' => 'view-generations',   'name' => 'View Generations'],
            ['group' => 'generations', 'slug' => 'create-generations', 'name' => 'Create Generations'],
            ['group' => 'generations', 'slug' => 'update-generations', 'name' => 'Update Generations'],
            ['group' => 'generations', 'slug' => 'delete-generations', 'name' => 'Delete Generations'],

            // Report Cards
            ['group' => 'report-cards', 'slug' => 'view-report-cards',     'name' => 'View Report Cards'],
            ['group' => 'report-cards', 'slug' => 'generate-report-cards', 'name' => 'Generate Report Cards'],

            // Transcripts
            ['group' => 'transcripts', 'slug' => 'view-transcripts',     'name' => 'View Transcripts'],
            ['group' => 'transcripts', 'slug' => 'generate-transcripts', 'name' => 'Generate Transcripts'],

            // Reports
            ['group' => 'reports', 'slug' => 'view-reports',   'name' => 'View Reports'],
            ['group' => 'reports', 'slug' => 'export-reports', 'name' => 'Export Reports'],

            // Activity Logs
            ['group' => 'activity-logs', 'slug' => 'view-activity-logs', 'name' => 'View Activity Logs'],

            // Users
            ['group' => 'users', 'slug' => 'view-users',   'name' => 'View Users'],
            ['group' => 'users', 'slug' => 'create-users', 'name' => 'Create Users'],
            ['group' => 'users', 'slug' => 'update-users', 'name' => 'Update Users'],
            ['group' => 'users', 'slug' => 'delete-users', 'name' => 'Delete Users'],

            // Sign-in Domains (email domain -> role rules used by Google login)
            ['group' => 'email-domain-rules', 'slug' => 'view-email-domain-rules',   'name' => 'View Sign-in Domains'],
            ['group' => 'email-domain-rules', 'slug' => 'create-email-domain-rules', 'name' => 'Create Sign-in Domains'],
            ['group' => 'email-domain-rules', 'slug' => 'update-email-domain-rules', 'name' => 'Update Sign-in Domains'],
            ['group' => 'email-domain-rules', 'slug' => 'delete-email-domain-rules', 'name' => 'Delete Sign-in Domains'],

            // Grade Boundaries (A/B/C/... cutoffs) — no create/delete, boundaries are a fixed set
            ['group' => 'grade-boundaries', 'slug' => 'view-grade-boundaries',   'name' => 'View Grade Boundaries'],
            ['group' => 'grade-boundaries', 'slug' => 'update-grade-boundaries', 'name' => 'Update Grade Boundaries'],

            // Assessment Types (quiz/assignment/midterm/final weighting)
            ['group' => 'assessment-types', 'slug' => 'view-assessment-types',   'name' => 'View Assessment Types'],
            ['group' => 'assessment-types', 'slug' => 'create-assessment-types', 'name' => 'Create Assessment Types'],
            ['group' => 'assessment-types', 'slug' => 'update-assessment-types', 'name' => 'Update Assessment Types'],
            ['group' => 'assessment-types', 'slug' => 'delete-assessment-types', 'name' => 'Delete Assessment Types'],

            // Terms (academic term structure)
            ['group' => 'terms', 'slug' => 'view-terms',   'name' => 'View Terms'],
            ['group' => 'terms', 'slug' => 'create-terms', 'name' => 'Create Terms'],
            ['group' => 'terms', 'slug' => 'update-terms', 'name' => 'Update Terms'],
            ['group' => 'terms', 'slug' => 'delete-terms', 'name' => 'Delete Terms'],

            // System
            ['group' => 'system', 'slug' => 'manage-roles-permissions', 'name' => 'Manage Roles & Permissions'],
            ['group' => 'system', 'slug' => 'view-own-student-info', 'name' => 'View Own Student Info'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['slug' => $perm['slug']],
                ['name' => $perm['name'], 'group' => $perm['group']]
            );
        }

        // Default permissions for teacher role
        $teacherPermissions = [
            'view-students',
            'view-classes',
            'view-subjects',
            'view-scores', 'create-scores', 'update-scores',
            'view-report-cards', 'generate-report-cards',
            'view-transcripts', 'generate-transcripts',
            'view-reports', 'export-reports',
            'view-activity-logs',
            // These four were previously open to any authenticated user (no permission
            // existed to gate them) — granted here so tightening that gate doesn't regress
            // a teacher's existing ability to read them.
            'view-generations', 'view-assessment-types', 'view-terms', 'view-grade-boundaries',
        ];

        // Default permissions for student role
        $studentPermissions = [
            'view-scores',
            'view-subjects',
            'view-report-cards',
            'view-transcripts',
            'view-reports',
            // See the comment on $teacherPermissions above — same reasoning.
            'view-generations', 'view-assessment-types', 'view-terms', 'view-grade-boundaries',
        ];

        $adminRole = Role::where('slug', 'admin')->first();
        $teacherRole = Role::where('slug', 'teacher')->first();
        $studentRole = Role::where('slug', 'student')->first();

        // Admin starts with every permission granted. Unlike other roles, admin's actual
        // access is never gated by this (User::hasPermission() always allows admin through) —
        // this only controls what shows up in their own nav, which they can trim from the
        // Roles & Permissions page like any other role without any risk of locking themselves
        // out. Users/Roles & Permissions/Sign-in Domains are permission-gated like everything
        // else now (view-users, manage-roles-permissions, view-email-domain-rules, etc.) —
        // admin keeps access purely because admin bypasses every permission check, not because
        // these routes are hardcoded to role:admin anymore.
        if ($adminRole) {
            $adminRole->permissions()->sync(Permission::pluck('id'));
        }

        if ($teacherRole) {
            $ids = Permission::whereIn('slug', $teacherPermissions)->pluck('id');
            $teacherRole->permissions()->sync($ids);
        }

        if ($studentRole) {
            $ids = Permission::whereIn('slug', $studentPermissions)->pluck('id');
            $studentRole->permissions()->sync($ids);
        }
    }
}
