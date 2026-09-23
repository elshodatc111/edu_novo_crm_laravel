<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\PaymentService;
use App\Services\StudentTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** V7: o'quvchining balans harakatlari va tarixi bitta jadvalda; to'lov naqt/plastikligi ko'rinadi. */
class V7StudentTimelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_table_shows_payment_method_and_events(): void
    {
        Carbon::setTestNow('2026-09-21 10:00:00');
        $branch = $this->branch('Toshkent');
        $cat = $this->catalog($branch);
        $admin = $this->user(Role::Admin, $branch, ['groups.create', 'groups.members', 'students.view', 'payments.create', 'payments.discount', 'payments.refund']);
        $this->actingAs($admin);

        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $s = $this->student($branch, ['name' => 'Karim Test']);
        app(EnrollmentService::class)->enroll($group, $s, null, $admin);
        app(PaymentService::class)->receive($s, ['cash' => 400000, 'card' => 200000], $group, null, $admin);
        app(PaymentService::class)->refund($s->fresh(), \App\Enums\PayMethod::Card, 20000, 'Xato to\'lov', $admin);
        \App\Models\AuditLog::record('student.updated', $s, "Ma'lumotlari yangilandi");

        $rows = app(StudentTimeline::class)->for($s->fresh());

        $payments = $rows->where('type', 'payment')->values();
        $this->assertCount(2, $payments);
        $this->assertEqualsCanonicalizing(['Naqt', 'Plastik'], $payments->pluck('method')->all());
        $this->assertSame(400000, $payments->firstWhere('method', 'Naqt')['amount']);

        $refund = $rows->firstWhere('type', 'payment_refund');
        $this->assertSame('Plastik', $refund['method']);
        $this->assertSame(-20000, $refund['amount']);

        // Guruh narxi, guruhga qo'shilish hodisasi va boshqa hodisa — bitta ro'yxatda; pulli audit yozuvlari takrorlanmaydi
        $this->assertNotNull($rows->firstWhere('type', 'charge'));
        $this->assertNotNull($rows->firstWhere('type', 'student.group_added'));
        $this->assertNotNull($rows->firstWhere('type', 'student.updated'));
        $this->assertNull($rows->firstWhere('type', 'payment.received'));
        $this->assertNull($rows->firstWhere('type', 'payment.refund'));

        // Yangidan eskiga
        $ids = $rows->pluck('at')->map->getTimestamp()->all();
        $sorted = $ids;
        rsort($sorted);
        $this->assertSame($sorted, $ids);

        $html = $this->get("/students/{$s->id}")->assertOk()->assertSee('Balans harakatlari va tarix')->assertSee("To'lov usuli", false)
            ->assertSee('Plastik')->assertSee('Naqt')->getContent();
        $this->assertStringNotContainsString('>Tarix</h2>', $html);        // alohida "Tarix" bloki yo'q
        $this->assertSame(1, substr_count($html, "To'lov usuli"), 'Bitta jadval');
    }
}
