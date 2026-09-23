<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $cashier;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
        $this->branch = $this->branch();
        $this->cashier = $this->user(Role::Manager, $this->branch, ['students.view', 'payments.create', 'statistics.view']);
        $this->token = $this->cashier->createToken('t')->plainTextToken;
    }

    public function test_students_list_search_show_and_scope(): void
    {
        $a = $this->student($this->branch, ['name' => 'ALI VALIYEV', 'phone' => '+998 90 123 4567', 'balance' => -100000]);
        $this->student($this->branch, ['name' => 'SARA', 'phone' => '+998 90 222 3344', 'balance' => 5000]);
        $this->student($this->branch('Boshqa'), ['name' => 'BEGONA']);

        $this->api($this->token)->getJson('/api/v1/students')->assertOk()->assertJsonPath('meta.total', 2);
        $this->api($this->token)->getJson('/api/v1/students?q=ali')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.debt', 100000);
        $this->api($this->token)->getJson('/api/v1/students?debtors=1')->assertOk()->assertJsonCount(1, 'data');
        $this->api($this->token)->getJson("/api/v1/students/{$a->id}")->assertOk()->assertJsonPath('data.name', 'ALI VALIYEV')->assertJsonStructure(['data' => ['groups']]);

        $foreign = \App\Models\User::where('name', 'BEGONA')->first();
        $this->api($this->token)->getJson("/api/v1/students/{$foreign->id}")->assertNotFound();

        $teacher = $this->user(Role::Teacher, $this->branch, ['attendance.view'])->createToken('x')->plainTextToken;
        $this->api($teacher)->getJson('/api/v1/students')->assertForbidden();
    }

    public function test_cashier_receives_payment_with_early_discount(): void
    {
        $admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members']);
        $this->actingAs($admin);
        $cat = $this->catalog($this->branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);

        $this->api($this->token)->postJson("/api/v1/students/{$student->id}/payments", ['cash' => 300000, 'card' => 150000, 'group_id' => $group->id])
            ->assertOk()->assertJsonPath('data.discount', 50000)->assertJsonPath('data.balance', -500000 + 450000 + 50000);

        $this->api($this->token)->postJson("/api/v1/students/{$student->id}/payments", ['cash' => 0, 'card' => 0])->assertStatus(422);

        $noPay = $this->user(Role::Manager, $this->branch, ['students.view'])->createToken('n')->plainTextToken;
        $this->api($noPay)->postJson("/api/v1/students/{$student->id}/payments", ['cash' => 1000])->assertForbidden();
    }

    public function test_statistics_overview_hides_forbidden_money(): void
    {
        $this->api($this->token)->getJson('/api/v1/statistics/overview?from=2026-09-01&to=2026-09-30')->assertOk()
            ->assertJsonPath('data.from', '2026-09-01')->assertJsonMissingPath('data.income')->assertJsonMissingPath('data.profit')->assertJsonPath('data.debtors', 0);

        $admin = $this->user(Role::Admin, $this->branch, ['statistics.view', 'payments.view', 'finance.view'])->createToken('a')->plainTextToken;
        $this->api($admin)->getJson('/api/v1/statistics/overview')->assertOk()->assertJsonPath('data.income', 0)->assertJsonStructure(['data' => ['profit', 'net_income']]);

        $none = $this->user(Role::Manager, $this->branch, ['students.view'])->createToken('z')->plainTextToken;
        $this->api($none)->getJson('/api/v1/statistics/overview')->assertForbidden();
    }

    public function test_teacher_payroll_endpoint(): void
    {
        $admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'teachers.pay']);
        $this->actingAs($admin);
        $cat = $this->catalog($this->branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat, ['teacher_rate' => 100000]), $admin);
        $this->fund($this->branch, \App\Enums\Wallet::TreasuryCash, 500000);
        app(PayrollService::class)->payTeacher($cat['teacher'], $group, PayMethod::Cash, 40000, 'Avans', $admin);

        $token = $cat['teacher']->createToken('t')->plainTextToken;
        $this->api($token)->getJson('/api/v1/me/payroll')->assertOk()
            ->assertJsonPath('data.groups.0.group', 'A1 GURUH')->assertJsonPath('data.groups.0.paid', 40000)->assertJsonPath('data.payouts.0.amount', 40000);

        $this->api($this->token)->getJson('/api/v1/me/payroll')->assertForbidden();
    }
}
