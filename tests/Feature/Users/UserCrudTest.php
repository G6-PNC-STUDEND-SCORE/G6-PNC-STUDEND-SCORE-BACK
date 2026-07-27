<?php

namespace Tests\Feature\Users;

use App\Models\RBAC\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->first()->id]);
        Sanctum::actingAs($admin);
        return $admin;
    }

    public function test_admin_can_list_users(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/users');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_admin_can_create_a_user(): void
    {
        $this->actingAsAdmin();
        $studentRole = Role::where('slug', 'student')->first();

        $response = $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new.user@example.com',
            'password' => 'password123',
            'role_id' => $studentRole->id,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', ['email' => 'new.user@example.com']);
    }

    public function test_admin_can_update_a_user(): void
    {
        $this->actingAsAdmin();
        $studentRole = Role::where('slug', 'student')->first();
        $user = User::factory()->create(['role_id' => $studentRole->id, 'name' => 'Old Name']);

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
            'role_id' => $studentRole->id,
        ]);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_delete_a_user(): void
    {
        $this->actingAsAdmin();
        $studentRole = Role::where('slug', 'student')->first();
        $user = User::factory()->create(['role_id' => $studentRole->id]);

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->deleteJson("/api/users/{$admin->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_non_admin_is_forbidden_from_listing_users(): void
    {
        $teacher = User::factory()->create(['role_id' => Role::where('slug', 'teacher')->first()->id]);
        Sanctum::actingAs($teacher);

        $response = $this->getJson('/api/users');

        $response->assertStatus(403);
    }
}
