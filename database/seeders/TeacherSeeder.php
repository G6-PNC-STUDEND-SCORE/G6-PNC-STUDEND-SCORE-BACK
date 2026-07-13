<?php

namespace Database\Seeders;

use App\Models\RBAC\Role;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $teacherUsers = User::whereHas('role', function ($query) {
            $query->where('slug', 'teacher');
        })->get();

        // Seed departments
        $departments = [
            ['name' => 'Technical', 'description' => 'Technical department'],
            ['name' => 'English',   'description' => 'English department'],
            ['name' => 'PL',        'description' => 'PL department'],
        ];

        $departmentIds = [];
        foreach ($departments as $dept) {
            $existing = DB::table('departments')->where('name', $dept['name'])->first();
            if (!$existing) {
                $departmentIds[] = DB::table('departments')->insertGetId([
                    'name'        => $dept['name'],
                    'description' => $dept['description'],
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            } else {
                $departmentIds[] = $existing->id;
            }
        }

        // Seed generations
        $generationYears = [2026, 2027];
        $generationIds = [];
        foreach ($generationYears as $year) {
            $gen = DB::table('generations')->where('year', $year)->first();
            if (!$gen) {
                $generationIds[$year] = DB::table('generations')->insertGetId([
                    'year'       => $year,
                    'is_current' => $year === 2026,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $generationIds[$year] = $gen->id;
            }
        }

        // Classes per teacher with generation
        $classNames = [
            0 => [['name' => 'B12', 'year' => 2026], ['name' => 'B13', 'year' => 2026], ['name' => 'A12', 'year' => 2027]],
            1 => [['name' => 'A13', 'year' => 2027], ['name' => 'B22', 'year' => 2026], ['name' => 'B21', 'year' => 2026]],
            2 => [['name' => 'B23', 'year' => 2027], ['name' => 'B32', 'year' => 2027], ['name' => 'B31', 'year' => 2026]],
        ];

        foreach ($teacherUsers as $index => $user) {
            if (DB::table('teachers')->where('user_id', $user->id)->exists()) {
                continue;
            }

            $departmentId = $departmentIds[$index % count($departmentIds)];

            $teacherId = DB::table('teachers')->insertGetId([
                'user_id'       => $user->id,
                'department_id' => $departmentId,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $classes = $classNames[$index % count($classNames)];
            foreach ($classes as $class) {
                DB::table('classes')->insert([
                    'name'          => $class['name'],
                    'teacher_id'    => $teacherId,
                    'generation_id' => $generationIds[$class['year']],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }
}