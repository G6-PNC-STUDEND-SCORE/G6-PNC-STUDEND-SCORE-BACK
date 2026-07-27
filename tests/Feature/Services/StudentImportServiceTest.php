<?php

namespace Tests\Feature\Services;

use App\Models\Generation;
use App\Models\RBAC\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): StudentImportService
    {
        return app(StudentImportService::class);
    }

    public function test_matches_existing_student_by_student_id_number(): void
    {
        $existingUser = User::factory()->create(['role_id' => Role::where('slug', 'student')->first()->id]);
        $existing = Student::create(['user_id' => $existingUser->id, 'student_id_number' => 'PNC2026-999']);
        $countBefore = Student::count();

        $result = $this->service()->findOrCreateStudent('PNC2026-999', 'A Different Name', null);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame($countBefore, Student::count());
    }

    public function test_matches_existing_student_by_unambiguous_name_and_generation(): void
    {
        $generation = Generation::firstOrCreate(['year' => 2050], ['name' => 'Batch 2050']);
        $existingUser = User::factory()->create(['name' => 'Unique Name', 'role_id' => Role::where('slug', 'student')->first()->id]);
        $existing = Student::create(['user_id' => $existingUser->id, 'student_id_number' => 'PNC2050-001', 'generation_id' => $generation->id]);
        $countBefore = Student::count();

        $result = $this->service()->findOrCreateStudent(null, 'Unique Name', $generation->id);

        $this->assertSame($existing->id, $result->id);
        $this->assertSame($countBefore, Student::count());
    }

    public function test_does_not_merge_ambiguous_name_matches(): void
    {
        $generation = Generation::firstOrCreate(['year' => 2051], ['name' => 'Batch 2051']);
        foreach (range(1, 2) as $i) {
            $user = User::factory()->create(['name' => 'Same Name', 'role_id' => Role::where('slug', 'student')->first()->id]);
            Student::create(['user_id' => $user->id, 'student_id_number' => "PNC2051-00{$i}", 'generation_id' => $generation->id]);
        }
        $countBefore = Student::count();

        $result = $this->service()->findOrCreateStudent(null, 'Same Name', $generation->id);

        // A third, brand-new student was created rather than guessing between the two matches.
        $this->assertSame($countBefore + 1, Student::count());
        $this->assertTrue($result->is_placeholder);
    }

    public function test_new_student_gets_a_real_email_under_the_given_domain(): void
    {
        $result = $this->service()->findOrCreateStudent(null, 'Sok Dara', null, 'student.example.org');

        $this->assertSame('sok.dara@student.example.org', $result->user->email);
    }

    public function test_email_collision_gets_a_numeric_suffix(): void
    {
        User::factory()->create(['email' => 'sok.dara@student.example.org']);

        $result = $this->service()->findOrCreateStudent(null, 'Sok Dara', null, 'student.example.org');

        $this->assertSame('sok.dara2@student.example.org', $result->user->email);
    }

    public function test_without_a_domain_falls_back_to_a_synthetic_placeholder_email(): void
    {
        $result = $this->service()->findOrCreateStudent(null, 'No Domain Given', null, null);

        $this->assertStringEndsWith('@example.com', $result->user->email);
    }

    public function test_reimporting_the_same_student_across_two_classes_creates_one_student_two_histories(): void
    {
        $generation = Generation::firstOrCreate(['year' => 2052], ['name' => 'Batch 2052']);
        $classA = SchoolClass::create(['name' => 'Class A', 'generation_id' => $generation->id]);
        $classB = SchoolClass::create(['name' => 'Class B', 'generation_id' => $generation->id]);

        $countBefore = Student::count();
        $service = $this->service();
        $student = $service->findOrCreateStudent('PNC2052-777', 'Repeat Student', $generation->id, 'student.example.org');
        $service->assignActiveClass($student, $classA->id, $generation->id);

        // Re-import for a second subject taught in a different class — same student number.
        $sameStudent = $service->findOrCreateStudent('PNC2052-777', 'Repeat Student', $generation->id, 'student.example.org');
        $service->assignActiveClass($sameStudent, $classB->id, $generation->id);

        $this->assertSame($student->id, $sameStudent->id);
        $this->assertSame($countBefore + 1, Student::count());
        $this->assertSame(2, \App\Models\StudentClassHistory::where('student_id', $student->id)->count());
        $this->assertSame(1, \App\Models\StudentClassHistory::where('student_id', $student->id)->where('status', 'active')->count());
    }
}
