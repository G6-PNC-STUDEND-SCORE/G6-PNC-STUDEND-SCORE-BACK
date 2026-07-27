<?php

namespace Database\Seeders;

use App\Models\EmailDomainRule;
use App\Models\RBAC\Role;
use Illuminate\Database\Seeder;

class EmailDomainRuleSeeder extends Seeder
{
    public function run(): void
    {
        $teacherRole = Role::where('slug', 'teacher')->first();
        $studentRole = Role::where('slug', 'student')->first();

        $rules = [
            'passerellesnumeriques.org' => $teacherRole,
            'student.passerellesnumeriques.org' => $studentRole,
            'fellow.passerellesnumeriques.org' => $studentRole,
        ];

        foreach ($rules as $domain => $role) {
            if (!$role) {
                continue;
            }

            EmailDomainRule::firstOrCreate(
                ['domain' => $domain],
                ['role_id' => $role->id, 'is_active' => true]
            );
        }
    }
}
