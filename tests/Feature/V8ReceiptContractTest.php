<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\GroupStudent;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** v8 B2: to'lov cheki (A5/80mm) va o'quv shartnomasi (A5) - avtomatik to'ldirilib chop etish uchun. */
class V8ReceiptContractTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---- Chek ----

    public function test_receipt_page_renders_payment_details_in_a5_by_default(): void
    {
        $branch = $this->branch('Toshkent');
        $admin = $this->user(Role::Admin, $branch, ['payments.view', 'payments.create']);
        $student = $this->student($branch, ['name' => 'Ali Valiyev', 'phone' => '+998 90 111 2233', 'balance' => 0]);

        $this->actingAs($admin);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 150000], null, 'Sinov', $admin);

        $resp = $this->get("/payments/{$payment->id}/receipt");

        $resp->assertOk()
            ->assertSee('Toshkent')
            ->assertSee('Ali Valiyev')
            ->assertSee('150 000', false)
            ->assertSee('@page { size: A5;', false)
            ->assertDontSee('80mm auto', false);
    }

    public function test_receipt_page_supports_80mm_format(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['payments.view', 'payments.create']);
        $student = $this->student($branch, ['balance' => 0]);
        $this->actingAs($admin);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 50000], null, null, $admin);

        $this->get("/payments/{$payment->id}/receipt?format=80mm")
            ->assertOk()
            ->assertSee('size: 80mm auto', false);
    }

    public function test_receipt_requires_payments_view_permission(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['payments.view', 'payments.create']);
        $student = $this->student($branch, ['balance' => 0]);
        $this->actingAs($admin);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 10000], null, null, $admin);

        $teacher = $this->user(Role::Teacher, $branch, ['attendance.view']);
        $this->actingAs($teacher)->get("/payments/{$payment->id}/receipt")->assertForbidden();
    }

    public function test_receipt_is_branch_scoped(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');
        $adminA = $this->user(Role::Admin, $branchA, ['payments.view', 'payments.create']);
        $adminB = $this->user(Role::Admin, $branchB, ['payments.view']);
        $student = $this->student($branchA, ['balance' => 0]);
        $this->actingAs($adminA);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 10000], null, null, $adminA);

        $this->actingAs($adminB)->get("/payments/{$payment->id}/receipt")->assertNotFound();
    }

    public function test_receipt_shows_reversed_badge(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['payments.view', 'payments.create', 'payments.reverse']);
        $student = $this->student($branch, ['balance' => 0]);
        $this->actingAs($admin);
        [$payment] = app(PaymentService::class)->receive($student, ['cash' => 20000], null, null, $admin);
        app(PaymentService::class)->reverse($payment, 'xato kiritildi', $admin);

        $this->get("/payments/{$payment->id}/receipt")->assertOk()->assertSee('STORNOLANGAN');
    }

    // ---- Shartnoma ----

    public function test_contract_page_renders_with_default_template(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch('Chilonzor');
        $admin = $this->user(Role::Admin, $branch, ['groups.create', 'groups.members']);
        $this->actingAs($admin);
        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $student = $this->student($branch, ['name' => "G'olib Qodirov", 'phone' => '+998 90 555 4433']);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);

        $resp = $this->get("/groups/{$group->id}/students/{$student->id}/contract");

        $resp->assertOk()
            ->assertSee("G'olib Qodirov")
            ->assertSee($group->name)
            ->assertSee($cat['course']->name)
            ->assertSee('Chilonzor')
            ->assertSee(\App\Support\Format::money($group->price));
    }

    public function test_contract_uses_branch_custom_template_when_set(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['groups.create', 'groups.members', 'settings.branch']);
        $this->actingAs($admin);
        $branch->update(['contract_template' => "MAXSUS SHARTNOMA: {oquvchi_fio} - {guruh_nomi} - {narx}"]);

        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $student = $this->student($branch, ['name' => 'Test Talaba']);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);

        $this->get("/groups/{$group->id}/students/{$student->id}/contract")
            ->assertOk()
            ->assertSee('MAXSUS SHARTNOMA: Test Talaba')
            ->assertDontSee("TA'LIM XIZMATLARI KO'RSATISH SHARTNOMASI");
    }

    public function test_contract_includes_stir_and_director_when_filled(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['groups.create', 'groups.members']);
        $branch->update(['stir' => '123456789', 'director_name' => 'Nodira Karimova', 'director_title' => 'Bosh direktor']);
        $this->actingAs($admin);

        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $student = $this->student($branch);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);

        $this->get("/groups/{$group->id}/students/{$student->id}/contract")
            ->assertOk()
            ->assertSee('123456789')
            ->assertSee('Nodira Karimova')
            ->assertSee('Bosh direktor');
    }

    public function test_contract_number_is_stable_across_reprints(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['groups.create', 'groups.members']);
        $this->actingAs($admin);
        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $student = $this->student($branch);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);

        $enrollment = GroupStudent::where('group_id', $group->id)->where('student_id', $student->id)->firstOrFail();
        $expected = strtoupper($branch->code).'-'.str_pad((string) $enrollment->id, 5, '0', STR_PAD_LEFT);

        $this->get("/groups/{$group->id}/students/{$student->id}/contract")->assertOk()->assertSee($expected);
        $this->get("/groups/{$group->id}/students/{$student->id}/contract")->assertOk()->assertSee($expected);
    }

    public function test_contract_requires_groups_members_permission(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['groups.create', 'groups.members']);
        $this->actingAs($admin);
        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $student = $this->student($branch);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);

        $viewer = $this->user(Role::Manager, $branch, ['groups.view']);
        $this->actingAs($viewer)->get("/groups/{$group->id}/students/{$student->id}/contract")->assertForbidden();
    }

    public function test_contract_is_branch_scoped(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');
        $adminA = $this->user(Role::Admin, $branchA, ['groups.create', 'groups.members']);
        $adminB = $this->user(Role::Admin, $branchB, ['groups.members']);
        $this->actingAs($adminA);
        $cat = $this->catalog($branchA);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $adminA);
        $student = $this->student($branchA);
        app(EnrollmentService::class)->enroll($group, $student, null, $adminA);

        $this->actingAs($adminB)->get("/groups/{$group->id}/students/{$student->id}/contract")->assertNotFound();
    }

    // ---- Sozlamalar ----

    public function test_settings_page_persists_stir_director_and_template(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['settings.branch']);

        $this->actingAs($admin)->put('/settings/contract', [
            'stir' => '987654321',
            'director_name' => 'Aziz Rustamov',
            'director_title' => 'Direktor',
            'contract_template' => 'Maxsus matn {oquvchi_fio}',
        ])->assertRedirect();

        $fresh = $branch->fresh();
        $this->assertSame('987654321', $fresh->stir);
        $this->assertSame('Aziz Rustamov', $fresh->director_name);
        $this->assertSame('Maxsus matn {oquvchi_fio}', $fresh->contract_template);
    }

    public function test_settings_reset_restores_default_template(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['settings.branch']);
        $branch->update(['contract_template' => 'Eskirgan maxsus matn']);

        $this->actingAs($admin)->put('/settings/contract/reset')->assertRedirect();

        $this->assertNull($branch->fresh()->contract_template);
    }

    public function test_settings_requires_settings_branch_permission(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['groups.view']);

        $this->actingAs($manager)->get('/settings/contract')->assertForbidden();
        $this->actingAs($manager)->put('/settings/contract', ['contract_template' => 'x'])->assertForbidden();
    }
}
