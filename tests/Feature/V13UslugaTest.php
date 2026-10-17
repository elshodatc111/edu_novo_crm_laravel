<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupStudent;
use App\Services\GroupService;
use App\Services\PayrollService;
use App\Services\UslugaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: o'qituvchi «Usluga» (staj bo'yicha qat'iy summa): pog'onalar, sana cheklovi, qotirish, ruxsatlar. */
class V13UslugaTest extends TestCase
{
    use RefreshDatabase;

    private const RATES = [
        1 => ['student' => 20000, 'bonus' => 10000],
        2 => ['student' => 30000, 'bonus' => 15000],
        3 => ['student' => 40000, 'bonus' => 20000],
        4 => ['student' => 50000, 'bonus' => 25000],
    ];

    private function enroll(Group $g, $student): void
    {
        GroupStudent::create(['branch_id' => $g->branch_id, 'group_id' => $g->id, 'student_id' => $student->id, 'is_active' => true]);
    }

    /** @return array{0:\App\Models\Branch,1:array,2:\App\Models\User,3:Group,4:Group} */
    private function scenario(): array
    {
        Carbon::setTestNow('2026-10-20 09:00:00');
        $b = $this->branch();
        $b->update(['usluga_from' => '2026-10-01', 'usluga_rates' => self::RATES]);
        $c = $this->catalog($b);
        $c['teacher']->update(['hired_at' => '2026-08-01']);
        $admin = $this->user(Role::Admin, $b, ['groups.view', 'groups.create', 'teachers.view', 'settings.branch']);
        $this->actingAs($admin);

        $old = app(GroupService::class)->create($this->groupPayload($c), $admin);                       // 21.09 - sanadan oldin
        $new = app(GroupService::class)->create($this->groupPayload($c, ['name' => 'Yangi', 'starts_on' => '2026-10-05']), $admin);
        $next = app(GroupService::class)->create($this->groupPayload($c, ['name' => 'Keyingi', 'starts_on' => '2026-10-06', 'schedule' => 'even']), $admin);

        $s1 = $this->student($b);
        $s2 = $this->student($b);
        $this->enroll($new, $s1);
        $this->enroll($new, $s2);
        $this->enroll($next, $s1);                     // s1 keyingi guruhda ham faol -> «Yangi» guruhda bonusli
        $this->enroll($old, $s2);

        return [$b, $c, $admin, $new, $old];
    }

    public function test_tiers_by_tenure_months(): void
    {
        $b = $this->branch();
        $t = $this->user(Role::Teacher, $b, [], ['hired_at' => '2026-01-01']);
        $svc = app(UslugaService::class);

        foreach (['2026-06-30' => 1, '2026-07-01' => 2, '2026-12-31' => 2, '2027-01-01' => 3, '2027-06-30' => 3, '2027-07-01' => 4, '2030-01-01' => 4] as $date => $tier) {
            $this->assertSame($tier, $svc->tierForMonths($svc->tenureMonths($t->fresh(), Carbon::parse($date))), $date);
        }
        $this->assertNull($svc->tenureMonths($this->user(Role::Teacher, $b), now()));
    }

    public function test_usluga_applies_only_to_groups_started_after_the_date_and_keeps_existing_accrual(): void
    {
        [$b, $c, $admin, $new, $old] = $this->scenario();
        $new->update(['teacher_rate' => 1000]);
        $old->update(['teacher_rate' => 1000]);

        $rows = collect(app(PayrollService::class)->teacherAccruals($c['teacher']))->keyBy(fn ($r) => $r['group']->id);

        // Eski guruh: o'zgarmagan
        $this->assertSame(UslugaService::STATE_OFF, $rows[$old->id]['usluga']['state']);
        $this->assertSame(0, $rows[$old->id]['usluga_students'] + $rows[$old->id]['usluga_bonus']);
        $this->assertSame($rows[$old->id]['accrued'], $rows[$old->id]['accrued_total']);

        // Yangi guruh: 2 o'quvchi x 20 000 + 1 bonusli x 10 000; mavjud hisob (2 x 1000) o'zgarmagan
        $r = $rows[$new->id];
        $this->assertSame(2000, $r['accrued']);
        $this->assertSame(UslugaService::STATE_OK, $r['usluga']['state']);
        $this->assertSame(1, $r['usluga']['tier']);
        $this->assertSame(40000, $r['usluga_students']);
        $this->assertSame(1, $r['bonus']);
        $this->assertSame(10000, $r['usluga_bonus']);
        $this->assertSame(52000, $r['accrued_total']);
    }

