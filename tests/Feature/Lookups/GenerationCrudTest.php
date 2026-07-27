<?php

namespace Tests\Feature\Lookups;

use App\Models\AcademicYear;
use App\Models\Generation;
use App\Models\RBAC\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerationCrudTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->first()->id]);
        Sanctum::actingAs($admin);
        return $admin;
    }

    public function test_generations_index_returns_the_real_generation_fields(): void
    {
        $this->actingAsAdmin();
        Generation::create(['year' => 2099, 'name' => 'Real Name For 2099']);

        $response = $this->getJson('/api/generations');

        $response->assertOk()->assertJsonPath('success', true);
        $names = collect($response->json('data'))->pluck('name')->all();
        // The old dead route synthesized 'Generation {year}' — the real model field should win.
        $this->assertNotContains('Generation 2099', $names);
    }

    public function test_admin_can_manage_generations(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/generations', ['year' => 2088]);
        $create->assertCreated()->assertJsonPath('success', true);
        $id = $create->json('data.id');

        $this->putJson("/api/generations/{$id}", ['year' => 2089])->assertOk();
        $this->assertDatabaseHas('generations', ['id' => $id, 'year' => 2089]);

        $this->deleteJson("/api/generations/{$id}")->assertOk();
        $this->assertDatabaseMissing('generations', ['id' => $id]);
    }

    public function test_admin_can_manage_terms(): void
    {
        $this->actingAsAdmin();
        $academicYear = new AcademicYear(['name' => 'AY Test', 'is_current' => false]);
        $academicYear->year = 2088;
        $academicYear->save();

        $create = $this->postJson('/api/terms', [
            'academic_year_id' => $academicYear->id,
            'name' => 'Test Term',
            'term_number' => 1,
        ]);
        $create->assertCreated()->assertJsonPath('success', true);
        $id = $create->json('data.id');

        $this->getJson('/api/terms')->assertOk()->assertJsonPath('success', true);

        $this->putJson("/api/terms/{$id}", ['name' => 'Renamed Term'])->assertOk();
        $this->assertDatabaseHas('terms', ['id' => $id, 'name' => 'Renamed Term']);

        $this->deleteJson("/api/terms/{$id}")->assertOk();
        $this->assertDatabaseMissing('terms', ['id' => $id]);
    }

    public function test_admin_can_manage_assessment_types(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/assessment-types', [
            'code' => 'extra-credit',
            'name' => 'Extra Credit',
            'weight_percent' => 5,
        ]);
        $create->assertCreated()->assertJsonPath('success', true);
        $id = $create->json('data.id');

        $this->getJson('/api/assessment-types')->assertOk()->assertJsonPath('success', true);

        $this->putJson("/api/assessment-types/{$id}", ['weight_percent' => 7])->assertOk();
        $this->assertDatabaseHas('assessment_types', ['id' => $id, 'weight_percent' => 7]);

        $this->deleteJson("/api/assessment-types/{$id}")->assertOk();
        $this->assertDatabaseMissing('assessment_types', ['id' => $id]);
    }

    public function test_teacher_cannot_manage_generations(): void
    {
        $teacher = User::factory()->create(['role_id' => Role::where('slug', 'teacher')->first()->id]);
        Sanctum::actingAs($teacher);

        $this->postJson('/api/generations', ['year' => 2077])->assertStatus(403);
    }
}
