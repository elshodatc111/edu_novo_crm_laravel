<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\BalanceTransaction;
use App\Models\Group;
use App\Models\LessonTime;
use App\Models\PricePlan;
use App\Models\Room;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: guruhni to'liq tahrirlash (xona, vaqt, kunlar, sana, darslar soni, narx) va filial almashganda bosh sahifaga o'tish. */
class V13GroupEditTest extends TestCase
{
    use RefreshDatabase;

    private function setup3(array $perms = []): array
    {
        $branch = $this->branch();
        $c = $this->catalog($branch);
        $admin = $this->user(Role::Admin, $branch, ['groups.view', 'groups.create', 'groups.update', 'groups.members', 'students.view', ...$perms]);
        $this->actingAs($admin);
        $group = app(GroupService::class)->create($this->groupPayload($c), $admin); // toq kunlar, 21.09 dan 4 ta dars: 21, 23, 25, 28

        return [$branch, $c, $admin, $group];
    }

    private function payload(array $c, array $over = []): array
    {
        return ['name' => 'a1 guruh', 'course_id' => $c['course']->id, 'teacher_id' => $c['teacher']->id, ...$over];
    }

    private function dates(Group $g): array
    {
        return $g->days()->get()->map(fn ($d) => $d->date->toDateString())->all();
    }

