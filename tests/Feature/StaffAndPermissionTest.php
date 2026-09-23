<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function staffPayload(array $over = []): array
    {
        return [
            'name' => 'Yangi Hodim', 'username' => 'yangi.hodim', 'phone' => '+998 90 555 6677', 'password' => 'parol12345',
            'password_confirmation' => 'parol12345', 'status' => 'active', ...$over,
        ];
    }

    public function test_sadmin_creates_admin_in_chosen_branch_with_default_permissions(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $branch = $this->branch();

        $this->actingAs($sadmin)->post('/staff', $this->staffPayload(['role' => 'admin', 'branch_id' => $branch->id]))
            ->assertRedirect(route('staff.index'));

        $admin = User::where('username', 'yangi.hodim')->firstOrFail();
        $this->assertSame($branch->id, $admin->branch_id);
        $this->assertTrue($admin->hasPermission('staff.manage'));
        $this->assertTrue($admin->hasPermission('permissions.assign'));
    }

    public function test_admin_can_create_only_managers_in_own_branch(): void
    {
        $branch = $this->branch();
        $other = $this->branch('Boshqa');
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage', 'students.view', 'students.create', 'attendance.take']);

        $this->actingAs($admin)->post('/staff', $this->staffPayload(['role' => 'admin']))->assertSessionHasErrors('role');
        $this->actingAs($admin)->post('/staff', $this->staffPayload(['role' => 'manager', 'branch_id' => $other->id]))->assertSessionHasErrors('branch_id');

        $this->actingAs($admin)->post('/staff', $this->staffPayload(['role' => 'manager']))->assertRedirect(route('staff.index'));

        $manager = User::where('username', 'yangi.hodim')->firstOrFail();
        $this->assertSame($branch->id, $manager->branch_id);

        // Menejer shablonidagi ruxsatlardan faqat adminda borlari beriladi
        $this->assertEqualsCanonicalizing(['students.view', 'students.create', 'attendance.take'], $manager->permissionKeys());
    }

    public function test_manager_cannot_open_staff_pages(): void
    {
        $manager = $this->user(Role::Manager, permissions: ['students.view']);

        $this->actingAs($manager)->get('/staff')->assertForbidden();
        $this->actingAs($manager)->get('/staff/create')->assertForbidden();
    }

    public function test_admin_only_sees_staff_of_own_branch(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $admin = $this->user(Role::Admin, $a, ['staff.view']);
        $mine = $this->user(Role::Manager, $a);
        $foreign = $this->user(Role::Manager, $b);

        $this->actingAs($admin)->get('/staff')->assertOk()->assertSee($mine->name)->assertDontSee($foreign->name);
        $this->actingAs($admin)->get("/staff/{$foreign->id}/edit")->assertNotFound();
    }

    public function test_admin_cannot_edit_other_admins(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage', 'permissions.assign']);
        $peer = $this->user(Role::Admin, $branch);

        $this->actingAs($admin)->get("/staff/{$peer->id}/edit")->assertForbidden();
        $this->actingAs($admin)->get("/staff/{$peer->id}/permissions")->assertForbidden();
    }

    public function test_admin_can_grant_only_own_permissions(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['permissions.assign', 'students.view', 'students.create', 'payments.view']);
        $manager = $this->user(Role::Manager, $branch, ['leads.view']);

        $this->actingAs($admin)->put("/staff/{$manager->id}/permissions", [
            'permissions' => ['students.view', 'payments.view', 'payments.refund', 'finance.view'],
        ])->assertRedirect();

        $keys = $manager->fresh()->permissionKeys();
        $this->assertContains('students.view', $keys);
        $this->assertContains('payments.view', $keys);
        $this->assertNotContains('payments.refund', $keys);   // adminda yo'q
        $this->assertNotContains('finance.view', $keys);       // adminda yo'q va menejerga berilmaydi
        $this->assertContains('leads.view', $keys);            // admin vakolatidan tashqari, o'zgarmaydi
    }

    public function test_admin_cannot_remove_permissions_outside_own_authority(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['permissions.assign', 'students.view']);
        $manager = $this->user(Role::Manager, $branch, ['students.view', 'leads.manage']);

        $this->actingAs($admin)->put("/staff/{$manager->id}/permissions", ['permissions' => []])->assertRedirect();

        $this->assertSame(['leads.manage'], $manager->fresh()->permissionKeys());
    }

    public function test_admin_cannot_give_admin_only_permissions_to_manager(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['permissions.assign', 'cashbox.approve']);
        $manager = $this->user(Role::Manager, $branch);

        $this->actingAs($admin)->put("/staff/{$manager->id}/permissions", ['permissions' => ['cashbox.approve']]);

        $this->assertNotContains('cashbox.approve', $manager->fresh()->permissionKeys());
    }

    public function test_admin_without_assign_permission_is_forbidden(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view']);
        $manager = $this->user(Role::Manager, $branch);

        $this->actingAs($admin)->get("/staff/{$manager->id}/permissions")->assertForbidden();
    }

    public function test_only_sadmin_can_set_admin_permissions(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $admin = $this->user(Role::Admin, $branch);

        $this->actingAs($sadmin)->put("/staff/{$admin->id}/permissions", ['permissions' => ['finance.view', 'ai.chat', 'cashbox.approve']])->assertRedirect();

        $this->assertEqualsCanonicalizing(['finance.view', 'ai.chat', 'cashbox.approve'], $admin->fresh()->permissionKeys());
        $this->assertTrue(AuditLog::where('action', 'permissions.updated')->exists());
    }

    public function test_blocking_staff_revokes_api_tokens(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage']);
        $manager = $this->user(Role::Manager, $branch);
        $manager->createToken('t');

        $this->actingAs($admin)->put("/staff/{$manager->id}", $this->staffPayload(['username' => $manager->username, 'status' => 'blocked', 'password' => '', 'password_confirmation' => '']))
            ->assertRedirect();

        $this->assertSame(0, $manager->tokens()->count());
    }

    public function test_pages_render_without_lazy_loading_errors(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage', 'permissions.assign', 'audit.view']);
        $this->user(Role::Manager, $branch);
        $this->user(Role::Manager, $branch);

        foreach (['/', '/branches', '/branches/create', '/staff', '/staff/create', '/audit-log', '/profile'] as $url) {
            $this->actingAs($sadmin)->get($url)->assertOk();
        }
        foreach (['/', '/staff', '/staff/create', '/audit-log', '/profile'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }

        $manager = User::where('role', 'manager')->first();
        $this->actingAs($sadmin)->get("/staff/{$manager->id}/permissions")->assertOk()->assertSee('Ruxsatlar');
        $this->actingAs($admin)->get("/staff/{$manager->id}/edit")->assertOk();
    }
}
