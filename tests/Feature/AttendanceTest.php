<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Group;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;
    private array $cat;
    private Group $group;
    private $s1;
    private $s2;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00'); // dushanba, 1-dars kuni (toq kunlar)

        $this->branch = $this->branch();
        $this->admin = $this->user(Role::Admin, $this->branch, ['groups.view', 'groups.create', 'groups.members', 'attendance.view', 'attendance.take', 'attendance.stats']);
        $this->actingAs($this->admin);
        $this->cat = $this->catalog($this->branch);
        $this->group = app(GroupService::class)->create($this->groupPayload($this->cat), $this->admin);

        $this->s1 = $this->student($this->branch);
        $this->s2 = $this->student($this->branch);
        foreach ([$this->s1, $this->s2] as $s) {
            app(EnrollmentService::class)->enroll($this->group, $s, null, $this->admin);
        }
    }

    public function test_teacher_takes_and_edits_todays_attendance(): void
    {
        $this->actingAs($this->cat['teacher'])->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id]])->assertRedirect();

        $this->assertSame(1, AttendanceSession::count());
        $this->assertTrue(Attendance::where('student_id', $this->s1->id)->value('is_present') == 1);
        $this->assertTrue(Attendance::where('student_id', $this->s2->id)->value('is_present') == 0);

        // Bugungi davomadni tahrirlash - ikkalasi ham kelgan
        $this->actingAs($this->cat['teacher'])->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id, $this->s2->id]])->assertRedirect();

        $this->assertSame(1, AttendanceSession::count());
        $this->assertSame(2, Attendance::count());
        $this->assertSame(2, Attendance::where('is_present', true)->count());
    }

    public function test_attendance_only_on_lesson_days(): void
    {
        Carbon::setTestNow('2026-09-22 10:00:00'); // seshanba - toq kunlarda dars yo'q

        $this->actingAs($this->cat['teacher'])->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id]])
            ->assertSessionHasErrors('attendance');
        $this->assertSame(0, Attendance::count());
    }

    public function test_past_days_cannot_be_changed(): void
    {
        $this->actingAs($this->cat['teacher'])->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id, $this->s2->id]]);

        Carbon::setTestNow('2026-09-23 10:00:00'); // 3-kun, yangi dars kuni
        $this->actingAs($this->cat['teacher'])->post("/groups/{$this->group->id}/attendance", ['present' => []]);

        // 21.09 dagi yozuvlar o'zgarmagan, 23.09 uchun yangilari yaratilgan
        $this->assertSame(2, Attendance::where('date', '2026-09-21')->where('is_present', true)->count());
        $this->assertSame(2, Attendance::where('date', '2026-09-23')->where('is_present', false)->count());
    }

    public function test_other_teacher_and_users_without_permission_are_forbidden(): void
    {
        $other = $this->user(Role::Teacher, $this->branch, ['attendance.view', 'attendance.take']);
        $this->actingAs($other)->post("/groups/{$this->group->id}/attendance", ['present' => []])->assertForbidden();

        $manager = $this->user(Role::Manager, $this->branch, ['attendance.view']);
        $this->actingAs($manager)->post("/groups/{$this->group->id}/attendance", ['present' => []])->assertForbidden();

        $manager2 = $this->user(Role::Manager, $this->branch, ['attendance.view', 'attendance.take']);
        $this->actingAs($manager2)->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id]])->assertRedirect();
    }

    public function test_attendance_of_other_branch_group_is_not_accessible(): void
    {
        $b = $this->branch('Boshqa');
        $adminB = $this->user(Role::Admin, $b, ['attendance.view', 'attendance.take']);

        $this->actingAs($adminB)->post("/groups/{$this->group->id}/attendance", ['present' => []])->assertNotFound();
    }

    public function test_ignores_students_that_are_not_in_group(): void
    {
        $stranger = $this->student($this->branch);
        $this->actingAs($this->admin)->post("/groups/{$this->group->id}/attendance", ['present' => [$stranger->id]]);

        $this->assertSame(0, Attendance::where('student_id', $stranger->id)->count());
        $this->assertSame(2, Attendance::count());
    }

    public function test_matrix_states(): void
    {
        $this->actingAs($this->admin)->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id]]);

        Carbon::setTestNow('2026-09-24 10:00:00'); // 23.09 dars bo'lgan, davomad olinmagan
        $matrix = app(AttendanceService::class)->matrix($this->group);

        $states = collect($matrix['days'])->pluck('state', 'date')->all();
        $this->assertSame('held', $states['2026-09-21']);
        $this->assertSame('missed', $states['2026-09-23']);
        $this->assertSame('upcoming', $states['2026-09-25']);

        $row = collect($matrix['rows'])->firstWhere('student.id', $this->s1->id);
        $this->assertSame(100.0, (float) $row['rate']);
        $row2 = collect($matrix['rows'])->firstWhere('student.id', $this->s2->id);
        $this->assertSame(0.0, (float) $row2['rate']);
    }

    public function test_daily_and_monthly_statistics(): void
    {
        $this->actingAs($this->admin)->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id]]);

        $daily = app(AttendanceService::class)->daily('2026-09-21');
        $this->assertSame(1, $daily['totals']['groups']);
        $this->assertSame(1, $daily['totals']['taken']);
        $this->assertSame(1, $daily['totals']['present']);
        $this->assertSame(1, $daily['totals']['absent']);
        $this->assertEquals(50, $daily['totals']['rate']);

        // Dars bo'lmagan kun
        $this->assertSame(0, app(AttendanceService::class)->daily('2026-09-22')['totals']['groups']);

        Carbon::setTestNow('2026-09-25 10:00:00');
        $this->actingAs($this->admin)->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id, $this->s2->id]]);

        $m = app(AttendanceService::class)->monthly('2026-09');
        $this->assertSame(1, $m['totals']['groups']);
        $this->assertSame(3, $m['totals']['present']);
        $this->assertSame(1, $m['totals']['absent']);
        $this->assertEquals(75, $m['totals']['rate']);
        $this->assertSame(3, $m['rows'][0]['scheduled']); // 21, 23, 25 (bugungacha)
        $this->assertSame(2, $m['rows'][0]['held']);      // 23-kuni olinmagan
        $this->assertCount(2, $m['series']);
    }

    public function test_pages_render(): void
    {
        $this->actingAs($this->admin)->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id]]);

        $this->get('/attendance')->assertOk()->assertSee('A1 GURUH');
        $this->get('/attendance/stats?date=2026-09-21&month=2026-09')->assertOk()->assertSee('Davomad statistikasi');
        $this->get("/groups/{$this->group->id}")->assertOk()->assertSee($this->s1->name);
        $this->actingAs($this->cat['teacher'])->get('/attendance')->assertOk();

        $manager = $this->user(Role::Manager, $this->branch, ['attendance.view']);
        $this->actingAs($manager)->get('/attendance/stats')->assertForbidden();
    }

    public function test_api_teacher_flow_and_student_view(): void
    {
        $token = $this->cat['teacher']->createToken('t')->plainTextToken;

        $this->api($token)->getJson('/api/v1/groups')->assertOk()->assertJsonPath('data.0.name', 'A1 GURUH')->assertJsonPath('data.0.price', null);
        $this->api($token)->getJson("/api/v1/groups/{$this->group->id}")->assertOk()->assertJsonPath('data.lesson_today', true)->assertJsonCount(2, 'data.students');
        $this->api($token)->postJson("/api/v1/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id]])->assertOk();
        $this->api($token)->getJson("/api/v1/groups/{$this->group->id}/attendance")->assertOk()->assertJsonCount(2, 'data.students');

        // O'quvchi faqat o'zini ko'radi va davomad ola olmaydi
        $st = $this->s1->createToken('s')->plainTextToken;
        $this->api($st)->getJson('/api/v1/groups')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.price', 500000);
        $this->api($st)->getJson("/api/v1/groups/{$this->group->id}/attendance")->assertOk()->assertJsonCount(1, 'data.students');
        $this->api($st)->postJson("/api/v1/groups/{$this->group->id}/attendance", ['present' => []])->assertForbidden();

        // Boshqa o'qituvchi ko'ra olmaydi
        $other = $this->user(Role::Teacher, $this->branch, ['attendance.view', 'attendance.take'])->createToken('o')->plainTextToken;
        $this->api($other)->getJson("/api/v1/groups/{$this->group->id}")->assertNotFound();
        $this->api($other)->postJson("/api/v1/groups/{$this->group->id}/attendance", ['present' => []])->assertForbidden();
    }
}
