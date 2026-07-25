<?php

namespace Tests\Feature\Classes;

use App\Models\RBAC\Role;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClassCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->first()->id]);
        Sanctum::actingAs($admin);
        return $admin;
    }

    public function test_admin_can_list_classes(): void
    {
        $this->actingAsAdmin();
        SchoolClass::create(['name' => 'Class A']);

        $response = $this->getJson('/api/classes');

        $response->assertOk()->assertJsonPath('success', true);
    }

    public function test_admin_can_create_a_class(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/classes', ['name' => 'New Class']);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('classes', ['name' => 'New Class']);
    }

    public function test_admin_can_update_a_class(): void
    {
        $this->actingAsAdmin();
        $class = SchoolClass::create(['name' => 'Old Name']);

        $response = $this->putJson("/api/classes/{$class->id}", ['name' => 'Updated Name']);

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_delete_a_class(): void
    {
        $this->actingAsAdmin();
        $class = SchoolClass::create(['name' => 'To Delete']);

        $response = $this->deleteJson("/api/classes/{$class->id}");

        $response->assertOk()->assertJsonPath('success', true);
        $this->assertSoftDeleted('classes', ['id' => $class->id]);
    }

    public function test_teacher_can_view_but_not_create_classes(): void
    {
        $teacher = User::factory()->create(['role_id' => Role::where('slug', 'teacher')->first()->id]);
        Sanctum::actingAs($teacher);

        $this->getJson('/api/classes')->assertOk();
        $this->postJson('/api/classes', ['name' => 'Should Fail'])->assertStatus(403);
    }
}
