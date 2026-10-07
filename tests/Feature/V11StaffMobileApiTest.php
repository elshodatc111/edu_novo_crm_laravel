<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\LeadSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class V11StaffMobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_todo_returns_debtors_and_group_stats(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['payments.view', 'groups.view']);
        $this->student($branch, ['balance' => -5000]);
        $token = $admin->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->getJson('/api/v1/dashboard/todo');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data.todo'));
    }

    public function test_leads_crud_flow_respects_permissions(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['leads.view', 'leads.manage']);
        $token = $manager->createToken('mobile')->plainTextToken;
        $source = LeadSource::create(['branch_id' => $branch->id, 'name' => 'Instagram', 'is_active' => true]);

        $create = $this->api($token)->postJson('/api/v1/leads', [
            'name' => 'Aziz Aliyev', 'phone' => '+998 90 123 4567', 'lead_source_id' => $source->id,
        ]);
        $create->assertCreated();
        $leadId = $create->json('data.id');

        $this->api($token)->postJson("/api/v1/leads/{$leadId}/note", ['body' => "Qo'ng'iroq qilindi"])->assertOk();
        $this->api($token)->getJson("/api/v1/leads/{$leadId}")->assertOk()->assertJsonPath('data.notes.0.body', "Qo'ng'iroq qilindi");

        $this->api($token)->postJson("/api/v1/leads/{$leadId}/cancel", ['reason' => 'Javob bermadi'])->assertOk();
        $this->api($token)->postJson("/api/v1/leads/{$leadId}/reopen")->assertOk();
    }

    public function test_leads_view_only_user_cannot_manage(): void
    {
        $branch = $this->branch();
        $viewer = $this->user(Role::Operator, $branch, ['leads.view']);
        $token = $viewer->createToken('mobile')->plainTextToken;

        $this->api($token)->postJson('/api/v1/leads', ['name' => 'X', 'phone' => '+998 90 111 2233'])->assertForbidden();
    }

    public function test_cashbox_request_and_approve_flow(): void
    {
        $branch = $this->branch();
        $operator = $this->user(Role::Operator, $branch, ['cashbox.request', 'cashbox.withdraw']);
        $admin = $this->user(Role::Admin, $branch, ['cashbox.approve', 'cashbox.view', 'cashbox.history']);
        $this->fund($branch, Wallet::TillCash, 200000);

        $opToken = $operator->createToken('mobile')->plainTextToken;
        $adminToken = $admin->createToken('mobile')->plainTextToken;

        $store = $this->api($opToken)->postJson('/api/v1/cashbox/requests', [
            'kind' => 'withdrawal', 'method' => PayMethod::Cash->value, 'amount' => 50000, 'description' => 'Ofis xarajati',
        ]);
        $store->assertCreated();
        $requestId = $store->json('data.id');

        $view = $this->api($adminToken)->getJson('/api/v1/cashbox');
        $view->assertOk();
        $this->assertCount(1, $view->json('data.pending'));

        $this->api($adminToken)->postJson("/api/v1/cashbox/requests/{$requestId}/approve")->assertOk();
    }

    public function test_cashbox_request_forbidden_without_permission(): void
    {
        $branch = $this->branch();
        $teacher = $this->user(Role::Teacher, $branch);
        $token = $teacher->createToken('mobile')->plainTextToken;

        $this->api($token)->postJson('/api/v1/cashbox/requests', [
            'kind' => 'withdrawal', 'method' => PayMethod::Cash->value, 'amount' => 1000, 'description' => 'x',
        ])->assertForbidden();
    }

    public function test_finance_overview_and_deposit(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['finance.view', 'finance.deposit']);
        $token = $admin->createToken('mobile')->plainTextToken;

        $this->api($token)->getJson('/api/v1/finance/overview')->assertOk()->assertJsonStructure(['data' => ['treasury_cash', 'treasury_card', 'charity_percent']]);

        $this->api($token)->postJson('/api/v1/finance/deposit', [
            'method' => PayMethod::Cash->value, 'amount' => 30000, 'description' => "O'z mablag'im",
        ])->assertOk();

        $after = $this->api($token)->getJson('/api/v1/finance/overview');
        $this->assertSame(30000, $after->json('data.treasury_cash'));
    }

    public function test_finance_withdraw_endpoint_does_not_exist_on_mobile(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['finance.manage']);
        $token = $admin->createToken('mobile')->plainTextToken;

        // v11: pul CHIQARISH ataylab mobil API'da yo'q (faqat veb, ikki bosqichli tasdiqlash bilan)
        $this->api($token)->postJson('/api/v1/finance/withdraw', ['source' => 'cash', 'amount' => 1000, 'description' => 'x'])
            ->assertNotFound();
    }

    public function test_staff_list_and_create(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view', 'staff.manage']);
        $token = $admin->createToken('mobile')->plainTextToken;

        $this->api($token)->getJson('/api/v1/staff')->assertOk();

        $create = $this->api($token)->postJson('/api/v1/staff', [
            'name' => 'Yangi Menejer', 'username' => 'yangi.menejer', 'phone' => '+998 91 222 3344',
            'status' => 'active', 'password' => 'parol12345', 'password_confirmation' => 'parol12345', 'role' => 'manager',
        ]);
        $create->assertCreated();
        $this->assertDatabaseHas('users', ['username' => 'yangi.menejer', 'branch_id' => $branch->id]);
    }

    public function test_staff_directory_cross_branch_read_only(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $this->student($b, ['name' => 'Boshqa filial oquvchisi']);
        $operator = $this->user(Role::Operator, $a, ['staff.view_all_branches']);
        $token = $operator->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->getJson("/api/v1/staff-directory/{$b->id}");
        $response->assertOk();
        $this->assertSame('Boshqa filial oquvchisi', $response->json('data.students.0.name'));
    }

    public function test_staff_directory_forbidden_without_permission(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $operator = $this->user(Role::Operator, $a);
        $token = $operator->createToken('mobile')->plainTextToken;

        $this->api($token)->getJson("/api/v1/staff-directory/{$b->id}")->assertForbidden();
    }

    public function test_group_student_enroll_via_mobile(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['groups.view', 'groups.members', 'groups.create']);
        $this->actingAs($admin);
        $catalog = $this->catalog($branch);
        $group = \App\Services\GroupService::class;
        $group = app(\App\Services\GroupService::class)->create($this->groupPayload($catalog), $admin);

        $student = $this->student($branch);
        $token = $admin->createToken('mobile')->plainTextToken;

        $response = $this->api($token)->postJson("/api/v1/groups/{$group->id}/students", ['student_id' => $student->id]);
        $response->assertOk();

        $this->assertDatabaseHas('group_students', ['group_id' => $group->id, 'student_id' => $student->id, 'is_active' => true]);
    }

    public function test_sms_history_and_bulk_preview(): void
    {
        $branch = $this->branch();
        $branch->update(['sms_enabled' => true]);
        $admin = $this->user(Role::Admin, $branch, ['sms.view', 'sms.send']);
        $token = $admin->createToken('mobile')->plainTextToken;

        $this->api($token)->getJson('/api/v1/sms')->assertOk();

        $this->student($branch, ['phone' => '+998 90 555 1234']);
        $preview = $this->api($token)->postJson('/api/v1/sms/bulk-preview', ['audience' => 'all', 'message' => 'Salom {name}']);
        $preview->assertOk();
        $this->assertGreaterThanOrEqual(1, $preview->json('data.count'));
    }
}
