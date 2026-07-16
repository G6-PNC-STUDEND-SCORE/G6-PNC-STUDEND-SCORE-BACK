<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use App\Support\AssessmentTypeDefaults;
use Illuminate\Database\Seeder;

class AssessmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AssessmentTypeDefaults::weights() as $code => $weightPercent) {
            AssessmentType::updateOrCreate(
                ['code' => $code],
                [
                    'name' => ucfirst($code),
                    'weight_percent' => $weightPercent,
                    'is_active' => true,
                ]
            );
        }
    }
}
