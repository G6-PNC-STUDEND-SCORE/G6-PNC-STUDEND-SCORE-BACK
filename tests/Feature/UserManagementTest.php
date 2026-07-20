<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\RBAC\Role;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Role $adminRole;
    protected Role $teacherRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Get or create roles
        $this->adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $this->teacherRole = Role::firstOrCreate(['slug' => 'teacher'], ['name' => 'Teacher']);

        // Create an admin user for authentication
        $this->adminUser = User::factory()->create([
            'role_id' => $this->adminRole->id,
        ]);
    }

    public function test_can_list_users_with_pagination(): void
    {
        // Create a few users
        User::factory()->count(15)->create([
            'role_id' => $this->teacherRole->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/users?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'current_page',
                    'data',
                    'last_page',
                    'per_page',
                    'total',
                ],
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertCount(10, $response->json('data.data'));
    }

    public function test_can_search_users_by_name_or_email_or_role(): void
    {
        $uniqueName = 'UniqueTestUserXYZ';
        $uniqueEmail = 'unique_xyz@student.edu';
        User::factory()->create([
            'name' => $uniqueName,
            'email' => $uniqueEmail,
            'role_id' => $this->teacherRole->id,
        ]);

        // Search by name
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/users?search=' . $uniqueName);

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.data')));
        $this->assertEquals($uniqueName, $response->json('data.data.0.name'));

        // Search by role name
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/users?search=Teacher');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($response->json('data.data')));
    }

    public function test_can_create_user_with_validation(): void
    {
        $payload = [
            'name' => 'New User Test',
            'email' => 'new_user_test@example.com',
            'password' => 'password123',
            'role_id' => $this->teacherRole->id,
            'status' => 'active',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/users', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'User created successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'new_user_test@example.com',
        ]);
    }

    public function test_can_update_user(): void
    {
        $user = User::factory()->create([
            'role_id' => $this->teacherRole->id,
        ]);

        $payload = [
            'name' => 'Updated Name Test',
            'email' => $user->email,
            'role_id' => $this->adminRole->id,
            'status' => 'inactive',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson('/api/users/' . $user->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User updated successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name Test',
            'role_id' => $this->adminRole->id,
            'status' => 'inactive',
        ]);
    }

    public function test_cannot_delete_self(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson('/api/users/' . $this->adminUser->id);

        $response->assertStatus(403);
    }

    public function test_can_delete_other_user(): void
    {
        $otherUser = User::factory()->create([
            'role_id' => $this->teacherRole->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson('/api/users/' . $otherUser->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'User deleted successfully.',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $otherUser->id,
        ]);
    }

    public function test_can_bulk_delete_users(): void
    {
        $users = User::factory()->count(3)->create([
            'role_id' => $this->teacherRole->id,
        ]);

        $ids = $users->pluck('id')->toArray();

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson('/api/users/bulk-delete', [
                'ids' => $ids,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'deleted_count' => 3,
                ],
            ]);

        foreach ($ids as $id) {
            $this->assertDatabaseMissing('users', [
                'id' => $id,
            ]);
        }
    }
}
