<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BalanceTransaction;
use App\Models\Group;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $actor;
    private Group $group;
    private array $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');

        $this->branch = $this->branch();
        $this->actor = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members', 'students.view', 'students.archive']);
        $this->actingAs($this->actor);
        $this->cat = $this->catalog($this->branch);
        $this->group = app(GroupService::class)->create($this->groupPayload($this->cat), $this->actor);
    }

    public function test_enroll_charges_price_and_logs_transaction(): void
    {
        $student = $this->student($this->branch);

        app(EnrollmentService::class)->enroll($this->group, $student, 'Test', $this->actor);

        $this->assertSame(-500000, $student->fresh()->balance);
        $this->assertDatabaseHas('balance_transactions', ['student_id' => $student->id, 'type' => 'charge', 'amount' => -500000, 'balance_after' => -500000]);
        $this->assertSame(1, $this->group->activeMembers()->count());
    }

    /** v9: o'quvchi sahifasidagi "guruhga qo'shish" ro'yxati boshlanish sanasi emas, guruh nomi bo'yicha (A-Y) chiqadi. */
    public function test_available_groups_dropdown_is_sorted_by_name(): void
    {
        $studentUser = $this->student($this->branch);

        $teacher2 = $this->user(Role::Teacher, $this->branch, ['attendance.view', 'attendance.take']);
        $room2 = \App\Models\Room::create(['branch_id' => $this->branch->id, 'name' => '2-xona']);
        app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'Yakuniy guruh', 'teacher_id' => $teacher2->id, 'room_id' => $room2->id, 'starts_on' => '2026-10-01']), $this->actor);

        $teacher3 = $this->user(Role::Teacher, $this->branch, ['attendance.view', 'attendance.take']);
        $room3 = \App\Models\Room::create(['branch_id' => $this->branch->id, 'name' => '3-xona']);
        app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'Boshlangich guruh', 'teacher_id' => $teacher3->id, 'room_id' => $room3->id, 'starts_on' => '2026-09-25']), $this->actor);

        $response = $this->actingAs($this->actor)->get(route('students.show', $studentUser));

        $names = collect($response->viewData('availableGroups'))->pluck('name')->values()->all();

        $this->assertSame(['A1 GURUH', 'BOSHLANGICH GURUH', 'YAKUNIY GURUH'], $names);
    }

    public function test_early_payment_discount_is_given_only_when_prepaid_and_on_time(): void
    {
        $service = app(EnrollmentService::class);

        // Oldindan to'lagan (450 000 = narx - chegirma) va guruh boshlanishida
        $paid = $this->student($this->branch, ['balance' => 450000]);
        $service->enroll($this->group, $paid, null, $this->actor);
        $this->assertSame(-500000 + 450000 + 50000, $paid->fresh()->balance);
        $this->assertDatabaseHas('balance_transactions', ['student_id' => $paid->id, 'type' => 'discount', 'amount' => 50000]);

        // To'lamagan - chegirma yo'q
        $unpaid = $this->student($this->branch, ['balance' => 100000]);
        $service->enroll($this->group, $unpaid, null, $this->actor);
        $this->assertDatabaseMissing('balance_transactions', ['student_id' => $unpaid->id, 'type' => 'discount']);

        // Kech qolgan (boshlanishidan 3 kundan keyin) - chegirma yo'q
        Carbon::setTestNow('2026-09-25 10:00:00');
        $late = $this->student($this->branch, ['balance' => 600000]);
        $service->enroll($this->group, $late, null, $this->actor);
        $this->assertDatabaseMissing('balance_transactions', ['student_id' => $late->id, 'type' => 'discount']);
    }

    public function test_cannot_enroll_twice_or_archived_or_foreign_student(): void
    {
        $service = app(EnrollmentService::class);
        $student = $this->student($this->branch);
        $service->enroll($this->group, $student, null, $this->actor);

        try {
            $service->enroll($this->group, $student, null, $this->actor);
            $this->fail('Ikkinchi marta qo\'shildi');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('group_id', $e->errors());
        }
        $this->assertSame(-500000, $student->fresh()->balance);

        $archived = $this->student($this->branch, ['archived_at' => now()]);
        $this->expectException(ValidationException::class);
        $service->enroll($this->group, $archived, null, $this->actor);
    }

    public function test_foreign_branch_student_is_rejected(): void
    {
        $foreign = $this->student($this->branch('Boshqa'));

        $this->expectException(ValidationException::class);
        app(EnrollmentService::class)->enroll($this->group, $foreign, null, $this->actor);
    }

    public function test_remove_refunds_price_and_charges_fine(): void
    {
        $service = app(EnrollmentService::class);
        $student = $this->student($this->branch);
        $service->enroll($this->group, $student, null, $this->actor);

        $service->remove($this->group, $student, 100000, 'Kelmadi', $this->actor);

        $this->assertSame(-100000, $student->fresh()->balance); // -500000 + 500000 - 100000
        $this->assertDatabaseHas('balance_transactions', ['student_id' => $student->id, 'type' => 'refund', 'amount' => 500000]);
        $this->assertDatabaseHas('balance_transactions', ['student_id' => $student->id, 'type' => 'fine', 'amount' => -100000]);
        $this->assertSame(0, $this->group->activeMembers()->count());
        $this->assertDatabaseHas('group_students', ['student_id' => $student->id, 'is_active' => false, 'fine' => 100000]);
    }

    public function test_fine_cannot_exceed_group_price_and_inactive_student_cannot_be_removed(): void
    {
        $service = app(EnrollmentService::class);
        $student = $this->student($this->branch);
        $service->enroll($this->group, $student, null, $this->actor);

        try {
            $service->remove($this->group, $student, 600000, null, $this->actor);
            $this->fail('Katta jarima qabul qilindi');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fine', $e->errors());
        }

        $service->remove($this->group, $student, 0, null, $this->actor);
        $this->expectException(ValidationException::class);
        $service->remove($this->group, $student, 0, null, $this->actor);
    }

    public function test_student_can_rejoin_after_being_removed(): void
    {
        $service = app(EnrollmentService::class);
        $student = $this->student($this->branch);
        $service->enroll($this->group, $student, null, $this->actor);
        $service->remove($this->group, $student, 0, null, $this->actor);
        $service->enroll($this->group, $student, null, $this->actor);

        $this->assertSame(1, $this->group->activeMembers()->count());
        $this->assertSame(2, $this->group->members()->count());
    }

    public function test_continue_group_moves_only_students_without_debt(): void
    {
        $service = app(EnrollmentService::class);
        $a = $this->student($this->branch);
        $b = $this->student($this->branch);
        $service->enroll($this->group, $a, null, $this->actor);
        $service->enroll($this->group, $b, null, $this->actor);

        $payload = $this->groupPayload($this->cat, ['name' => 'a2', 'starts_on' => '2026-10-05']);

        // Ikkalasi ham qarzdor (balans -500 000) - o'tkazib bo'lmaydi
        try {
            app(GroupService::class)->continueGroup($this->group, $payload, [$a->id, $b->id], $this->actor);
            $this->fail('Qarzdor o\'quvchilar o\'tkazildi');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('students', $e->errors());
        }
        $this->assertSame(1, Group::count());   // yangi guruh yaratilmagan

        // A qarzini yopdi (500 000 to'ladi) - o'tadi, B qolmaydi
        app(\App\Services\PaymentService::class)->receive($a, ['cash' => 1000000], null, null, $this->actor);
        $new = app(GroupService::class)->continueGroup($this->group, $payload, [$a->id], $this->actor);

        $this->assertSame($new->id, $this->group->fresh()->next_group_id);
        $this->assertSame([$a->id], $new->activeMembers()->pluck('student_id')->all());
        // 1 000 000 to'ladi: eski guruh (chegirma muddati ochiq) va yangi guruh ikkalasi uchun narx − chegirma (2 × 450 000) qoplangan,
        // shuning uchun ikkala guruhdan 50 000 dan chegirma: -500 000 + 1 000 000 + 50 000 - 500 000 + 50 000
        $this->assertSame(100000, $a->fresh()->balance);
        $this->assertSame(-500000, $b->fresh()->balance);
    }

    /** v10 (2-band): groups.enroll_debtor ruxsati bor admin/sAdmin qarzi bor o'quvchini ham davom ettirishga o'tkaza oladi. */
    public function test_admin_with_enroll_debtor_permission_can_continue_debtor_into_new_group(): void
    {
        $admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members', 'groups.enroll_debtor']);
        $this->actingAs($admin);

        $service = app(EnrollmentService::class);
        $a = $this->student($this->branch);
        $service->enroll($this->group, $a, null, $admin);   // balans -500 000, qarzdor

        $payload = $this->groupPayload($this->cat, ['name' => 'a2', 'starts_on' => '2026-10-05']);
        $new = app(GroupService::class)->continueGroup($this->group, $payload, [$a->id], $admin);

        $this->assertSame($new->id, $this->group->fresh()->next_group_id);
        $this->assertSame([$a->id], $new->activeMembers()->pluck('student_id')->all());
        // Yangi guruh narxi (500 000) ham balansdan yechiladi: qarz yanada oshadi.
        $this->assertSame(-1000000, $a->fresh()->balance);
    }

    /** v10: qarzi bor o'quvchi ruxsatsiz admin uchun hamon rad etiladi, ruxsat bor bo'lsa boshqa (qarzsiz) o'quvchi normal davom etadi. */
    public function test_continue_group_debtor_exception_does_not_affect_non_debtors(): void
    {
        $admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members', 'groups.enroll_debtor']);
        $this->actingAs($admin);

        $service = app(EnrollmentService::class);
        $a = $this->student($this->branch);   // qarzdor bo'ladi
        $b = $this->student($this->branch);
        $service->enroll($this->group, $a, null, $admin);
        $service->enroll($this->group, $b, null, $admin);
        app(\App\Services\PaymentService::class)->receive($b, ['cash' => 1000000], null, null, $admin);   // b qarzini yopadi

        $payload = $this->groupPayload($this->cat, ['name' => 'a2', 'starts_on' => '2026-10-05']);
        $new = app(GroupService::class)->continueGroup($this->group, $payload, [$a->id, $b->id], $admin);

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $new->activeMembers()->pluck('student_id')->all());
    }

    public function test_student_with_debt_cannot_join_any_group(): void
    {
        $service = app(EnrollmentService::class);
        $student = $this->student($this->branch);
        $service->enroll($this->group, $student, null, $this->actor);   // balans -500 000

        $other = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'boshqa', 'starts_on' => '2026-11-02', 'room_id' => $this->cat['room']->id]), $this->actor);

        try {
            $service->enroll($other, $student, null, $this->actor);
            $this->fail('Qarzdor o\'quvchi qo\'shildi');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('qarzi bor', mb_strtolower($e->errors()['student_id'][0]));
        }
        $this->assertSame(-500000, $student->fresh()->balance);

        // Balans 0 bo'lsa qo'shiladi (500 000 to'lov birinchi guruhga 50 000 chegirma ham beradi)
        app(\App\Services\PaymentService::class)->receive($student, ['cash' => 500000], null, null, $this->actor);
        $this->assertSame(50000, $student->fresh()->balance);
        $service->enroll($other, $student, null, $this->actor);
        $this->assertSame(-450000, $student->fresh()->balance);
    }

    public function test_enrollment_discount_is_recorded_and_blocks_second_discount(): void
    {
        $student = $this->student($this->branch, ['balance' => 450000]);
        app(EnrollmentService::class)->enroll($this->group, $student, null, $this->actor);

        $this->assertDatabaseHas('payments', ['student_id' => $student->id, 'group_id' => $this->group->id, 'type' => 'discount', 'amount' => 50000]);
        $this->assertSame(0, $student->fresh()->balance);

        // Keyin shu guruh uchun to'lov qilsa, chegirma qayta berilmaydi
        app(\App\Services\PaymentService::class)->receive($student, ['cash' => 500000], $this->group, null, $this->actor);
        $this->assertSame(1, \App\Models\Payment::where('student_id', $student->id)->where('type', 'discount')->count());
        $this->assertSame(500000, $student->fresh()->balance);
    }

    public function test_discount_window_is_configurable_per_branch(): void
    {
        // Guruh 21.09 da boshlangan; bugun 21.09: standart oyna (30 kun oldin, 3 kun keyin) ichida
        $s1 = $this->student($this->branch, ['balance' => 450000]);
        app(EnrollmentService::class)->enroll($this->group, $s1, null, $this->actor);
        $this->assertSame(1, \App\Models\Payment::where('student_id', $s1->id)->where('type', 'discount')->count());

        // Keyin qo'shilish 0 kunga qisqartirilsa, 2 kundan keyin chegirma yo'q
        $this->branch->update(['discount_days_after' => 0]);
        Carbon::setTestNow('2026-09-23 10:00:00');
        $s2 = $this->student($this->branch, ['balance' => 450000]);
        app(EnrollmentService::class)->enroll($this->group, $s2, null, $this->actor);
        $this->assertSame(0, \App\Models\Payment::where('student_id', $s2->id)->where('type', 'discount')->count());

        // Uzaytirilsa, beriladi
        $this->branch->update(['discount_days_after' => 5]);
        $s3 = $this->student($this->branch, ['balance' => 450000]);
        app(EnrollmentService::class)->enroll($this->group, $s3, null, $this->actor);
        $this->assertSame(1, \App\Models\Payment::where('student_id', $s3->id)->where('type', 'discount')->count());

        // Boshlanishidan oldin: 30 kundan ko'p oldin chegirma yo'q, 30 kun ichida bor
        $this->branch->update(['discount_days_before' => 10]);
        $future = app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'kelajak', 'starts_on' => '2026-10-19', 'room_id' => $this->cat['room']->id]), $this->actor);   // 26 kun keyin
        $s4 = $this->student($this->branch, ['balance' => 450000]);
        app(EnrollmentService::class)->enroll($future, $s4, null, $this->actor);
        $this->assertSame(0, \App\Models\Payment::where('student_id', $s4->id)->where('type', 'discount')->count());

        $this->branch->update(['discount_days_before' => 30]);
        $s5 = $this->student($this->branch, ['balance' => 450000]);
        app(EnrollmentService::class)->enroll($future, $s5, null, $this->actor);
        $this->assertSame(1, \App\Models\Payment::where('student_id', $s5->id)->where('type', 'discount')->count());
    }

    public function test_web_add_and_remove_student_and_permissions(): void
    {
        $student = $this->student($this->branch);

        $this->post("/groups/{$this->group->id}/students", ['student_id' => $student->id, 'note' => 'Web'])->assertRedirect();
        $this->assertSame(-500000, $student->fresh()->balance);

        $manager = $this->user(Role::Manager, $this->branch, ['students.view', 'groups.view']);
        $this->actingAs($manager)->post("/groups/{$this->group->id}/students", ['student_id' => $this->student($this->branch)->id])->assertForbidden();

        $this->actingAs($this->actor)->delete("/groups/{$this->group->id}/students/{$student->id}", ['fine' => 0])->assertRedirect();
        $this->assertSame(0, $student->fresh()->balance);
    }

    public function test_student_archive_requires_no_active_groups(): void
    {
        $student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($this->group, $student, null, $this->actor);

        $this->post("/students/{$student->id}/archive")->assertSessionHasErrors('student');
        $this->assertNull($student->fresh()->archived_at);

        app(EnrollmentService::class)->remove($this->group, $student, 0, null, $this->actor);
        $this->post("/students/{$student->id}/archive")->assertRedirect();
        $this->assertNotNull($student->fresh()->archived_at);
    }
}
