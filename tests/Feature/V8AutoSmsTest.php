<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\SmsMessage;
use App\Services\AttendanceService;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** v8 B1: avtomatik SMS — qarz eslatmasi (kunlik buyruq) va darsga kelmaganlik xabari (davomaddan keyin, ota-ona raqamiga). */
class V8AutoSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');

        config(['services.eskiz.email' => 'global@eskiz.uz', 'services.eskiz.password' => 'secret', 'services.eskiz.from' => '4546']);
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'TOKEN-1']]),
            '*/message/sms/send' => Http::response(['status' => 'waiting', 'id' => 'x']),
        ]);
    }

    /**
     * Haqiqiy forma kabi - filialning HOZIRGI bayroqlarini ham shu so'rovda qayta yuboradi,
     * aks holda forma faqat $key shablonini o'z ichiga olib, tekshirilmayotgan checkboxlarni
     * (masalan sms_auto_debt) beixtiyor "belgilanmagan" holatga qaytarib qo'yardi.
     */
    private function enableTemplate($branch, string $key): void
    {
        $fresh = $branch->fresh();
        $this->put('/sms/settings', [
            'sms_enabled' => 1,
            'sms_auto_debt' => $fresh->sms_auto_debt ? 1 : 0,
            'sms_auto_absent' => $fresh->sms_auto_absent ? 1 : 0,
            'templates' => [$key => ['enabled' => 1, 'body' => \App\Support\SmsTemplates::all()[$key]['body']]],
        ])->assertSessionHasNoErrors();
    }

    // ---- Qarz eslatmasi (sms:debts) ----

    public function test_debt_reminder_sends_only_when_enabled_and_auto_flag_on(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['sms.manage']);
        $this->actingAs($admin);
        $debtor = $this->student($branch, ['name' => 'Qarzdor', 'phone' => '+998 90 111 1111', 'balance' => -75000]);
        $this->student($branch, ['name' => 'Toza', 'phone' => '+998 90 222 2222', 'balance' => 10000]);

        // sms_enabled o'chiq bo'lsa — auto_debt yoqilgan bo'lsa ham yuborilmaydi
        $branch->update(['sms_auto_debt' => true]);
        $this->artisan('sms:debts')->assertSuccessful();
        $this->assertSame(0, SmsMessage::where('template_key', 'debt_reminder')->count());

        // sms_enabled yoqiladi, lekin auto_debt hali o'chiq va shablon o'chiq
        $branch->update(['sms_enabled' => true, 'sms_auto_debt' => false]);
        $this->artisan('sms:debts')->assertSuccessful();
        $this->assertSame(0, SmsMessage::where('template_key', 'debt_reminder')->count());

        // Ikkalasi ham yoqiladi, lekin shablonning o'zi hali o'chiq (standart holat) -> baribir yuborilmaydi
        $branch->update(['sms_auto_debt' => true]);
        $this->artisan('sms:debts')->assertSuccessful();
        $this->assertSame(0, SmsMessage::where('template_key', 'debt_reminder')->count());

        // Shablon ham yoqilsa — endi yuboriladi, faqat qarzdorga
        $this->enableTemplate($branch, 'debt_reminder');
        $this->artisan('sms:debts')->assertSuccessful();
        $sent = SmsMessage::where('template_key', 'debt_reminder')->get();
        $this->assertCount(1, $sent);
        $this->assertSame($debtor->id, $sent[0]->recipient_id);
        $this->assertStringContainsString("75 000 so'm", $sent[0]->message);
    }

    public function test_debt_reminder_is_deduplicated_per_day(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['sms.manage']);
        $this->actingAs($admin);
        $this->student($branch, ['phone' => '+998 90 111 1111', 'balance' => -1000]);
        $branch->update(['sms_auto_debt' => true]);
        $this->enableTemplate($branch, 'debt_reminder');

        $this->artisan('sms:debts')->assertSuccessful();
        $this->artisan('sms:debts')->assertSuccessful();
        $this->assertSame(1, SmsMessage::where('template_key', 'debt_reminder')->count());

        Carbon::setTestNow('2026-09-22 09:15:00');
        $this->artisan('sms:debts')->assertSuccessful();
        $this->assertSame(2, SmsMessage::where('template_key', 'debt_reminder')->count());
    }

    public function test_debt_reminder_ignores_other_branches_and_non_debtors(): void
    {
        $branch = $this->branch('Bu');
        $other = $this->branch('Boshqa');
        $admin = $this->user(Role::Admin, $branch, ['sms.manage']);
        $this->actingAs($admin);
        $branch->update(['sms_auto_debt' => true]);
        $other->update(['sms_enabled' => true, 'sms_auto_debt' => true]);
        $this->enableTemplate($branch, 'debt_reminder');

        $this->student($branch, ['phone' => '+998 90 111 1111', 'balance' => 0]);
        $this->student($other, ['phone' => '+998 90 333 3333', 'balance' => -5000]);

        $this->artisan('sms:debts')->assertSuccessful();

        $this->assertSame(0, SmsMessage::where('template_key', 'debt_reminder')->count());
    }

    // ---- Darsga kelmaganlik xabari (davomad olinganda) ----

    public function test_absence_notice_sent_to_parent_phone_not_student_phone(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['sms.manage', 'groups.create']);
        $this->actingAs($admin);
        $branch->update(['sms_auto_absent' => true]);
        $this->enableTemplate($branch, 'absence_notice');

        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $present = $this->student($branch, ['name' => 'Keldi', 'phone' => '+998 90 111 1111']);
        $absent = $this->student($branch, ['name' => 'Kelmadi', 'phone' => '+998 90 222 2222', 'phone2' => '+998 90 999 8888']);
        app(EnrollmentService::class)->enroll($group, $present, null, $admin);
        app(EnrollmentService::class)->enroll($group, $absent, null, $admin);

        app(AttendanceService::class)->takeToday($group, [$present->id], $cat['teacher']);

        $sent = SmsMessage::where('template_key', 'absence_notice')->get();
        $this->assertCount(1, $sent);
        $this->assertSame($absent->id, $sent[0]->recipient_id);
        $this->assertSame('998909998888', $sent[0]->phone);           // phone2ga, o'ziga emas
        $this->assertStringContainsString($group->name, $sent[0]->message);
        $this->assertStringContainsString('Kelmadi', $sent[0]->message);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/message/sms/send') && $r['mobile_phone'] === '998909998888');
    }

    public function test_absence_notice_skips_students_without_parent_phone(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['sms.manage', 'groups.create']);
        $this->actingAs($admin);
        $branch->update(['sms_auto_absent' => true]);
        $this->enableTemplate($branch, 'absence_notice');

        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $absent = $this->student($branch, ['phone' => '+998 90 222 2222', 'phone2' => null]);
        app(EnrollmentService::class)->enroll($group, $absent, null, $admin);

        app(AttendanceService::class)->takeToday($group, [], $cat['teacher']);

        $this->assertSame(0, SmsMessage::where('template_key', 'absence_notice')->count());
    }

    public function test_absence_notice_requires_auto_flag_and_template_enabled(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['sms.manage', 'groups.create']);
        $this->actingAs($admin);
        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $absent = $this->student($branch, ['phone' => '+998 90 222 2222', 'phone2' => '+998 90 999 8888']);
        app(EnrollmentService::class)->enroll($group, $absent, null, $admin);

        // sms_auto_absent o'chiq (standart) -> yuborilmaydi
        app(AttendanceService::class)->takeToday($group, [], $cat['teacher']);
        $this->assertSame(0, SmsMessage::where('template_key', 'absence_notice')->count());

        // Bayroq yoqiladi, lekin shablon hali o'chiq (standart) -> baribir yuborilmaydi
        $branch->update(['sms_enabled' => true, 'sms_auto_absent' => true]);
        app(AttendanceService::class)->takeToday($group, [], $cat['teacher']);
        $this->assertSame(0, SmsMessage::where('template_key', 'absence_notice')->count());
    }

    public function test_absence_notice_deduplicated_per_day_when_attendance_resaved(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['sms.manage', 'groups.create']);
        $this->actingAs($admin);
        $branch->update(['sms_auto_absent' => true]);
        $this->enableTemplate($branch, 'absence_notice');

        $cat = $this->catalog($branch);
        $group = app(GroupService::class)->create($this->groupPayload($cat), $admin);
        $absent = $this->student($branch, ['phone' => '+998 90 222 2222', 'phone2' => '+998 90 999 8888']);
        app(EnrollmentService::class)->enroll($group, $absent, null, $admin);

        app(AttendanceService::class)->takeToday($group, [], $cat['teacher']);
        app(AttendanceService::class)->takeToday($group, [], $cat['teacher']); // o'sha kuni qayta saqlash

        $this->assertSame(1, SmsMessage::where('template_key', 'absence_notice')->count());
    }

    // ---- Sozlamalar sahifasi ----

    public function test_settings_page_persists_auto_flags_with_permission(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['sms.manage']);

        $this->actingAs($admin)->put('/sms/settings', [
            'sms_enabled' => 1, 'sms_auto_debt' => 1, 'sms_auto_absent' => 1,
            'templates' => ['debt_reminder' => ['enabled' => 1, 'body' => 'Qarz: {debt}']],
        ])->assertRedirect();

        $this->assertTrue($branch->fresh()->sms_auto_debt);
        $this->assertTrue($branch->fresh()->sms_auto_absent);

        // Belgi olib tashlansa - o'chadi (checkboxlar yuborilmasa false bo'lishi kerak;
        // "templates" formada har doim to'liq keladi, shuning uchun bo'sh massiv emas)
        $this->actingAs($admin)->put('/sms/settings', [
            'sms_enabled' => 1, 'templates' => ['debt_reminder' => ['enabled' => 0, 'body' => 'Qarz: {debt}']],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($branch->fresh()->sms_auto_debt);
        $this->assertFalse($branch->fresh()->sms_auto_absent);
    }

    public function test_settings_page_shows_new_checkboxes_to_manager_with_sms_manage(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['sms.manage']);
        $branch->update(['sms_auto_debt' => true]);

        $this->actingAs($manager)->get('/sms')->assertOk()
            ->assertSee('sms_auto_debt', false)
            ->assertSee('sms_auto_absent', false);
    }
}