    public function test_not_started_group_schedule_can_be_fully_changed(): void
    {
        Carbon::setTestNow('2026-09-14 09:00:00');
        [$branch, $c, $admin, $group] = $this->setup3();
        $room2 = Room::create(['branch_id' => $branch->id, 'name' => '2-xona']);
        $time2 = LessonTime::create(['branch_id' => $branch->id, 'starts_at' => '14:00', 'ends_at' => '15:30']);

        $this->put("/groups/{$group->id}", $this->payload($c, [
            'room_id' => $room2->id, 'lesson_time_id' => $time2->id, 'schedule' => 'even', 'starts_on' => '2026-09-22', 'lesson_count' => 6,
        ]))->assertRedirect(route('groups.show', $group));

        $fresh = $group->fresh();
        $this->assertSame(['2026-09-22', '2026-09-24', '2026-09-26', '2026-09-29', '2026-10-01', '2026-10-03'], $this->dates($fresh));
        $this->assertSame(6, $fresh->lesson_count);
        $this->assertSame('2026-09-22', $fresh->starts_on->toDateString());
        $this->assertSame('2026-10-03', $fresh->ends_on->toDateString());
        $this->assertSame($room2->id, $fresh->room_id);
        $this->assertSame($time2->id, $fresh->lesson_time_id);
        $this->assertSame('even', $fresh->schedule->value);
        $this->assertSame(6, $fresh->days()->where('room_id', $room2->id)->where('lesson_time_id', $time2->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.updated']);
    }

    public function test_started_group_keeps_past_lessons_and_rebuilds_only_future(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        [$branch, $c, $admin, $group] = $this->setup3();
        $room2 = Room::create(['branch_id' => $branch->id, 'name' => '2-xona']);

        $this->put("/groups/{$group->id}", $this->payload($c, ['room_id' => $room2->id, 'schedule' => 'daily', 'lesson_count' => 5]))->assertRedirect();

        $fresh = $group->fresh();
        $this->assertSame(['2026-09-21', '2026-09-23', '2026-09-25', '2026-09-26', '2026-09-28'], $this->dates($fresh));
        $days = $fresh->days()->get();
        $this->assertSame($c['room']->id, $days[0]->room_id);   // o'tgan darslar eski xonada qoladi
        $this->assertSame($c['room']->id, $days[1]->room_id);
        $this->assertSame($room2->id, $days[2]->room_id);       // kelgusilari yangi xonada
        $this->assertSame('2026-09-21', $fresh->starts_on->toDateString());
        $this->assertSame('2026-09-28', $fresh->ends_on->toDateString());
        $this->assertSame(5, $fresh->lesson_count);
    }

    public function test_started_group_start_date_and_too_small_count_are_rejected(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        [$branch, $c, $admin, $group] = $this->setup3();
        $before = $this->dates($group);

        $this->put("/groups/{$group->id}", $this->payload($c, ['starts_on' => '2026-09-28']))->assertSessionHasErrors('starts_on');
        $this->put("/groups/{$group->id}", $this->payload($c, ['lesson_count' => 1]))->assertSessionHasErrors('lesson_count');

        $this->assertSame($before, $this->dates($group->fresh()));
    }

    public function test_lesson_with_taken_attendance_is_locked_even_if_today(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');
        [$branch, $c, $admin, $group] = $this->setup3();
        AttendanceSession::create(['branch_id' => $branch->id, 'group_id' => $group->id, 'date' => '2026-09-23', 'taken_by' => $admin->id]);

        $this->put("/groups/{$group->id}", $this->payload($c, ['lesson_count' => 3]))->assertRedirect();

        $this->assertSame(['2026-09-21', '2026-09-23', '2026-09-25'], $this->dates($group->fresh()));
        $this->assertSame(3, $group->fresh()->lesson_count);
    }

    public function test_conflicting_room_or_time_is_rejected_and_nothing_changes(): void
    {
        Carbon::setTestNow('2026-09-14 09:00:00');
        [$branch, $c, $admin, $groupA] = $this->setup3();
        $room2 = Room::create(['branch_id' => $branch->id, 'name' => '2-xona']);
        $teacher2 = $this->user(Role::Teacher, $branch);
        $groupB = app(GroupService::class)->create($this->groupPayload($c, ['name' => 'b guruh', 'room_id' => $room2->id, 'teacher_id' => $teacher2->id]), $admin);
        $before = $this->dates($groupB);

        // B guruhni A guruh xonasiga ko'chirish: shu kunlar va vaqtda band
        $this->put("/groups/{$groupB->id}", $this->payload($c, ['name' => 'b guruh', 'teacher_id' => $teacher2->id, 'room_id' => $c['room']->id]))
            ->assertSessionHasErrors('lesson_time_id');

        $this->assertSame($room2->id, $groupB->fresh()->room_id);
        $this->assertSame($before, $this->dates($groupB->fresh()));
    }

    public function test_price_change_requires_confirmation_and_adjusts_active_members_via_journal(): void
    {
        Carbon::setTestNow('2026-09-14 09:00:00');
        [$branch, $c, $admin, $group] = $this->setup3(['groups.change_price']);
        $student = $this->student($branch);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);
        $this->assertSame(-500000, $student->fresh()->balance);

        $dear = PricePlan::create(['branch_id' => $branch->id, 'name' => 'Qimmat', 'amount' => 600000, 'early_discount' => 0]);
        $cheap = PricePlan::create(['branch_id' => $branch->id, 'name' => 'Arzon', 'amount' => 400000, 'early_discount' => 0]);

        // Tasdiqsiz - o'zgarmaydi
        $this->put("/groups/{$group->id}", $this->payload($c, ['price_plan_id' => $dear->id]))->assertSessionHasErrors('confirm_price_change');
        $this->assertSame(500000, $group->fresh()->price);
        $this->assertSame(-500000, $student->fresh()->balance);

        // Tasdiq bilan - qimmatlashadi: 100 000 qo'shimcha yechiladi
        $this->put("/groups/{$group->id}", $this->payload($c, ['price_plan_id' => $dear->id, 'confirm_price_change' => 1]))->assertRedirect();
        $this->assertSame(600000, $group->fresh()->price);
        $this->assertSame(-600000, $student->fresh()->balance);
        $this->assertDatabaseHas('balance_transactions', ['student_id' => $student->id, 'type' => BalanceTransaction::CHARGE, 'amount' => -100000, 'group_id' => $group->id]);

        // Arzonlashadi: 200 000 qaytariladi
        $this->put("/groups/{$group->id}", $this->payload($c, ['price_plan_id' => $cheap->id, 'confirm_price_change' => 1]))->assertRedirect();
        $this->assertSame(400000, $group->fresh()->price);
        $this->assertSame(-400000, $student->fresh()->balance);
        $this->assertDatabaseHas('balance_transactions', ['student_id' => $student->id, 'type' => BalanceTransaction::REFUND, 'amount' => 200000]);

        // Chiqarilganda aynan joriy narx qaytadi - balans 0
        app(EnrollmentService::class)->remove($group->fresh(), $student, 0, null, $admin);
        $this->assertSame(0, $student->fresh()->balance);
    }

    public function test_price_without_members_changes_without_confirmation_and_unchanged_plan_is_kept(): void
    {
        Carbon::setTestNow('2026-09-14 09:00:00');
        [$branch, $c, $admin, $group] = $this->setup3(['groups.change_price']);
        $dear = PricePlan::create(['branch_id' => $branch->id, 'name' => 'Qimmat', 'amount' => 600000, 'early_discount' => 0]);

        $this->put("/groups/{$group->id}", $this->payload($c))->assertRedirect();
        $this->assertSame(500000, $group->fresh()->price);

        $this->put("/groups/{$group->id}", $this->payload($c, ['price_plan_id' => $dear->id]))->assertRedirect();
        $this->assertSame(600000, $group->fresh()->price);
    }

    public function test_price_cannot_be_changed_without_permission(): void
    {
        Carbon::setTestNow('2026-09-14 09:00:00');
        [$branch, $c, $admin, $group] = $this->setup3(); // groups.change_price yo'q
        $dear = PricePlan::create(['branch_id' => $branch->id, 'name' => 'Qimmat', 'amount' => 600000, 'early_discount' => 0]);

        $this->put("/groups/{$group->id}", $this->payload($c, ['price_plan_id' => $dear->id, 'name' => 'yangi nom']))->assertRedirect();

        $this->assertSame(500000, $group->fresh()->price);
        $this->assertSame('YANGI NOM', $group->fresh()->name);
    }

    public function test_legacy_update_with_only_basic_fields_does_not_touch_schedule(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        [$branch, $c, $admin, $group] = $this->setup3();
        $before = $this->dates($group);

        $this->put("/groups/{$group->id}", $this->payload($c, ['name' => 'Boshqa nom', 'teacher_rate' => 100000]))->assertRedirect();

        $this->assertSame($before, $this->dates($group->fresh()));
        $this->assertSame(100000, $group->fresh()->teacher_rate);
    }

    public function test_edit_page_shows_schedule_fields_and_lock_notice(): void
    {
        Carbon::setTestNow('2026-09-25 10:00:00');
        [$branch, $c, $admin, $group] = $this->setup3(['groups.change_price']);

        $this->get("/groups/{$group->id}/edit")->assertOk()
            ->assertSee('Xona')->assertSee('Dars vaqti')->assertSee('Dars kunlari')->assertSee('Boshlanish sanasi')->assertSee('Darslar soni')->assertSee('Narx rejasi')
            ->assertSee('2 ta dars')->assertSee('Hozirgi narx');
    }

    public function test_finished_group_schedule_fields_are_locked(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00'); // guruh 28.09 da tugagan
        [$branch, $c, $admin, $group] = $this->setup3();
        $room2 = Room::create(['branch_id' => $branch->id, 'name' => '2-xona']);
        $time2 = LessonTime::create(['branch_id' => $branch->id, 'starts_at' => '14:00', 'ends_at' => '15:30']);
        $before = $this->dates($group);

        $this->put("/groups/{$group->id}", $this->payload($c, ['lesson_time_id' => $time2->id]))->assertSessionHasErrors('lesson_time_id');
        $this->put("/groups/{$group->id}", $this->payload($c, ['starts_on' => '2026-10-25']))->assertSessionHasErrors('starts_on');
        $this->put("/groups/{$group->id}", $this->payload($c, ['lesson_count' => 10]))->assertSessionHasErrors('lesson_count');
        $this->put("/groups/{$group->id}", $this->payload($c, ['room_id' => $room2->id, 'schedule' => 'daily']))->assertSessionHasErrors(['room_id', 'schedule']);

        $this->assertSame($before, $this->dates($group->fresh()));
        $this->assertSame($c['time']->id, $group->fresh()->lesson_time_id);

        // Jadvalga tegmaydigan maydonlar tahrirlanadi, bir xil qiymatlar yuborilsa xato yo'q
        $this->put("/groups/{$group->id}", $this->payload($c, [
            'name' => 'yangi nom', 'room_id' => $group->room_id, 'lesson_time_id' => $group->lesson_time_id, 'schedule' => $group->schedule->value,
            'starts_on' => $group->starts_on->toDateString(), 'lesson_count' => $group->lesson_count,
        ]))->assertRedirect();
        $this->assertSame('YANGI NOM', $group->fresh()->name);
    }

    public function test_running_group_time_change_keeps_past_time_and_changes_today_and_future(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00'); // 21 o'tgan, 23 bugun (davomad yo'q), 25, 28 kelgusi
        [$branch, $c, $admin, $group] = $this->setup3();
        $time2 = LessonTime::create(['branch_id' => $branch->id, 'starts_at' => '14:00', 'ends_at' => '15:30']);
        $oldTime = $group->lesson_time_id;

        $this->put("/groups/{$group->id}", $this->payload($c, ['lesson_time_id' => $time2->id]))->assertRedirect();

        $days = $group->fresh()->days()->orderBy('date')->get();
        $this->assertSame([$oldTime, $time2->id, $time2->id, $time2->id], $days->pluck('lesson_time_id')->all());
        $this->assertSame('2026-09-21', $group->fresh()->starts_on->toDateString());
    }

    public function test_running_group_today_with_attendance_keeps_its_time(): void
    {
        Carbon::setTestNow('2026-09-23 10:00:00');
        [$branch, $c, $admin, $group] = $this->setup3();
        $time2 = LessonTime::create(['branch_id' => $branch->id, 'starts_at' => '14:00', 'ends_at' => '15:30']);
        $oldTime = $group->lesson_time_id;
        AttendanceSession::create(['branch_id' => $branch->id, 'group_id' => $group->id, 'date' => '2026-09-23', 'taken_by' => $admin->id]);

        $this->put("/groups/{$group->id}", $this->payload($c, ['lesson_time_id' => $time2->id]))->assertRedirect();

        $days = $group->fresh()->days()->orderBy('date')->get();
        $this->assertSame([$oldTime, $oldTime, $time2->id, $time2->id], $days->pluck('lesson_time_id')->all());
    }

    public function test_not_started_group_start_date_cannot_move_to_past_but_today_is_allowed(): void
    {
        Carbon::setTestNow('2026-09-14 09:00:00');
        [$branch, $c, $admin, $group] = $this->setup3();

        $this->put("/groups/{$group->id}", $this->payload($c, ['starts_on' => '2026-09-13']))->assertSessionHasErrors('starts_on');
        $this->put("/groups/{$group->id}", $this->payload($c, ['starts_on' => '2026-09-14']))->assertRedirect();

        $this->assertSame('2026-09-14', $group->fresh()->starts_on->toDateString()); // 14.09 - dushanba (toq kun)
    }

    public function test_edit_page_for_finished_group_shows_locked_notice(): void
    {
        Carbon::setTestNow('2026-10-20 10:00:00');
        [$branch, $c, $admin, $group] = $this->setup3();

        $this->get("/groups/{$group->id}/edit")->assertOk()->assertSee('yakunlangan')->assertSee('darslar soni o\'zgarmaydi');
    }

    public function test_branch_switch_always_redirects_to_dashboard(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->withHeader('referer', url('/groups/6'))
            ->post('/branch/switch', ['branch_id' => $b->id])->assertRedirect(route('dashboard'));
        $this->post('/branch/switch', ['branch_id' => ''])->assertRedirect(route('dashboard'));
    }
}
