<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Group;
use App\Models\Holiday;
use App\Services\ScheduleService;
use App\Enums\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00'); // dushanba
    }

    private function admin($branch)
    {
        return $this->user(Role::Admin, $branch, ['groups.view', 'groups.create', 'groups.update', 'groups.members', 'students.view']);
    }

    public function test_lesson_dates_follow_schedule_and_skip_holidays(): void
    {
        $branch = $this->branch();
        $this->actingAs($this->admin($branch));

        $this->assertSame(['2026-09-21', '2026-09-23', '2026-09-25', '2026-09-28'],
            app(ScheduleService::class)->lessonDates('2026-09-21', 4, Schedule::Odd));

        Holiday::create(['branch_id' => $branch->id, 'date' => '2026-09-23']);

        $this->assertSame(['2026-09-21', '2026-09-25', '2026-09-28', '2026-09-30'],
            app(ScheduleService::class)->lessonDates('2026-09-21', 4, Schedule::Odd));

        // Yakshanba hech qachon dars kuni bo'lmaydi
        $daily = app(ScheduleService::class)->lessonDates('2026-09-26', 3, Schedule::Daily);
        $this->assertSame(['2026-09-26', '2026-09-28', '2026-09-29'], $daily);
    }

    public function test_admin_creates_group_with_days_and_price_snapshot(): void
    {
        $branch = $this->branch();
        $c = $this->catalog($branch);

        $this->actingAs($this->admin($branch))->post('/groups', $this->groupPayload($c))->assertRedirect();

        $group = Group::firstOrFail();
        $this->assertSame('A1 GURUH', $group->name);
        $this->assertSame(500000, $group->price);
        $this->assertSame(50000, $group->early_discount);
        $this->assertSame('2026-09-21', $group->starts_on->toDateString());
        $this->assertSame('2026-09-28', $group->ends_on->toDateString());
        $this->assertSame(4, $group->days()->count());
    }

    public function test_room_and_teacher_conflicts_are_rejected(): void
    {
        $branch = $this->branch();
        $c = $this->catalog($branch);
        $admin = $this->admin($branch);

        $this->actingAs($admin)->post('/groups', $this->groupPayload($c))->assertRedirect();

        // Bir xil xona, bir xil vaqt
        $other = $this->user(Role::Teacher, $branch);
        $this->actingAs($admin)->post('/groups', $this->groupPayload($c, ['name' => 'B', 'teacher_id' => $other->id]))
            ->assertSessionHasErrors('lesson_time_id');

        // Bir xil o'qituvchi, boshqa xona
        $room2 = \App\Models\Room::create(['branch_id' => $branch->id, 'name' => '2-xona']);
        $this->actingAs($admin)->post('/groups', $this->groupPayload($c, ['name' => 'C', 'room_id' => $room2->id]))
            ->assertSessionHasErrors('lesson_time_id');

        // Boshqa xona va boshqa o'qituvchi - ruxsat
        $this->actingAs($admin)->post('/groups', $this->groupPayload($c, ['name' => 'D', 'room_id' => $room2->id, 'teacher_id' => $other->id]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Group::count());
    }

    public function test_cannot_use_other_branch_catalog_or_teacher(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $cB = $this->catalog($b);

        $this->actingAs($this->admin($a))->post('/groups', $this->groupPayload($cB))
            ->assertSessionHasErrors(['course_id', 'teacher_id', 'room_id']);
        $this->assertSame(0, Group::count());
    }

    public function test_group_update_changes_teacher_of_future_days_only(): void
    {
        $branch = $this->branch();
        $c = $this->catalog($branch);
        $admin = $this->admin($branch);
        $this->actingAs($admin)->post('/groups', $this->groupPayload($c));
        $group = Group::firstOrFail();

        Carbon::setTestNow('2026-09-25 10:00:00');
        $new = $this->user(Role::Teacher, $branch);

        $this->actingAs($admin)->put("/groups/{$group->id}", [
            'name' => 'Yangi nom', 'course_id' => $c['course']->id, 'teacher_id' => $new->id, 'teacher_rate' => 100000, 'teacher_bonus_rate' => 20000,
        ])->assertRedirect();

        $days = $group->days()->get();
        $this->assertSame($c['teacher']->id, $days[0]->teacher_id);   // 21.09 - o'tgan
        $this->assertSame($new->id, $days[2]->teacher_id);            // 25.09 - bugun
        $this->assertSame($new->id, $days[3]->teacher_id);            // 28.09 - kelgusi
        $this->assertSame('YANGI NOM', $group->fresh()->name);
    }

    public function test_teacher_sees_only_own_groups_and_manager_needs_permission(): void
    {
        $branch = $this->branch();
        $c = $this->catalog($branch);
        $this->actingAs($this->admin($branch))->post('/groups', $this->groupPayload($c));
        $group = Group::firstOrFail();

        $other = $this->user(Role::Teacher, $branch, ['attendance.view']);
        $this->actingAs($c['teacher'])->get('/groups')->assertOk()->assertSee('A1 GURUH');
        $this->actingAs($c['teacher'])->get("/groups/{$group->id}")->assertOk();
        $this->actingAs($other)->get('/groups')->assertOk()->assertDontSee('A1 GURUH');
        $this->actingAs($other)->get("/groups/{$group->id}")->assertForbidden();

        $manager = $this->user(Role::Manager, $branch, ['students.view']);
        $this->actingAs($manager)->get('/groups')->assertForbidden();
    }

    public function test_group_of_other_branch_is_not_found(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $this->actingAs($this->admin($b))->post('/groups', $this->groupPayload($this->catalog($b)));
        $group = Group::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->admin($a))->get("/groups/{$group->id}")->assertNotFound();
    }
}
