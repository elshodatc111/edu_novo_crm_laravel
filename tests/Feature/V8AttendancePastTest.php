<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** v8: o'tgan kun davomatini tuzatish (`attendance.edit_past`, doc 06 - 16-savol: faqat sAdmin/admin). */
class V8AttendancePastTest extends TestCase
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
        Carbon::setTestNow('2026-09-21 10:00:00'); // dushanba, 1-dars kuni (toq kunlar: Du-Chor-Ju)

        $this->branch = $this->branch();
        $this->admin = $this->user(Role::Admin, $this->branch, [
            'groups.view', 'groups.create', 'groups.members',
            'attendance.view', 'attendance.take', 'attendance.edit_past',
        ]);
        $this->actingAs($this->admin);
        $this->cat = $this->catalog($this->branch);
        $this->group = app(GroupService::class)->create($this->groupPayload($this->cat), $this->admin);

        $this->s1 = $this->student($this->branch, ['name' => 'Birinchi']);
        $this->s2 = $this->student($this->branch, ['name' => 'Ikkinchi']);
        foreach ([$this->s1, $this->s2] as $s) {
            app(EnrollmentService::class)->enroll($this->group, $s, null, $this->admin);
        }
    }

    public function test_admin_corrects_a_day_that_was_never_taken(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00'); // 23.09 o'tgan dars kuni, davomad olinmagan (missed)

        $this->get("/groups/{$this->group->id}/attendance/past?date=2026-09-23")
            ->assertOk()->assertSee('23.09.2026')->assertSee('Olinmagan edi');

        $this->post("/groups/{$this->group->id}/attendance/past", ['date' => '2026-09-23', 'present' => [$this->s1->id]])
            ->assertRedirect()->assertSessionHas('success');

        $session = AttendanceSession::where('date', '2026-09-23')->firstOrFail();
        $this->assertSame($this->admin->id, $session->taken_by);
        $this->assertTrue((bool) Attendance::where('date', '2026-09-23')->where('student_id', $this->s1->id)->value('is_present'));
        $this->assertFalse((bool) Attendance::where('date', '2026-09-23')->where('student_id', $this->s2->id)->value('is_present'));

        $log = AuditLog::where('action', 'attendance.edited_past')->firstOrFail();
        $this->assertStringContainsString('2026-09-23', $log->description);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_admin_edits_a_previously_taken_past_day(): void
    {
        // 21.09 kuni o'qituvchi haqiqiy "bugun" sifatida davomad oldi - faqat 1-si kelgan.
        $this->actingAs($this->cat['teacher'])->post("/groups/{$this->group->id}/attendance", ['present' => [$this->s1->id]]);

        Carbon::setTestNow('2026-09-24 10:00:00'); // endi 21.09 - o'tgan kun

        $this->actingAs($this->admin)
            ->post("/groups/{$this->group->id}/attendance/past", ['date' => '2026-09-21', 'present' => [$this->s1->id, $this->s2->id]])
            ->assertRedirect();

        // Yangi sessiya YARATILMAGAN - eskisi TUZATILGAN.
        $this->assertSame(1, AttendanceSession::where('date', '2026-09-21')->count());
        $this->assertSame(2, Attendance::where('date', '2026-09-21')->where('is_present', true)->count());
    }

    public function test_cannot_edit_today_or_future_date(): void
    {
        $this->post("/groups/{$this->group->id}/attendance/past", ['date' => '2026-09-21', 'present' => []])
            ->assertSessionHasErrors('date');
        $this->post("/groups/{$this->group->id}/attendance/past", ['date' => '2026-09-25', 'present' => []])
            ->assertSessionHasErrors('date');
        $this->assertSame(0, Attendance::count());
    }

    public function test_cannot_edit_a_non_lesson_day(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00');

        // 22.09 - seshanba, bu guruhda toq kunlar (Du-Chor-Ju) dars, demak dars yo'q.
        $this->post("/groups/{$this->group->id}/attendance/past", ['date' => '2026-09-22', 'present' => []])
            ->assertSessionHasErrors('date');
        $this->assertSame(0, Attendance::count());
    }

    public function test_teacher_cannot_access_past_edit_for_own_group(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00');

        $this->actingAs($this->cat['teacher'])->get("/groups/{$this->group->id}/attendance/past")->assertForbidden();
        $this->actingAs($this->cat['teacher'])
            ->post("/groups/{$this->group->id}/attendance/past", ['date' => '2026-09-23', 'present' => []])
            ->assertForbidden();
    }

    public function test_manager_without_edit_past_permission_is_forbidden(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00');
        $manager = $this->user(Role::Manager, $this->branch, ['attendance.view', 'attendance.take']);

        $this->actingAs($manager)->get("/groups/{$this->group->id}/attendance/past")->assertForbidden();
        $this->actingAs($manager)
            ->post("/groups/{$this->group->id}/attendance/past", ['date' => '2026-09-23', 'present' => []])
            ->assertForbidden();
    }

    public function test_other_branch_admin_cannot_access(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00');
        $other = $this->branch('Boshqa');
        $adminB = $this->user(Role::Admin, $other, ['attendance.view', 'attendance.edit_past']);

        $this->actingAs($adminB)->get("/groups/{$this->group->id}/attendance/past")->assertNotFound();
        $this->actingAs($adminB)
            ->post("/groups/{$this->group->id}/attendance/past", ['date' => '2026-09-23', 'present' => []])
            ->assertNotFound();
    }

    public function test_past_correction_does_not_send_absence_sms(): void
    {
        config(['services.eskiz.email' => 'x@x.uz', 'services.eskiz.password' => 'p', 'services.eskiz.from' => '4546']);
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'T']]),
            '*/message/sms/send' => Http::response(['status' => 'waiting']),
        ]);
        $this->branch->update(['sms_enabled' => true, 'sms_auto_absent' => true]);
        SmsTemplate::updateOrCreate(['branch_id' => $this->branch->id, 'key' => 'absence_notice'], ['body' => 'x', 'is_enabled' => true]);
        $this->s1->update(['phone2' => '+998 90 999 8888']);

        Carbon::setTestNow('2026-09-24 10:00:00');
        $this->post("/groups/{$this->group->id}/attendance/past", ['date' => '2026-09-23', 'present' => []])->assertRedirect();

        $this->assertSame(0, SmsMessage::count());
        Http::assertNothingSent();
    }

    public function test_matrix_link_visible_only_with_permission(): void
    {
        Carbon::setTestNow('2026-09-24 10:00:00');

        // Matn Blade shablonida oddiy HTML matn (o'zgaruvchi emas), shuning uchun kavo'rnoq escape qilinmaydi.
        $this->actingAs($this->admin)->get("/groups/{$this->group->id}")
            ->assertOk()->assertSee("O'tgan kunni tuzatish", false);

        $manager = $this->user(Role::Manager, $this->branch, ['groups.view', 'attendance.view', 'attendance.take']);
        $this->actingAs($manager)->get("/groups/{$this->group->id}")
            ->assertOk()->assertDontSee("O'tgan kunni tuzatish", false);
    }
}
