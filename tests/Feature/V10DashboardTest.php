<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lead;
use App\Services\DashboardService;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** v10 (6-band): bosh sahifa - qarzdorlar/lidlar panellari scroll bo'ladi, faol/kutilayotgan guruhlar soni chiqadi. */
class V10DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
    }

    public function test_dashboard_reports_active_and_upcoming_group_counts_with_student_totals(): void
    {
        $branch = $this->branch('Namangan');
        $admin = $this->user(Role::Admin, $branch, ['groups.view']);

        $this->actingAs($admin);

        $catA = $this->catalog($branch);
        $active = app(GroupService::class)->create($this->groupPayload($catA, ['name' => 'Faol guruh']), $admin);
        $active->forceFill(['starts_on' => '2026-09-01', 'ends_on' => '2026-10-10'])->save();

        // Ikkinchi guruh - alohida kurs/xona/vaqt (catalog() bir xil filialda takrorlansa
        // kurs nomi unique cheklovga uchraydi, xona/o'qituvchi esa jadval to'qnashuviga uchraydi).
        $catB = [
            'teacher' => $this->user(Role::Teacher, $branch, ['attendance.view', 'attendance.take']),
            'course' => \App\Models\Course::create(['branch_id' => $branch->id, 'name' => 'Ingliz tili']),
            'room' => \App\Models\Room::create(['branch_id' => $branch->id, 'name' => '2-xona']),
            'time' => \App\Models\LessonTime::create(['branch_id' => $branch->id, 'starts_at' => '11:00', 'ends_at' => '12:30']),
            'plan' => \App\Models\PricePlan::create(['branch_id' => $branch->id, 'name' => 'Standart 2', 'amount' => 500000, 'early_discount' => 50000]),
        ];
        $upcoming = app(GroupService::class)->create($this->groupPayload($catB, ['name' => 'Kutilayotgan guruh']), $admin);
        $upcoming->forceFill(['starts_on' => '2026-10-15', 'ends_on' => '2026-11-15'])->save();

        $s1 = $this->student($branch, ['name' => 'S1']);
        $s2 = $this->student($branch, ['name' => 'S2']);
        $s3 = $this->student($branch, ['name' => 'S3']);
        app(EnrollmentService::class)->enroll($active, $s1, null, $admin);
        app(EnrollmentService::class)->enroll($active, $s2, null, $admin);
        app(EnrollmentService::class)->enroll($upcoming, $s3, null, $admin);

        $stats = app(DashboardService::class)->groupStats();
        $this->assertSame(['groups' => 1, 'students' => 2], $stats['active']);
        $this->assertSame(['groups' => 1, 'students' => 1], $stats['upcoming']);

        $this->actingAs($admin)->get('/')->assertOk()
            ->assertSee('Faol guruhlar')
            ->assertSee('Boshlanishi kutilayotgan guruhlar')
            ->assertSee("2 o'quvchi", false)
            ->assertSee("1 o'quvchi", false);
    }

    public function test_group_stats_hidden_without_permission_or_unselected_branch(): void
    {
        $branch = $this->branch('Buxoro');
        $viewer = $this->user(Role::Admin, $branch, []); // 'groups.view' yo'q

        $this->actingAs($viewer)->get('/')->assertOk()->assertDontSee('Boshlanishi kutilayotgan guruhlar');

        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->get('/')->assertOk()->assertDontSee('Boshlanishi kutilayotgan guruhlar');
    }

    public function test_debtors_and_stale_leads_panels_render_as_scrollable_containers(): void
    {
        $branch = $this->branch('Farg\'ona');
        $admin = $this->user(Role::Admin, $branch, ['payments.view', 'leads.view']);

        $debtor = $this->student($branch, ['name' => 'Qarzdor', 'balance' => -30000]);
        $lead = Lead::create(['branch_id' => $branch->id, 'name' => 'Eski lid', 'phone' => '+998 90 555 5555', 'status' => Lead::NEW]);
        $lead->forceFill(['created_at' => now()->subHours(40)])->save();

        $html = $this->actingAs($admin)->get('/')->assertOk()
            ->assertSee('Bugungi qarzdorlar')
            ->assertSee('24 soatdan ortiq javobsiz lidlar')
            ->getContent();

        // Ikkala panel ham scroll bo'ladigan konteynerga o'ralgan (max balandlik + overflow-y-auto)
        $this->assertSame(2, substr_count($html, 'max-h-72 overflow-y-auto'));
    }
}
