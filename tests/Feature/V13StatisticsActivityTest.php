<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lead;
use App\Models\Payment;
use App\Services\StatisticsService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: tushum dinamikasi (kun/hafta/oy) va hodim faoliyati statistikasi. */
class V13StatisticsActivityTest extends TestCase
{
    use RefreshDatabase;

    private function pay($branch, $student, $by, int $amount, string $method = 'cash', ?string $at = null, array $extra = []): Payment
    {
        $p = Payment::create(['branch_id' => $branch->id, 'student_id' => $student->id, 'type' => 'payment', 'method' => $method, 'amount' => $amount, 'created_by' => $by->id] + $extra);
        if ($at) {
            $p->forceFill(['created_at' => $at])->save();
        }

        return $p;
    }

    public function test_income_dynamics_sums_days_and_excludes_reversed(): void
    {
        $b = $this->branch('A');
        $op = $this->user(Role::Operator, $b);
        $st = $this->student($b);
        $this->actingAs($op);

        $this->pay($b, $st, $op, 100000, 'cash');
        $this->pay($b, $st, $op, 50000, 'card');
        $this->pay($b, $st, $op, 70000, 'cash', null, ['reversed_at' => now()]);
        $this->pay($b, $st, $op, 20000, 'cash', today()->subDays(2)->toDateTimeString());

        $d = app(StatisticsService::class)->incomeDynamics('day');
        $this->assertCount(30, $d['rows']);
        $last = end($d['rows']);
        $this->assertSame(100000, $last['cash']);
        $this->assertSame(50000, $last['card']);
        $this->assertSame(2, $last['count']);
        $this->assertSame(170000, $d['totals']['net']);

        $m = app(StatisticsService::class)->incomeDynamics('month');
        $this->assertCount(12, $m['rows']);
        $w = app(StatisticsService::class)->incomeDynamics('week');
        $this->assertCount(12, $w['rows']);
        $this->assertSame(array_sum(array_column($w['rows'], 'net')), $w['totals']['net']);
    }

    public function test_statistics_page_shows_dynamics_only_with_payment_permission(): void
    {
        $b = $this->branch('A');
        $with = $this->user(Role::Manager, $b, ['statistics.view', 'payments.view']);
        $without = $this->user(Role::Manager, $b, ['statistics.view']);

        $this->actingAs($with)->get('/statistics?dyn=week')->assertOk()->assertSee('Tushum dinamikasi (aniq)')->assertSee('Shu hafta');
        $this->actingAs($without)->get('/statistics')->assertOk()->assertDontSee('Tushum dinamikasi (aniq)');
        $this->actingAs($with)->get('/statistics?dyn=zzz')->assertOk();
    }

    public function test_staff_activity_counts_own_payments_and_leads_in_own_branch(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $op = $this->user(Role::Operator, $a);
        $other = $this->user(Role::Operator, $a);
        $stA = $this->student($a);
        $stB = $this->student($b);

        $this->pay($a, $stA, $op, 100000, 'cash');
        $this->pay($a, $stA, $op, 40000, 'card');
        $this->pay($a, $stA, $op, 999000, 'cash', null, ['reversed_at' => now()]); // storno: hisobga olinmaydi
        $this->pay($a, $stA, $other, 5000, 'cash');                                  // boshqa hodim
        Lead::create(['branch_id' => $a->id, 'name' => 'Lead 1', 'phone' => '+998 90 111 2233', 'status' => 'new', 'created_by' => $op->id]);
        Lead::create(['branch_id' => $a->id, 'name' => 'Lead 2', 'phone' => '+998 90 111 2244', 'status' => Lead::CONVERTED, 'created_by' => $op->id]);

        $this->actingAs($op);
        $r = app(StatisticsService::class)->staffActivity($op, 'day');

        $this->assertCount(30, $r['rows']);
        $this->assertSame(2, $r['totals']['payments']);
        $this->assertSame(140000, $r['totals']['total']);
        $this->assertSame(2, $r['totals']['leads']);
        $this->assertSame(1, $r['totals']['converted']);

        $m = app(StatisticsService::class)->staffActivity($op, 'month');
        $this->assertCount(12, $m['rows']);
        $this->assertSame(140000, $m['totals']['total']);

        // boshqa filial konteksti: bu filial yozuvlari ko'rinmaydi
        $this->actingAs($this->user(Role::SAdmin));
        BranchContext::select($b->id);
        $this->assertSame(0, app(StatisticsService::class)->staffActivity($op, 'day')['totals']['payments']);
        $this->assertNotNull($stB);
    }

    public function test_payroll_card_shows_activity_for_staff_but_not_teacher(): void
    {
        $b = $this->branch('A');
        $admin = $this->user(Role::Admin, $b, ['staff.view', 'teachers.view']);
        $op = $this->user(Role::Operator, $b);
        $teacher = $this->user(Role::Teacher, $b);

        $this->actingAs($admin)->get(route('payroll.show', $op))->assertOk()->assertSee('Faoliyat statistikasi');
        $this->actingAs($admin)->get(route('payroll.show', ['user' => $op, 'act' => 'month']))->assertOk()->assertSee('Oylik (12 oy)');
        $this->actingAs($admin)->get(route('payroll.show', $teacher))->assertOk()->assertDontSee('Faoliyat statistikasi');
    }
}
