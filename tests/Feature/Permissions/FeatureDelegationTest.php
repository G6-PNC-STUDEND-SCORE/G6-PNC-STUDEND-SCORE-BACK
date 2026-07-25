<?php

namespace Tests\Feature\Permissions;

use App\Models\RBAC\Permission;
use App\Models\RBAC\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Proves the point of gating every feature behind a `permission:` check instead of
 * `role:admin`: a non-admin role with exactly one permission granted can reach exactly
 * that one feature and nothing else gated the same way.
 */
class FeatureDelegationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsRoleWithPermissions(array $slugs): User
    {
        $suffix = uniqid();
        $role = Role::create(['name' => 'Custom ' . $suffix, 'slug' => 'custom-' . $suffix, 'description' => 'test role']);
        $role->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));

        $user = User::factory()->create(['role_id' => $role->id]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_users_page_is_reachable_once_view_users_is_granted_without_admin_role(): void
    {
        // Baseline: a fresh custom role with NO permissions is forbidden, same as before.
        $this->actingAsRoleWithPermissions([]);
        $this->getJson('/api/users')->assertStatus(403);

        // Grant exactly one permission — no role change involved.
        $this->actingAsRoleWithPermissions(['view-users']);
        $this->getJson('/api/users')->assertOk();

        // Being granted view-users does NOT also grant create-users.
        $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password123', 'role_id' => 1])
            ->assertStatus(403);
    }

    public function test_roles_and_permissions_page_is_reachable_once_manage_roles_permissions_is_granted(): void
    {
        $this->actingAsRoleWithPermissions([]);
        $this->getJson('/api/roles')->assertStatus(403);

        $this->actingAsRoleWithPermissions(['manage-roles-permissions']);
        $this->getJson('/api/roles')->assertOk();
        $this->getJson('/api/permissions')->assertOk();
    }

    public function test_sign_in_domains_page_is_reachable_once_view_email_domain_rules_is_granted(): void
    {
        $this->actingAsRoleWithPermissions([]);
        $this->getJson('/api/email-domain-rules')->assertStatus(403);

        $this->actingAsRoleWithPermissions(['view-email-domain-rules']);
        $this->getJson('/api/email-domain-rules')->assertOk();
    }

    public function test_activity_logs_reachable_once_view_activity_logs_is_granted(): void
    {
        $this->actingAsRoleWithPermissions([]);
        $this->getJson('/api/activity-logs')->assertStatus(403);

        $this->actingAsRoleWithPermissions(['view-activity-logs']);
        $this->getJson('/api/activity-logs')->assertOk();
    }

    public function test_grade_boundary_and_assessment_type_and_term_writes_require_their_own_permission(): void
    {
        $this->actingAsRoleWithPermissions(['view-assessment-types']);
        // Can view, but not create — view and create are genuinely separate grants.
        $this->getJson('/api/assessment-types')->assertOk();
        $this->postJson('/api/assessment-types', ['code' => 'x', 'name' => 'X', 'weight_percent' => 5])
            ->assertStatus(403);

        $this->actingAsRoleWithPermissions(['create-assessment-types']);
        $this->postJson('/api/assessment-types', ['code' => 'extra', 'name' => 'Extra', 'weight_percent' => 5])
            ->assertCreated();
    }

    public function test_teacher_role_keeps_previously_open_read_access_to_generations_terms_assessment_types_grade_boundaries(): void
    {
        $teacher = User::factory()->create(['role_id' => Role::where('slug', 'teacher')->first()->id]);
        Sanctum::actingAs($teacher);

        // These were reachable by ANY authenticated user before this change (no permission
        // existed) — the migration to permission-gating must not regress that for teachers.
        $this->getJson('/api/generations')->assertOk();
        $this->getJson('/api/terms')->assertOk();
        $this->getJson('/api/assessment-types')->assertOk();
        $this->getJson('/api/grade-boundaries')->assertOk();

        // But teacher still can't reach admin-configuration writes or Users/Roles.
        $this->postJson('/api/terms', ['name' => 'X', 'academic_year_id' => 1, 'term_number' => 9])->assertStatus(403);
        $this->getJson('/api/users')->assertStatus(403);
    }

    public function test_admin_bypasses_every_new_gate_without_any_explicit_grant(): void
    {
        $admin = User::factory()->create(['role_id' => Role::where('slug', 'admin')->first()->id]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/roles')->assertOk();
        $this->getJson('/api/email-domain-rules')->assertOk();
        $this->getJson('/api/activity-logs')->assertOk();
    }
}
