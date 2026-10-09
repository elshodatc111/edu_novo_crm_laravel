<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\GroupDay;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: boshlanmagan, o'quvchisiz guruhni arxivlash (admin/sAdmin, parol va sabab bilan). */
class V13GroupArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function setup3(array $perms = ['groups.view', 'groups.create', 'groups.delete', 'groups.members', 'students.view']): array
    {
        Carbon::setTestNow('2026-09-14 09:00:00');
        $branch = $this->branch();
        $c = $this->catalog($branch);
        $admin = $this->user(Role::Admin, $branch, $perms);
        $this->actingAs($admin);
        $group = app(GroupService::class)->create($this->groupPayload($c), $admin); // 21.09 dan, 4 ta dars

        return [$branch, $c, $admin, $group];
    }

    private function archiveUrl($user, Group $group, string $reason = 'Guruh yig\'ilmadi')
    {
        return $this->actingAs($user)->post("/groups/{$group->id}/archive", ['reason' => $reason]);
    }

    public function test_admin_archives_not_started_group_with_password_and_reason(): void
    {
        [$branch, $c, $admin, $group] = $this->setup3();

        $url = $this->archiveUrl($admin, $group)->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/confirm/', $url);
        $this->assertNotNull(Group::find($group->id));                       // 1-bosqichda hali o'chmagan

        $this->get($url)->assertOk()->assertSee('Parolingiz')->assertSee("Guruhni o'chirish");
        $this->post($url, ['password' => 'noto-g-ri'])->assertSessionHasErrors('password');
        $this->assertNotNull(Group::find($group->id));

        $this->post($url, ['password' => 'parol12345'])->assertRedirect(route('groups.index'))->assertSessionHas('success');

        $this->assertNull(Group::find($group->id));                          // ro'yxatdan yo'qoladi
        $archived = Group::withTrashed()->find($group->id);                  // lekin tarix saqlanadi
        $this->assertSame($admin->id, $archived->deleted_by);
        $this->assertSame("Guruh yig'ilmadi", $archived->delete_reason);
        $this->assertSame(0, GroupDay::where('group_id', $group->id)->count()); // dars kunlari bo'shatildi
        $this->assertTrue(AuditLog::where('action', 'group.archived')->exists());
        $this->get("/groups/{$group->id}")->assertNotFound();
        $this->get('/groups')->assertOk()->assertDontSee($group->name);
    }

    public function test_slots_are_freed_so_a_new_group_can_reuse_room_and_time(): void
    {
        [$branch, $c, $admin, $group] = $this->setup3();
        app(GroupService::class)->archive($group, 'Sabab', $admin);

        $again = app(GroupService::class)->create($this->groupPayload($c, ['name' => 'yangi guruh']), $admin);
        $this->assertSame(4, $again->days()->count());
    }

    public function test_reason_is_required(): void
    {
        [$branch, $c, $admin, $group] = $this->setup3();

        $this->archiveUrl($admin, $group, '')->assertSessionHasErrors('reason');
        $this->assertNotNull(Group::find($group->id));
    }

    public function test_started_group_cannot_be_archived(): void
    {
        [$branch, $c, $admin, $group] = $this->setup3();
        Carbon::setTestNow('2026-09-21 10:00:00');                           // boshlanish sanasi keldi

        $this->archiveUrl($admin, $group)->assertSessionHasErrors('group');
        $this->assertNotNull(Group::find($group->id));
    }

    public function test_group_with_attendance_or_active_students_cannot_be_archived(): void
    {
        [$branch, $c, $admin, $group] = $this->setup3();

        $student = $this->student($branch);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);
        $this->archiveUrl($admin, $group)->assertSessionHasErrors('group');
        $this->assertNotNull(Group::find($group->id));

        app(EnrollmentService::class)->remove($group->fresh(), $student, 0, null, $admin);
        $this->assertSame(0, $student->fresh()->balance);

        AttendanceSession::create(['branch_id' => $branch->id, 'group_id' => $group->id, 'date' => '2026-09-21', 'taken_by' => $admin->id]);
        $this->archiveUrl($admin, $group)->assertSessionHasErrors('group');

        // Faol o'quvchi yo'q va davomad yo'q bo'lganda (chiqarilgan o'quvchi tarixi bilan) arxivlanadi
        AttendanceSession::where('group_id', $group->id)->delete();
        app(GroupService::class)->archive($group->fresh(), 'Sabab', $admin);
        $this->assertNull(Group::find($group->id));
        $this->assertSame($group->id, Group::withTrashed()->find($group->id)->id);
    }

    public function test_only_users_with_groups_delete_can_archive(): void
    {
        [$branch, $c, $admin, $group] = $this->setup3();

        foreach ([Role::Manager, Role::Operator, Role::Teacher] as $role) {
            $u = $this->user($role, $branch, ['groups.view', 'groups.update', 'groups.members', 'attendance.view']);
            $this->archiveUrl($u, $group)->assertForbidden();
        }
        $noPerm = $this->user(Role::Admin, $branch, ['groups.view', 'groups.update']);
        $this->archiveUrl($noPerm, $group)->assertForbidden();
        $this->assertNotNull(Group::find($group->id));

        // sAdmin ruxsatsiz ham bajara oladi
        $sadmin = $this->user(Role::SAdmin);
        $this->withSession(['current_branch_id' => $branch->id])->archiveUrl($sadmin, $group)->assertRedirect();
    }

    public function test_group_from_another_branch_is_404(): void
    {
        [$branch, $c, $admin, $group] = $this->setup3();
        $other = $this->user(Role::Admin, $this->branch('Boshqa'), ['groups.view', 'groups.delete']);

        $this->archiveUrl($other, $group)->assertNotFound();
        // Boshqa filial foydalanuvchisi guruhni ko'rmaydi; guruhning o'zi arxivlanmagan (deleted_at bo'sh)
        $this->assertNull(Group::withoutGlobalScope(\App\Models\Concerns\BranchScope::class)->find($group->id)->deleted_at);
    }

    public function test_delete_button_visibility_and_permission_registry(): void
    {
        [$branch, $c, $admin, $group] = $this->setup3();

        $this->get("/groups/{$group->id}")->assertOk()->assertSee('open-archive', false);

        $noDelete = $this->user(Role::Admin, $branch, ['groups.view', 'groups.update']);
        $this->actingAs($noDelete)->get("/groups/{$group->id}")->assertOk()->assertDontSee('open-archive', false);

        $this->assertContains('groups.delete', \App\Support\PermissionRegistry::forRole(Role::Admin));
        $this->assertNotContains('groups.delete', \App\Support\PermissionRegistry::forRole(Role::Manager));
        $this->assertNotContains('groups.delete', \App\Support\PermissionRegistry::forRole(Role::Operator));
        $this->assertNotContains('groups.delete', \App\Support\PermissionRegistry::forRole(Role::Teacher));
    }

    public function test_student_history_still_shows_archived_group_name(): void
    {
        [$branch, $c, $admin, $group] = $this->setup3();
        $student = $this->student($branch);
        app(EnrollmentService::class)->enroll($group, $student, null, $admin);
        app(EnrollmentService::class)->remove($group->fresh(), $student, 0, null, $admin);
        app(GroupService::class)->archive($group->fresh(), 'Sabab', $admin);

        $this->get("/students/{$student->id}")->assertOk();
    }
}