    public function test_usluga_is_prorated_by_attendance(): void
    {
        [$b, $c, $admin, $new] = $this->scenario();

        \App\Models\AttendanceSession::create(['branch_id' => $b->id, 'group_id' => $new->id, 'date' => '2026-10-05', 'taken_by' => $admin->id]);

        $r = collect(app(PayrollService::class)->teacherAccruals($c['teacher']))->firstWhere(fn ($x) => $x['group']->id === $new->id);
        // jami 50 000, 4 ta darsdan 1 tasi o'tgan
        $this->assertSame(12500, $r['accrued_by_attendance']);
    }

    public function test_amounts_are_frozen_after_group_started(): void
    {
        [$b, $c, $admin, $new] = $this->scenario();

        app(PayrollService::class)->teacherAccruals($c['teacher']);          // qotiradi
        $this->assertSame(1, $new->fresh()->usluga_tier);
        $this->assertSame(20000, $new->fresh()->usluga_student_amount);

        $b->update(['usluga_rates' => [1 => ['student' => 99999, 'bonus' => 99999]] + self::RATES]);
        $c['teacher']->update(['hired_at' => '2020-01-01']);

        $r = collect(app(PayrollService::class)->teacherAccruals($c['teacher']))->firstWhere(fn ($x) => $x['group']->id === $new->id);
        $this->assertSame(40000, $r['usluga_students']);
        $this->assertSame(10000, $r['usluga_bonus']);
    }

    public function test_missing_hire_date_gives_zero_and_a_warning(): void
    {
        [$b, $c, $admin, $new] = $this->scenario();
        $c['teacher']->update(['hired_at' => null]);

        $r = collect(app(PayrollService::class)->teacherAccruals($c['teacher']->fresh()))->firstWhere(fn ($x) => $x['group']->id === $new->id);
        $this->assertSame(UslugaService::STATE_NO_HIRE_DATE, $r['usluga']['state']);
        $this->assertSame(0, $r['usluga_students']);
        $this->assertNull($new->fresh()->usluga_tier);                          // qotirilmaydi

        $this->actingAs($admin)->get("/payroll/{$c['teacher']->id}")->assertOk()->assertSee('kiritilmagan');
    }

    public function test_payroll_page_shows_usluga_columns(): void
    {
        [$b, $c, $admin, $new] = $this->scenario();

        $this->actingAs($admin)->get("/payroll/{$c['teacher']->id}")->assertOk()
            ->assertSee("Usluga (o'quvchi)", false)->assertSee('Usluga (bonus)', false)->assertSee('01.08.2026')->assertSee('0–6 oy');
    }

    public function test_only_super_admin_changes_settings_admin_can_view(): void
    {
        $b = $this->branch();
        $admin = $this->user(Role::Admin, $b, ['settings.branch']);
        $sadmin = $this->user(Role::SAdmin);
        $payload = ['usluga_from' => '2026-10-01', 'usluga_student_1' => '20 000', 'usluga_bonus_1' => '10000', 'usluga_student_2' => '30000', 'usluga_bonus_2' => '15000',
            'usluga_student_3' => '40000', 'usluga_bonus_3' => '20000', 'usluga_student_4' => '50000', 'usluga_bonus_4' => '25000'];

        $this->actingAs($admin)->get('/settings/usluga')->assertOk()->assertSee('faqat sAdmin', false);
        $this->actingAs($admin)->put('/settings/usluga', $payload)->assertForbidden();
        $this->assertNull($b->fresh()->usluga_from);

        $this->actingAs($sadmin)->withSession(['current_branch_id' => $b->id])->put('/settings/usluga', $payload)->assertSessionHasNoErrors();
        $b = $b->fresh();
        $this->assertSame('2026-10-01', $b->usluga_from->toDateString());
        $this->assertSame(20000, $b->usluga_rates[1]['student']);
        $this->assertSame(25000, $b->usluga_rates[4]['bonus']);
        $this->assertTrue(AuditLog::where('action', 'settings.usluga')->exists());
    }

    public function test_admin_sets_hired_at_and_change_is_logged(): void
    {
        $b = $this->branch();
        $admin = $this->user(Role::Admin, $b, ['staff.view']);
        $teacher = $this->user(Role::Teacher, $b, [], ['phone' => '+998 90 111 3301']);

        $this->actingAs($admin)->put("/staff/{$teacher->id}", [
            'name' => $teacher->name, 'username' => $teacher->username, 'phone' => '+998 90 111 3301', 'status' => 'active', 'hired_at' => '2026-03-15',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-03-15', $teacher->fresh()->hired_at->toDateString());
        $this->assertTrue(AuditLog::where('action', 'staff.hired_at_changed')->where('subject_id', $teacher->id)->exists());
    }
}
