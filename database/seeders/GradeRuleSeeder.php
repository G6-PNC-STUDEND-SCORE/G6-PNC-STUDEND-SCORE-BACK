<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GradeRuleSeeder extends Seeder
{
    public function run(): void
    {
        $gradeRules = [
            ['min_score' => 90, 'max_score' => 100, 'grade' => 'A'],
            ['min_score' => 80, 'max_score' => 89.99, 'grade' => 'B'],
            ['min_score' => 70, 'max_score' => 79.99, 'grade' => 'C'],
            ['min_score' => 60, 'max_score' => 69.99, 'grade' => 'D'],
            ['min_score' => 0, 'max_score' => 59.99, 'grade' => 'F'],
        ];

        foreach ($gradeRules as $rule) {
            DB::table('grade_rules')->insert([
                'min_score' => $rule['min_score'],
                'max_score' => $rule['max_score'],
                'grade' => $rule['grade'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
