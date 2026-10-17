<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Payment;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\ReportService;
use App\Services\StatisticsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: ketma-ket raqamlash, tablar tartibi, qator bosish, kim ro'yxatga olgani va to'lagan yangi o'quvchilar. */
class V13ListsAndRegistrarTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_created_by_is_saved_and_shown_on_card(): void
    {
        $b = $this->branch();
        $op = $this->user(Role::Operator, $b, ['students.view', 'students.create']);

        $this->actingAs($op)->post('/students', ['name' => 'Ali Valiyev', 'phone' => '+998 90 123 4567'])->assertSessionHasNoErrors();
        $s = User::where('role', Role::Student)->firstOrFail();

        $this->assertSame($op->id, $s->created_by);
        $this->assertSame('manual', $s->registered_via);
        $this->get("/students/{$s->id}")->assertOk()->assertSee("Ro'yxatga olgan")->assertSee($op->name);
    }

    public function test_students_and_groups_lists_have_numbers_and_clickable_rows(): void
    {
        $b = $this->branch();
        $admin = $this->user(Role::Admin, $b, ['students.view', 'groups.view']);
        $s1 = $this->student($b);
        $s2 = $this->student($b);

        $this->actingAs($admin)->get('/students')->assertOk()
            ->assertSee('№', false)
            ->assertSee('data-href="'.route('students.show', $s1).'"', false)
            ->assertSee('data-href="'.route('students.show', $s2).'"', false);

        Carbon::setTestNow('2026-09-14 09:00:00');
        $c = $this->catalog($b);
        $group = app(GroupService::class)->create($this->groupPayload($c), $admin);
        $this->get('/groups')->assertOk()->assertSee('№', false)->assertSee('data-href="'.route('groups.show', $group).'"', false);
    }

    public function test_group_page_shows_students_tab_before_attendance(): void
    {
        Carbon::setTestNow('2026-09-14 09:00:00');
        $b = $this->branch();
        $admin = $this->user(Role::Admin, $b, ['groups.view', 'groups.create', 'groups.members', 'students.view']);
        $this->actingAs($admin);
        $c = $this->catalog($b);
        $group = app(GroupService::class)->create($this->groupPayload($c), $admin);

        $this->get("/groups/{$group->id}")->assertOk()->assertSeeInOrder([">O'quvchilar<", '>Davomad<'], false);
    }

    public function test_payroll_list_rows_are_clickable_without_open_button(): void
    {
        $b = $this->branch();
        $admin = $this->user(Role::Admin, $b, ['staff.view']);
        $op = $this->user(Role::Operator, $b);

        $this->actingAs($admin)->get('/payroll?tab=staff')->assertOk()
            ->assertSee('data-href="'.route('payroll.show', $op).'"', false)
            ->assertDontSee('>Ochish<', false);
    }

    public function test_staff_activity_counts_paid_new_students_and_their_sum(): void
    {
        $b = $this->branch();
        $op = $this->user(Role::Operator, $b, ['students.view', 'students.create']);
        $this->actingAs($op);
        $svc = app(\App\Services\StudentService::class);

        [$paid] = $svc->create(['name' => 'Birinchi', 'phone' => '+998 90 111 2201'], $op, false);
        [$unpaid] = $svc->create(['name' => 'Ikkinchi', 'phone' => '+998 90 111 2202'], $op, false);
        foreach ([60000, 40000] as $amount) {
            Payment::create(['branch_id' => $b->id, 'student_id' => $paid->id, 'type' => 'payment', 'method' => 'cash', 'amount' => $amount, 'created_by' => $op->id]);
        }
        Payment::create(['branch_id' => $b->id, 'student_id' => $paid->id, 'type' => 'payment', 'method' => 'cash', 'amount' => 9999, 'created_by' => $op->id, 'reversed_at' => now()]);

        $t = app(StatisticsService::class)->staffActivity($op, 'day')['totals'];
        $this->assertSame(2, $t['students']);
        $this->assertSame(1, $t['paid_students']);
        $this->assertSame(100000, $t['paid_sum']);
    }

    public function test_new_students_report_has_registrar_column(): void
    {
        $b = $this->branch();
        $op = $this->user(Role::Operator, $b, ['students.view', 'students.create']);
        $this->actingAs($op);
        app(\App\Services\StudentService::class)->create(['name' => 'Hisobot', 'phone' => '+998 90 111 2203'], $op, false);

        $r = app(ReportService::class)->build('students', \Carbon\CarbonImmutable::today()->subDay(), \Carbon\CarbonImmutable::today()->addDay());
        $this->assertContains("Ro'yxatga olgan", $r['columns']);
        $this->assertSame($op->name, $r['rows'][0][array_search("Ro'yxatga olgan", $r['columns'], true)]);
    }
}
