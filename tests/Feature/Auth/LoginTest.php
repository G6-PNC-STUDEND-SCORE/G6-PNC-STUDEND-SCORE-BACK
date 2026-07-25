<?php

namespace Tests\Feature\Auth;

use App\Models\RBAC\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_credentials_return_token(): void
    {
        $role = Role::where('slug', 'admin')->first();
        $user = User::factory()->create([
            'email' => 'login-test@example.com',
            'password' => Hash::make('correct-password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user', 'token', 'message']);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $role = Role::where('slug', 'admin')->first();
        $user = User::factory()->create([
            'email' => 'login-test-2@example.com',
            'password' => Hash::make('correct-password'),
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_unknown_email_is_rejected(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody-registered@example.com',
            'password' => 'whatever',
        ]);

        $response->assertStatus(401);
    }
}
