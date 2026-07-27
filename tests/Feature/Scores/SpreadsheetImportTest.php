<?php

namespace Tests\Feature\Scores;

use App\Models\AcademicYear;
use App\Models\Generation;
use App\Models\RBAC\Role;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpreadsheetImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeFreshOffering(): SubjectOffering
    {
        $academicYear = AcademicYear::where('year', 2026)->first();
        if (! $academicYear) {
            $academicYear = new AcademicYear(['name' => 'AY 2026', 'is_current' => true]);
            $academicYear->year = 2026;
            $academicYear->save();
        }

        $generation = Generation::firstOrCreate(['year' => 2026], ['name' => 'Batch 2026']);
        $term = Term::firstOrCreate(
            ['academic_year_id' => $academicYear->id, 'term_number' => 1],
            ['name' => 'Term 1']
        );
        $class = SchoolClass::create(['name' => 'Import Test Class ' . Str::random(6), 'generation_id' => $generation->id]);
        $subject = Subject::create(['name' => 'Import Test Subject ' . Str::random(6), 'status' => 'active']);

        return SubjectOffering::create([
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'term_id' => $term->id,
            'academic_year_id' => $academicYear->id,
        ]);
    }

    public function test_marks_are_stored_on_the_first_import_into_a_brand_new_subject_term(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->first()->id]);
        Sanctum::actingAs($admin);
        $offering = $this->makeFreshOffering();

        // No student, no enrollment, no ScoreDetail exists anywhere for this subject/term yet —
        // every column below is brand new to the whole system, with plain (no "(type)") labels,
        // exactly like a teacher's own Excel file.
        $response = $this->postJson(
            "/api/spreadsheet/subject/{$offering->subject_id}/term/{$offering->term_id}/import-file",
            [
                'rows' => [
                    [
                        'student_name' => 'Fresh Import Student',
                        'student_number' => 'PNCFRESH-001',
                        'marks' => [
                            'Quiz 1_unknown' => 8,
                            'Midterm_unknown' => 75,
                        ],
                    ],
                ],
            ]
        );

        $response->assertOk();

        $student = \App\Models\Student::where('student_id_number', 'PNCFRESH-001')->first();
        $this->assertNotNull($student);

        $enrollment = \App\Models\StudentSubjectEnrollment::where('student_id', $student->id)->first();
        $this->assertNotNull($enrollment);

        $details = \App\Models\ScoreDetail::where('score_id', $enrollment->score->id)->get();
        $this->assertCount(2, $details);

        $quiz = $details->firstWhere('label', 'Quiz 1');
        $midterm = $details->firstWhere('label', 'Midterm');
        $this->assertNotNull($quiz);
        $this->assertNotNull($midterm);
        // On the OLD code these would both be null — nothing existed to attach the mark to.
        $this->assertEquals(8, $quiz->mark);
        $this->assertEquals(75, $midterm->mark);
    }

    public function test_second_student_in_the_same_import_reuses_the_column_the_first_student_just_created(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->first()->id]);
        Sanctum::actingAs($admin);
        $offering = $this->makeFreshOffering();

        $response = $this->postJson(
            "/api/spreadsheet/subject/{$offering->subject_id}/term/{$offering->term_id}/import-file",
            [
                'rows' => [
                    [
                        'student_name' => 'First Student',
                        'student_number' => 'PNCFRESH-101',
                        'marks' => ['Quiz 1_unknown' => 5],
                    ],
                    [
                        'student_name' => 'Second Student',
                        'student_number' => 'PNCFRESH-102',
                        'marks' => ['Quiz 1_unknown' => 9],
                    ],
                ],
            ]
        );

        $response->assertOk();

        foreach (['PNCFRESH-101' => 5, 'PNCFRESH-102' => 9] as $number => $expectedMark) {
            $student = \App\Models\Student::where('student_id_number', $number)->first();
            $enrollment = \App\Models\StudentSubjectEnrollment::where('student_id', $student->id)->first();
            $detail = \App\Models\ScoreDetail::where('score_id', $enrollment->score->id)->where('label', 'Quiz 1')->first();
            $this->assertNotNull($detail, "Quiz 1 detail missing for student {$number}");
            $this->assertEquals($expectedMark, $detail->mark);
        }

        // Only ONE ScoreDetail per student — the second student's column reused the first's
        // canonical definition rather than creating a duplicate.
        $this->assertSame(2, \App\Models\ScoreDetail::where('label', 'Quiz 1')->count());
    }
}
