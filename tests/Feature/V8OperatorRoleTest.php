<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v8: yangi "Operator" lavozimi — menejer bilan bir xil ruxsat mexanizmi, torroq boshlang'ich shablon. */
class V8OperatorRoleTest extends TestCase
{
    use RefreshDatabase;

    private function staffPayload(array $over = []): array
    {
        return [
            'name' => 'Yangi Operator', 'username' => 'yangi.operator', 'phone' => '+998 90 111 2233', 'password' => 'parol12345',
            'password_confirmation' => 'parol12345', 'status' => 'active', ...$over,
        ];
    }

    public function test_sadmin_creates_operator_with_default_permissions(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $branch = $this->branch();

        $this->actingAs($sadmin)->post('/staff', $this->staffPayload(['role' => 'operator', 'branch_id' => $branch->id]))
            ->assertRedirect(route('staff.index'));

        $operator = User::where('username', 'yangi.operator')->firstOrFail();
        $this->assertSame(Role::Operator, $operator->role);
        $this->assertSame($branch->id, $operator->branch_id);
        $this->assertEqualsCanonicalizing([
            'students.view', 'students.create', 'students.update', 'students.notes',
            'groups.view', 'groups.members',
            'attendance.view', 'attendance.take',
            'payments.view', 'payments.create',
            'leads.view', 'leads.manage',
            'staff.view_all_branches', // v9: boshqa filialdagi mas'ul xodimni topa olishi uchun
        ], $operator->permissionKeys());
    }

    public function test_operator_defaults_exclude_money_outflow_discount_and_courses(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->post('/staff', $this->staffPayload(['role' => 'operator', 'branch_id' => $this->branch()->id]));

        $operator = User::where('username', 'yangi.operator')->firstOrFail();
        $keys = $operator->permissionKeys();

        foreach (['cashbox.view', 'cashbox.request', 'cashbox.approve', 'cashbox.history', 'payments.discount', 'payments.refund', 'payments.reverse', 'finance.view', 'finance.manage', 'courses.view'] as $forbidden) {
            $this->assertNotContains($forbidden, $keys, "Operator standart holatda '{$forbidden}' ruxsatiga ega bo'lmasligi kerak.");
        }
    }

    public function test_admin_can_create_operator_in_own_branch_with_intersected_permissions(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage', 'students.view', 'students.create', 'attendance.take']);

        $this->actingAs($admin)->post('/staff', $this->staffPayload(['role' => 'operator']))
            ->assertRedirect(route('staff.index'));

        $operator = User::where('username', 'yangi.operator')->firstOrFail();
        $this->assertSame($branch->id, $operator->branch_id);
        // Operator shablonidagi ruxsatlardan faqat adminda borlari beriladi
        $this->assertEqualsCanonicalizing(['students.view', 'students.create', 'attendance.take'], $operator->permissionKeys());
    }

    public function test_admin_can_grant_full_manager_scope_permissions_to_operator(): void
    {
        // "Operator ham xuddi shu mexanizmga qo'shiladi" — admin o'zida bor bo'lsa,
        // standart shablondan tashqari (masalan kassa/chegirma) ruxsatlarni ham bera oladi.
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['permissions.assign', 'payments.discount', 'cashbox.view', 'cashbox.request', 'courses.view']);
        $operator = $this->user(Role::Operator, $branch);

        $this->actingAs($admin)->put("/staff/{$operator->id}/permissions", [
            'permissions' => ['payments.discount', 'cashbox.view', 'cashbox.request', 'courses.view'],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(['payments.discount', 'cashbox.view', 'cashbox.request', 'courses.view'], $operator->fresh()->permissionKeys());
    }

    public function test_admin_without_staff_manage_cannot_create_operator(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view']);

        $this->actingAs($admin)->post('/staff', $this->staffPayload(['role' => 'operator']))
            ->assertSessionHasErrors('role');
    }

    public function test_operator_appears_in_staff_listing_and_role_filter_for_admin(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage']);
        $operator = $this->user(Role::Operator, $branch);

        $this->actingAs($admin)->get('/staff')->assertOk()->assertSee($operator->name)->assertSee('Operator');
        $this->actingAs($admin)->get('/staff?role=operator')->assertOk()->assertSee($operator->name);
        $this->actingAs($admin)->get('/staff/create')->assertOk()->assertSee('Operator');
    }

    public function test_admin_only_sees_operators_of_own_branch(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $admin = $this->user(Role::Admin, $a, ['staff.view']);
        $mine = $this->user(Role::Operator, $a);
        $foreign = $this->user(Role::Operator, $b);

        $this->actingAs($admin)->get('/staff')->assertOk()->assertSee($mine->name)->assertDontSee($foreign->name);
        $this->actingAs($admin)->get("/staff/{$foreign->id}/edit")->assertNotFound();
    }

    public function test_operator_included_in_staff_payroll_tab(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view']);
        $operator = $this->user(Role::Operator, $branch);

        $response = $this->actingAs($admin)->get('/payroll?tab=staff')->assertOk();
        $response->assertSee($operator->name);

        $this->actingAs($admin)->get("/payroll/{$operator->id}")->assertOk();
    }

    public function test_operator_cannot_use_admin_only_permissions_even_if_requested(): void
    {
        $branch = $this->branch();
        $sadmin = $this->user(Role::SAdmin);
        $operator = $this->user(Role::Operator, $branch);

        // sAdmin ham operatorga admin-only ruxsatni (masalan finance.view) bera olmaydi —
        // chunki bu ruxsat hech qachon operator uchun "grantable" emas.
        $this->actingAs($sadmin)->put("/staff/{$operator->id}/permissions", [
            'permissions' => ['finance.view', 'students.view'],
        ])->assertRedirect();

        $keys = $operator->fresh()->permissionKeys();
        $this->assertContains('students.view', $keys);
        $this->assertNotContains('finance.view', $keys);
    }

    public function test_operator_role_badge_and_dashboard_count_render(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view']);
        $this->user(Role::Operator, $branch);

        $this->assertSame('badge-purple', Role::Operator->badgeClass());
        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Operatorlar');
    }
}
