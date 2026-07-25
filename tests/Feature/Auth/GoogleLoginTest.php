<?php

namespace Tests\Feature\Auth;

use App\Models\EmailDomainRule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Auth\GoogleIdTokenVerifierInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakeGoogleIdTokenVerifier;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGooglePayload(string $email, string $sub = 'google-sub-123'): array
    {
        return [
            'sub' => $sub,
            'email' => $email,
            'name' => 'Test Google User',
        ];
    }

    public function test_new_user_with_recognized_student_domain_is_created_as_student(): void
    {
        $this->app->instance(
            GoogleIdTokenVerifierInterface::class,
            new FakeGoogleIdTokenVerifier($this->fakeGooglePayload('new.student@student.passerellesnumeriques.org'))
        );

        $response = $this->postJson('/api/google-login', ['credential' => 'fake-token']);

        $response->assertOk()->assertJsonPath('user.role', 'student');

        $user = User::where('email', 'new.student@student.passerellesnumeriques.org')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Student::where('user_id', $user->id)->exists());
    }

    public function test_new_user_with_recognized_teacher_domain_is_created_as_teacher(): void
    {
        $this->app->instance(
            GoogleIdTokenVerifierInterface::class,
            new FakeGoogleIdTokenVerifier($this->fakeGooglePayload('new.teacher@passerellesnumeriques.org'))
        );

        $response = $this->postJson('/api/google-login', ['credential' => 'fake-token']);

        $response->assertOk()->assertJsonPath('user.role', 'teacher');

        $user = User::where('email', 'new.teacher@passerellesnumeriques.org')->first();
        $this->assertNotNull($user);
        $this->assertTrue(Teacher::where('user_id', $user->id)->exists());
    }

    public function test_unrecognized_domain_is_rejected(): void
    {
        $this->app->instance(
            GoogleIdTokenVerifierInterface::class,
            new FakeGoogleIdTokenVerifier($this->fakeGooglePayload('someone@not-a-configured-domain.org'))
        );

        $response = $this->postJson('/api/google-login', ['credential' => 'fake-token']);

        $response->assertStatus(403);
        $this->assertNull(User::where('email', 'someone@not-a-configured-domain.org')->first());
    }

    public function test_existing_user_matched_by_email_is_linked_not_duplicated(): void
    {
        $existing = User::factory()->create([
            'email' => 'already.here@passerellesnumeriques.org',
            'google_id' => null,
        ]);

        $this->app->instance(
            GoogleIdTokenVerifierInterface::class,
            new FakeGoogleIdTokenVerifier($this->fakeGooglePayload('already.here@passerellesnumeriques.org', 'google-sub-456'))
        );

        $response = $this->postJson('/api/google-login', ['credential' => 'fake-token']);

        $response->assertOk();

        $this->assertSame(1, User::where('email', 'already.here@passerellesnumeriques.org')->count());
        $existing->refresh();
        $this->assertSame('google-sub-456', $existing->google_id);
    }

    public function test_invalid_google_credential_is_rejected(): void
    {
        $this->app->instance(
            GoogleIdTokenVerifierInterface::class,
            new FakeGoogleIdTokenVerifier(null)
        );

        $response = $this->postJson('/api/google-login', ['credential' => 'garbage-token']);

        $response->assertStatus(401);
    }
}
