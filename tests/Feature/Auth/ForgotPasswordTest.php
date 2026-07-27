<?php

namespace Tests\Feature\Auth;

use App\Models\RBAC\Role;
use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_points_at_the_configured_frontend_url(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role_id' => Role::where('slug', 'admin')->first()->id]);

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo(
            $user,
            PasswordResetNotification::class,
            function (PasswordResetNotification $notification) use ($user) {
                $mail = $notification->toMail($user);
                return str_starts_with($mail->actionUrl, config('app.frontend_url'))
                    && ! str_contains($mail->actionUrl, 'localhost:3000');
            }
        );
    }
}
