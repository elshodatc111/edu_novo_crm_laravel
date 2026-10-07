<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: hodim lavozimini o'zgartirish - ruxsat qoidalari, shablonga qaytarish, telefon takrorlanishi. */
class V13RoleChangeTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $u, array $over = []): array
    {
        return ['name' => $u->name, 'username' => $u->username, 'phone' => $u->phone, 'status' => 'active', ...$over];
    }

    public function test_sadmin_changes_manager_to_operator_and_permissions_reset_to_template(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $manager = $this->user(Role::Manager, $branch, ['students.view', 'cashbox.view', 'cashbox.request'], ['phone' => '+998 90 111 2233']);
        $manager->tokens()->create(['name' => 'tel', 'token' => hash('sha256', 'x'), 'abilities' => ['*']]);

        $this->actingAs($sadmin)->get("/staff/{$manager->id}/edit")->assertOk()->assertSee('Operator');

        $this->actingAs($sadmin)->put("/staff/{$manager->id}", $this->payload($manager, ['role' => 'operator']))
            ->assertRedirect(route('staff.index'))->assertSessionHas('success');

        $manager->refresh();
        $this->assertSame(Role::Operator, $manager->role);
        $this->assertEqualsCanonicalizing(\App\Support\PermissionRegistry::defaultsFor(Role::Operator), $manager->permissionKeys());
        $this->assertSame(0, $manager->tokens()->count(), 'Mobil seanslar tugatilishi kerak.');
        $this->assertTrue(AuditLog::where('action', 'staff.role_changed')->where('subject_id', $manager->id)->exists());
    }

    public function test_admin_can_change_within_own_branch_but_not_to_admin(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage']);
        $operator = $this->user(Role::Operator, $branch, [], ['phone' => '+998 90 200 3040']);

        $this->actingAs($admin)->put("/staff/{$operator->id}", $this->payload($operator, ['role' => 'admin']))->assertSessionHasErrors('role');
        $this->assertSame(Role::Operator, $operator->fresh()->role);

        $this->actingAs($admin)->put("/staff/{$operator->id}", $this->payload($operator, ['role' => 'manager']))->assertRedirect();
        $this->assertSame(Role::Manager, $operator->fresh()->role);
    }

    public function test_admin_cannot_change_role_in_other_branch(): void
    {
        $admin = $this->user(Role::Admin, $this->branch('A'), ['staff.view', 'staff.manage']);
        $foreign = $this->user(Role::Manager, $this->branch('B'), [], ['phone' => '+998 90 300 4050']);

        $this->actingAs($admin)->put("/staff/{$foreign->id}", $this->payload($foreign, ['role' => 'operator']))->assertNotFound();
    }

    public function test_user_cannot_change_own_role(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage'], ['phone' => '+998 90 400 5060']);

        $this->actingAs($admin)->put("/staff/{$admin->id}", $this->payload($admin, ['role' => 'manager']))->assertForbidden();
    }

    public function test_teacher_with_running_group_cannot_change_role(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $cat = $this->catalog($branch);
        $teacher = $cat['teacher'];
        $groupAdmin = $this->user(Role::Admin, $branch, ['groups.create']);
        $this->actingAs($groupAdmin);
        app(\App\Services\GroupService::class)->create($this->groupPayload($cat, ['starts_on' => now()->toDateString()]), $groupAdmin);

        $this->actingAs($sadmin)->put("/staff/{$teacher->id}", $this->payload($teacher, ['role' => 'manager', 'phone' => '+998 90 600 7080']))->assertSessionHasErrors('role');
        $this->assertSame(Role::Teacher, $teacher->fresh()->role);
    }

    public function test_phone_must_be_free_in_new_role(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $manager = $this->user(Role::Manager, $branch, [], ['phone' => '+998 90 555 6677']);
        $this->user(Role::Operator, $branch, [], ['phone' => '+998 90 555 6677']);

        $this->actingAs($sadmin)->put("/staff/{$manager->id}", $this->payload($manager, ['role' => 'operator']))->assertSessionHasErrors('phone');
        $this->assertSame(Role::Manager, $manager->fresh()->role);
    }

    public function test_saving_without_role_change_keeps_permissions(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $manager = $this->user(Role::Manager, $branch, ['students.view', 'cashbox.view'], ['phone' => '+998 90 100 2030']);

        $this->actingAs($sadmin)->put("/staff/{$manager->id}", $this->payload($manager, ['role' => 'manager', 'name' => 'Yangi Ism']))->assertRedirect();

        $manager->refresh();
        $this->assertSame('Yangi Ism', $manager->name);
        $this->assertEqualsCanonicalizing(['students.view', 'cashbox.view'], $manager->permissionKeys());
    }
}
