<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'Mathematics'],
            ['name' => 'English Language'],
            ['name' => 'Science'],
            ['name' => 'History'],
            ['name' => 'Geography'],
            ['name' => 'Physics'],
            ['name' => 'Chemistry'],
            ['name' => 'Biology'],
        ];

        foreach ($subjects as $subject) {
            DB::table('subjects')->insert([
                'name' => $subject['name'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
