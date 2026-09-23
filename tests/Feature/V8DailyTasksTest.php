<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lead;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\DashboardService;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** v8 B5: kunlik vazifalar paneli — avtomatik hisoblanadigan vazifalar va "bajardim" (faqat bugunga yashirish). */
class V8DailyTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function todoFor(User $user): array
    {
        $this->actingAs($user);

        return app(DashboardService::class)->todo($user->fresh());
    }

    public function test_manager_sees_debtor_lead_and_birthday_tasks_but_not_attendance(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['payments.view', 'leads.view', 'students.view']);

        $this->student($branch, ['balance' => -50000, 'birthday' => '2000-09-21']);
        $lead = Lead::create(['branch_id' => $branch->id, 'name' => 'Aziz', 'phone' => '+998 90 111 2222', 'status' => Lead::NEW]);
        $lead->forceFill(['created_at' => now()->subHours(30)])->save();

        $keys = collect($this->todoFor($manager))->pluck('key')->all();

        $this->assertContains('debtors', $keys);
        $this->assertContains('leads', $keys);
        $this->assertContains('birthdays', $keys);
        $this->assertNotContains('attendance', $keys);
        $this->assertNotContains('discount', $keys);
    }

    public function test_fresh_lead_within_24h_is_not_listed(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['leads.view']);
        Lead::create(['branch_id' => $branch->id, 'name' => 'Yangi', 'phone' => '+998 90 111 2233', 'status' => Lead::NEW]);

        $this->assertSame([], $this->todoFor($manager));
    }

    public function test_teacher_only_sees_own_group_attendance_missing(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $cat = $this->catalog($branch);
        $admin = $this->user(Role::Admin, $branch, ['groups.create']);
        $this->actingAs($admin);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);

        $otherTeacher = $this->user(Role::Teacher, $branch, ['attendance.view', 'attendance.take']);

        $todo = $this->todoFor($cat['teacher']);
        $this->assertCount(1, $todo);
        $this->assertSame('attendance', $todo[0]['key']);
        $this->assertSame($group->name, $todo[0]['items'][0]['label']);

        $this->assertSame([], $this->todoFor($otherTeacher));
    }

    public function test_attendance_task_disappears_once_taken(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $cat = $this->catalog($branch);
        $admin = $this->user(Role::Admin, $branch, ['groups.create']);
        $this->actingAs($admin);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $student = $this->student($branch);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);

        $this->assertNotEmpty($this->todoFor($cat['teacher']));

        app(AttendanceService::class)->takeToday($group, [$student->id], $cat['teacher']);

        $this->assertSame([], $this->todoFor($cat['teacher']));
    }

    public function test_dismiss_hides_today_but_reappears_tomorrow_if_still_unresolved(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['payments.view']);
        $debtor = $this->student($branch, ['balance' => -20000]);

        $this->actingAs($manager);
        $service = app(DashboardService::class);
        $this->assertNotEmpty($service->todo($manager));

        $service->dismiss($manager, "debtor:{$debtor->id}");
        $this->assertSame([], $service->todo($manager));

        // Ertaga — qarz hali ham bor, vazifa qaytadi (soxta "bajardim" foyda bermaydi)
        Carbon::setTestNow('2026-09-22 10:00:00');
        $this->assertNotEmpty($service->todo($manager));
    }

    public function test_dismiss_is_idempotent_and_scoped_per_user(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['payments.view']);
        $other = $this->user(Role::Manager, $branch, ['payments.view']);
        $debtor = $this->student($branch, ['balance' => -20000]);

        $service = app(DashboardService::class);
        $service->dismiss($manager, "debtor:{$debtor->id}");
        $service->dismiss($manager, "debtor:{$debtor->id}"); // ikkinchi marta — xatolik bo'lmasligi kerak

        $this->actingAs($manager);
        $this->assertSame([], $service->todo($manager));

        $this->actingAs($other);
        $this->assertNotEmpty($service->todo($other));
    }

    public function test_dismiss_route_requires_auth_and_hides_task(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['payments.view']);
        $debtor = $this->student($branch, ['balance' => -20000]);

        $this->post('/tasks/dismiss', ['task_key' => "debtor:{$debtor->id}"])->assertRedirect('/login');

        $this->actingAs($manager)->post('/tasks/dismiss', ['task_key' => "debtor:{$debtor->id}"])->assertRedirect();
        $this->assertSame([], app(DashboardService::class)->todo($manager));
    }

    public function test_admin_sees_weekly_activity_but_manager_does_not(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['staff.view']);
        $manager = $this->user(Role::Manager, $branch, ['payments.view']);
        $debtor = $this->student($branch, ['balance' => -20000]);

        app(DashboardService::class)->dismiss($manager, "debtor:{$debtor->id}");

        $this->actingAs($admin)->get('/')->assertOk()->assertSee('Xodimlar faolligi');
        $this->actingAs($manager)->get('/')->assertOk()->assertDontSee('Xodimlar faolligi');
    }

    public function test_operator_default_permissions_see_relevant_tasks(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $operator = $this->user(Role::Operator, $branch, [
            'students.view', 'students.create', 'students.update', 'students.notes',
            'groups.view', 'groups.members', 'attendance.view', 'attendance.take',
            'payments.view', 'payments.create', 'leads.view', 'leads.manage',
        ]);
        $this->student($branch, ['balance' => -10000]);

        $keys = collect($this->todoFor($operator))->pluck('key')->all();
        $this->assertContains('debtors', $keys);
    }

    public function test_student_role_gets_empty_todo(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $student = $this->student($branch);

        $this->assertSame([], $this->todoFor($student));
    }

    public function test_sadmin_without_selected_branch_sees_no_tasks(): void
    {
        // sAdmin filial tanlamaganda Gate::before orqali barcha ruxsatga ega va BranchScope
        // barcha filiallarni ko'rsatadi — bir nechta filial vazifasi aralashib ketmasligi uchun
        // (bosh sahifadagi kalendar kabi) hech narsa ko'rsatilmaydi.
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch();
        $cat = $this->catalog($branch);
        $admin = $this->user(Role::Admin, $branch, ['groups.create']);
        $this->actingAs($admin);
        app(GroupService::class)->create($this->groupPayload($cat, ['name' => 'BOSHQA-FILIAL-GURUHI']), $admin);
        $this->student($branch, ['balance' => -10000]);

        $sadmin = $this->user(Role::SAdmin);
        $this->assertSame([], $this->todoFor($sadmin));

        $this->actingAs($sadmin)->get('/')->assertOk()->assertDontSee('BOSHQA-FILIAL-GURUHI');

        // Filial tanlansa — o'sha filialning vazifalari ko'rinadi
        $this->withSession(['current_branch_id' => $branch->id])->get('/')->assertOk()->assertSee('BOSHQA-FILIAL-GURUHI');
    }
}
