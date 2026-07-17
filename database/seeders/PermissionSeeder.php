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
        ];

        // Default permissions for student role
        $studentPermissions = [
            'view-scores',
            'view-subjects',
            'view-report-cards',
            'view-transcripts',
            'view-reports',
        ];

        $teacherRole = Role::where('slug', 'teacher')->first();
        $studentRole = Role::where('slug', 'student')->first();

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
