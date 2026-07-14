<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Subject;
use App\Models\AssessmentWeight;
use App\Models\Student;
use App\Models\StudentSubjectEnrollment;
use App\Models\Score;
use App\Models\ScoreDetail;

class AssessmentWeightControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // disable middleware if auth/permission middleware blocks endpoints
        $this->withoutMiddleware();
    }

    public function test_store_returns_422_when_sum_not_100()
    {
        $subject = Subject::factory()->create();

        $payload = [
            'weights' => [
                ['name' => 'quiz', 'percent' => 30],
                ['name' => 'exam', 'percent' => 60],
            ],
        ];

        $res = $this->postJson("/api/subjects/{$subject->id}/weights", $payload);
        $res->assertStatus(422);
    }

    public function test_store_persists_when_valid_and_returns_200()
    {
        $subject = Subject::factory()->create();

        $payload = [
            'weights' => [
                ['name' => 'quiz', 'percent' => 40, 'sort_order' => 1],
                ['name' => 'exam', 'percent' => 60, 'sort_order' => 2],
            ],
        ];

        $res = $this->postJson("/api/subjects/{$subject->id}/weights", $payload);
        $res->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseHas('assessment_weights', [
            'subject_id' => $subject->id,
            'name' => 'quiz',
            'percent' => 40,
        ]);

        $this->assertDatabaseHas('assessment_weights', [
            'subject_id' => $subject->id,
            'name' => 'exam',
            'percent' => 60,
        ]);
    }

    public function test_get_weighted_scores_returns_expected_structure_and_values()
    {
        $subject = Subject::factory()->create();

        // Create a subject offering and enrollment chain if factories exist, otherwise minimal setup
        $student = Student::factory()->create();

        // Create an offering and enrollment
        $offering = \App\Models\SubjectOffering::factory()->create(['subject_id' => $subject->id, 'status' => 'active']);
        $enrollment = StudentSubjectEnrollment::factory()->create(['subject_offering_id' => $offering->id, 'student_id' => $student->id]);

        // Create score and details for the enrollment
        $score = Score::factory()->create(['enrollment_id' => $enrollment->id]);
        // detail: quiz (max 10) and exam (max 20)
        ScoreDetail::create([ 'score_id' => $score->id, 'assessment_type_id' => null, 'label' => 'Quiz 1', 'order_number' => 1, 'max_score' => 10, 'mark' => 8 ]);
        ScoreDetail::create([ 'score_id' => $score->id, 'assessment_type_id' => null, 'label' => 'Exam 1', 'order_number' => 2, 'max_score' => 20, 'mark' => 18 ]);

        // Create weights that match labels
        AssessmentWeight::create(['subject_id' => $subject->id, 'name' => 'Quiz 1', 'percent' => 40, 'sort_order' => 1]);
        AssessmentWeight::create(['subject_id' => $subject->id, 'name' => 'Exam 1', 'percent' => 60, 'sort_order' => 2]);

        $res = $this->getJson("/api/subjects/{$subject->id}/weighted-scores");
        $res->assertStatus(200)->assertJson(['success' => true]);

        $data = $res->json('data');
        $this->assertArrayHasKey('weights', $data);
        $this->assertArrayHasKey('results', $data);
        $results = $data['results'];
        $this->assertCount(1, $results);

        $studentResult = $results[0];
        // expected weighted total as in calculation: quiz 8/10 -> 80% * 0.4 = 32; exam 18/20 -> 90% * 0.6 = 54 => total 86
        $this->assertEqualsWithDelta(86.0, $studentResult['weighted_total'], 0.01);
    }
}
