<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectSeeder extends Seeder
{
    public function run(): void
    {
        $subjectNames = [
            'Logic', 'Typing', 'Algorithms', 'OOP',
            'English', 'Design', 'Data Analysis',
            'PL', 'Vue.js', 'PHP', 'Laravel', 'Node.js',
        ];

        foreach ($subjectNames as $name) {
            $exists = DB::table('subjects')
                ->where('name', $name)
                ->exists();

            if ($exists) continue;

            DB::table('subjects')->insert([
                'name'       => $name,
                'status'     => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
