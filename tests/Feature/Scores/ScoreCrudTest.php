<?php

namespace Tests\Feature\Scores;

use App\Models\AcademicYear;
use App\Models\Generation;
use App\Models\RBAC\Role;
use App\Models\SchoolClass;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\StudentSubjectEnrollment;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScoreCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->first()->id]);
        Sanctum::actingAs($admin);
        return $admin;
    }

    /**
     * Builds the minimal Subject -> Term -> SubjectOffering -> Enrollment chain
     * a Score needs to exist, plus the enrolled Student's own User account.
     */
    private function makeEnrollment(): StudentSubjectEnrollment
    {
        // DatabaseSeeder (via $seed = true in TestCase) already seeds an academic year,
        // generation, and terms for 2026 — reuse those (unique constraints on year/term_number)
        // and only create fresh class/subject/student rows scoped to this test.
        $academicYear = AcademicYear::where('year', 2026)->first();
        if (! $academicYear) {
            // 'year' isn't in AcademicYear's $fillable, so it must be set directly.
            $academicYear = new AcademicYear(['name' => 'AY 2026', 'is_current' => true]);
            $academicYear->year = 2026;
            $academicYear->save();
        }

        $generation = Generation::firstOrCreate(['year' => 2026]);

        $term = Term::firstOrCreate(
            ['academic_year_id' => $academicYear->id, 'term_number' => 1],
            ['name' => 'Term 1']
        );

        $class = SchoolClass::create(['name' => 'Test Class ' . Str::random(6), 'generation_id' => $generation->id]);

        $subject = Subject::create(['name' => 'Test Subject ' . Str::random(6), 'status' => 'active']);

        $offering = SubjectOffering::create([
            'subject_id' => $subject->id,
            'class_id' => $class->id,
            'term_id' => $term->id,
            'academic_year_id' => $academicYear->id,
        ]);

        $studentUser = User::factory()->create(['role_id' => Role::where('slug', 'student')->first()->id]);
        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id_number' => 'PNCTEST-' . Str::random(8),
            'generation_id' => $generation->id,
        ]);

        $history = StudentClassHistory::create([
            'student_id' => $student->id,
            'class_id' => $class->id,
            'generation_id' => $generation->id,
            'status' => 'active',
        ]);

        return StudentSubjectEnrollment::create([
            'student_id' => $student->id,
            'student_class_history_id' => $history->id,
            'subject_offering_id' => $offering->id,
        ]);
    }

    public function test_admin_can_create_a_score_with_details(): void
    {
        $this->actingAsAdmin();
        $enrollment = $this->makeEnrollment();

        $response = $this->postJson('/api/scores', [
            'student_subject_enrollment_id' => $enrollment->id,
            'details' => [
                ['type' => 'quiz', 'label' => 'Quiz 1', 'mark' => 8, 'max_score' => 10],
                ['type' => 'final', 'label' => 'Final Exam', 'mark' => 70, 'max_score' => 100],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('scores', ['student_subject_enrollment_id' => $enrollment->id]);
    }

    public function test_updating_a_detail_recalculates_total_and_grade(): void
    {
        $this->actingAsAdmin();
        $enrollment = $this->makeEnrollment();

        $createResponse = $this->postJson('/api/scores', [
            'student_subject_enrollment_id' => $enrollment->id,
            'details' => [
                ['type' => 'final', 'label' => 'Final Exam', 'mark' => 50, 'max_score' => 100],
            ],
        ]);
        $score = Score::find($createResponse->json('data.id'));
        $detail = $score->details()->first();

        $response = $this->putJson("/api/scores/{$score->id}/details/{$detail->id}", [
            'mark' => 90,
        ]);

        $response->assertOk();
        $score->refresh();
        $this->assertNotNull($score->total);
        $this->assertGreaterThan(30, $score->total);
    }

    public function test_admin_can_delete_a_score(): void
    {
        $this->actingAsAdmin();
        $enrollment = $this->makeEnrollment();
        $score = Score::create(['student_subject_enrollment_id' => $enrollment->id]);

        $response = $this->deleteJson("/api/scores/{$score->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('scores', ['id' => $score->id]);
    }

    public function test_student_cannot_view_another_students_score(): void
    {
        $enrollment = $this->makeEnrollment();
        $score = Score::create(['student_subject_enrollment_id' => $enrollment->id]);

        $otherStudent = User::factory()->create(['role_id' => Role::where('slug', 'student')->first()->id]);
        Sanctum::actingAs($otherStudent);

        $response = $this->getJson("/api/scores/{$score->id}");

        $response->assertStatus(403);
    }
}
