<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class AssessmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'quiz', 'name' => 'Quiz', 'weight_percent' => 20],
            ['code' => 'assignment', 'name' => 'Assignment', 'weight_percent' => 10],
            ['code' => 'midterm', 'name' => 'Midterm', 'weight_percent' => 30],
            ['code' => 'final', 'name' => 'Final', 'weight_percent' => 40],
        ];

        foreach ($types as $type) {
            AssessmentType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'weight_percent' => $type['weight_percent'],
                    'is_active' => true,
                ]
            );
        }
    }
}
