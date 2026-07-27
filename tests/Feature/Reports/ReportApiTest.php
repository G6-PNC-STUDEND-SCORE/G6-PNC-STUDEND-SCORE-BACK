<?php

namespace Tests\Feature\Reports;

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

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private SchoolClass $class;
    private AcademicYear $academicYear;
    private Term $term;
    private Subject $math;
    private Subject $english;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->first()->id]);
        Sanctum::actingAs($admin);

        $this->academicYear = AcademicYear::where('year', 2026)->first();
        $generation = Generation::where('year', 2026)->first();
        $this->term = Term::where('academic_year_id', $this->academicYear->id)
            ->where('term_number', 1)
            ->first();

        $this->class = SchoolClass::create([
            'name' => 'Grade 6A ' . Str::random(5),
            'generation_id' => $generation->id,
        ]);

        $this->math = Subject::create(['name' => 'Math ' . Str::random(5), 'status' => 'active']);
        $this->english = Subject::create(['name' => 'English ' . Str::random(5), 'status' => 'active']);
    }

    public function test_student_report_returns_report_card_shape(): void
    {
        $student = $this->createStudentWithScores('John Doe', [95, 85]);
        $this->createStudentWithScores('Alice', [90, 90]);
        $this->createStudentWithScores('Bob', [40, 40]);

        $response = $this->getJson("/api/reports/student/{$student->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Student report retrieved successfully.')
            ->assertJsonPath('data.student_id', $student->id)
            ->assertJsonPath('data.student_name', 'John Doe')
            ->assertJsonPath('data.total_score', 180)
            ->assertJsonPath('data.average_score', 90)
            ->assertJsonPath('data.rank', 1)
            ->assertJsonCount(2, 'data.subjects');
    }

    public function test_class_summary_returns_student_average_statistics(): void
    {
        $this->createStudentWithScores('John Doe', [95, 85]);
        $this->createStudentWithScores('Alice', [90, 90]);
        $this->createStudentWithScores('Bob', [40, 40]);

        $response = $this->getJson("/api/reports/class/{$this->class->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Class summary retrieved successfully.')
            ->assertJsonPath('data.class_id', $this->class->id)
            ->assertJsonPath('data.total_students', 3)
            ->assertJsonPath('data.highest_average', 90)
            ->assertJsonPath('data.lowest_average', 40)
            ->assertJsonPath('data.class_average', 73.33)
            ->assertJsonPath('data.pass_count', 2)
            ->assertJsonPath('data.fail_count', 1);
    }

    public function test_class_rankings_handle_ties(): void
    {
        $this->createStudentWithScores('John Doe', [95, 85]);
        $this->createStudentWithScores('Alice', [90, 90]);
        $this->createStudentWithScores('Bob', [40, 40]);

        $response = $this->getJson("/api/reports/class/{$this->class->id}/rankings");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Class rankings retrieved successfully.')
            ->assertJsonPath('data.0.rank', 1)
            ->assertJsonPath('data.1.rank', 1)
            ->assertJsonPath('data.2.rank', 3);
    }

    public function test_report_endpoints_return_404_for_missing_records(): void
    {
        $this->getJson('/api/reports/student/999999')->assertNotFound();
        $this->getJson('/api/reports/class/999999')->assertNotFound();
        $this->getJson('/api/reports/class/999999/rankings')->assertNotFound();
    }

    private function createStudentWithScores(string $name, array $scores): Student
    {
        $studentUser = User::factory()->create([
            'name' => $name,
            'role_id' => Role::where('slug', 'student')->first()->id,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'student_id_number' => 'PNC-' . Str::random(8),
            'generation_id' => $this->class->generation_id,
        ]);

        $history = StudentClassHistory::create([
            'student_id' => $student->id,
            'class_id' => $this->class->id,
            'generation_id' => $this->class->generation_id,
            'status' => 'active',
        ]);

        foreach ([$this->math, $this->english] as $index => $subject) {
            $offering = SubjectOffering::firstOrCreate([
                'subject_id' => $subject->id,
                'class_id' => $this->class->id,
                'term_id' => $this->term->id,
                'academic_year_id' => $this->academicYear->id,
            ]);

            $enrollment = StudentSubjectEnrollment::create([
                'student_id' => $student->id,
                'student_class_history_id' => $history->id,
                'subject_offering_id' => $offering->id,
            ]);

            Score::create([
                'student_subject_enrollment_id' => $enrollment->id,
                'total' => $scores[$index],
            ]);
        }

        return $student;
    }
}
