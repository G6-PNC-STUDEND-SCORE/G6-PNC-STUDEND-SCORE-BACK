<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubjectTermSeeder extends Seeder
{
    /**
     * Subject-term assignments based on curriculum:
     *
     * Term 1: Logic, BCU, MS Office, English, PL, Design
     * Term 2: Web design, Algorithm, Git, English for IT, PL
     * Term 3: PHP, Database, Javascript, QA, English, English for IT, PL
     * Term 4: Laravel, Node.js, Vue.js, OOP, Typescript
     *
     * Also creates any new subjects that don't exist yet.
     */
    public function run(): void
    {
        // ── 1. Define all subjects grouped by term ──────────────────────
        $termSubjects = [
            1 => ['Logic', 'BCU', 'MS Office', 'English', 'PL', 'Design'],
            2 => ['Web design', 'Algorithms', 'Git', 'English for IT', 'PL'],
            3 => ['PHP', 'Database', 'Javascript', 'QA', 'English', 'English for IT', 'PL'],
            4 => ['Laravel', 'Node.js', 'Vue.js', 'OOP', 'Typescript'],
        ];

        // Flatten all unique subject names
        $allSubjectNames = array_unique(array_merge(...array_values($termSubjects)));

        // ── 2. Create or find subjects ──────────────────────────────────
        $subjectIdMap = []; // name => id
        foreach ($allSubjectNames as $name) {
            $subject = DB::table('subjects')
                ->where('name', $name)
                ->first();

            if ($subject) {
                $subjectIdMap[$name] = $subject->id;
            } else {
                $subjectIdMap[$name] = DB::table('subjects')->insertGetId([
                    'name'       => $name,
                    'status'     => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("Created new subject: {$name} (ID: {$subjectIdMap[$name]})");
            }
        }

        // ── 3. Get term IDs (ordered by term_number) ────────────────────
        $terms = DB::table('terms')
            ->orderBy('term_number')
            ->get(['id', 'term_number']);

        if ($terms->isEmpty()) {
            $this->command->error('No terms found! Run the StudentSeeder first to create terms.');
            return;
        }

        $termIdByNumber = [];
        foreach ($terms as $term) {
            $termIdByNumber[$term->term_number] = $term->id;
        }

        // ── 4. Populate the subject_term pivot table ────────────────────
        $inserted = 0;
        $skipped = 0;

        foreach ($termSubjects as $termNumber => $subjectNames) {
            $termId = $termIdByNumber[$termNumber] ?? null;
            if (!$termId) {
                $this->command->warn("Term number {$termNumber} not found in database, skipping.");
                continue;
            }

            foreach ($subjectNames as $subjectName) {
                $subjectId = $subjectIdMap[$subjectName] ?? null;
                if (!$subjectId) continue;

                // Check if entry already exists
                $exists = DB::table('subject_term')
                    ->where('subject_id', $subjectId)
                    ->where('term_id', $termId)
                    ->exists();

                if ($exists) {
                    $skipped++;
                } else {
                    DB::table('subject_term')->insert([
                        'subject_id' => $subjectId,
                        'term_id'    => $termId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $inserted++;
                }
            }
        }

        // ── 5. Report results ──────────────────────────────────────────
        $this->command->info("Subject-Term pivot table seeded successfully!");
        $this->command->info("  - Inserted: {$inserted} new entries");
        $this->command->info("  - Skipped (already exist): {$skipped} entries");

        // ── 6. Show summary per term ────────────────────────────────────
        $this->command->line('');
        $this->command->line('Subject-Term Assignment Summary:');
        $this->command->line(str_repeat('-', 60));

        foreach ($termSubjects as $termNumber => $subjectNames) {
            $termName = "Term {$termNumber}";
            $subjectList = implode(', ', $subjectNames);
            $this->command->line("  {$termName}: {$subjectList}");
        }

        $this->command->line(str_repeat('-', 60));
    }
}
