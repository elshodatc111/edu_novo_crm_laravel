<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** v8 B9: ommaviy SMS yuborishdan oldin ko'rish (oldindan) va ikki bosqichli tasdiqlash. */
class V8SmsBulkConfirmTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.eskiz.email' => 'global@eskiz.uz', 'services.eskiz.password' => 'secret', 'services.eskiz.from' => '4546']);
        Http::fake([
            '*/auth/login' => Http::response(['data' => ['token' => 'TOKEN-1']]),
            '*/message/sms/send' => Http::response(['status' => 'waiting']),
        ]);

        $this->branch = $this->branch('Toshkent');
        $this->branch->update(['sms_enabled' => true]);
        $this->admin = $this->user(Role::Admin, $this->branch, ['sms.view', 'sms.send', 'sms.manage']);
        $this->actingAs($this->admin);
    }

    private function confirmUrl($response): string
    {
        $response->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertStringContainsString('/confirm/', $url);

        return $url;
    }

    public function test_preview_shows_recipient_count_without_sending_anything(): void
    {
        $this->student($this->branch, ['name' => 'Aziza Karimova', 'phone' => '+998 90 111 1111', 'balance' => -75000]);
        $this->student($this->branch, ['name' => 'Bekzod Yusupov', 'phone' => '+998 90 222 2222', 'balance' => -30000]);

        $resp = $this->post('/sms/bulk', ['audience' => 'debtors', 'message' => "Hurmatli {name}, qarzingiz {debt}"]);
        $url = $this->confirmUrl($resp);

        $this->get($url)->assertOk()->assertSee('Ommaviy SMS yuborish')->assertSee('2 ta');
        $this->assertSame(0, SmsMessage::count());
        Http::assertNothingSent();
    }

    public function test_preview_sample_text_is_rendered_with_real_recipient_data(): void
    {
        $this->student($this->branch, ['name' => 'Aziza Karimova', 'phone' => '+998 90 111 1111', 'balance' => -75000]);

        $resp = $this->post('/sms/bulk', ['audience' => 'debtors', 'message' => "Hurmatli {name}, qarzingiz {debt}"]);
        $url = $this->confirmUrl($resp);

        $this->get($url)->assertOk()->assertSee('Aziza Karimova')->assertSee('75 000');
    }

    public function test_confirming_queues_the_previewed_messages(): void
    {
        $this->student($this->branch, ['name' => 'Aziza', 'phone' => '+998 90 111 1111', 'balance' => -75000]);
        $this->student($this->branch, ['name' => 'Bekzod', 'phone' => '+998 90 222 2222', 'balance' => -30000]);

        $resp = $this->post('/sms/bulk', ['audience' => 'debtors', 'message' => 'Qarz: {debt}']);
        $url = $this->confirmUrl($resp);

        $this->post($url)->assertRedirect('/sms')->assertSessionHas('success', "2 ta SMS navbatga qo'yildi.");
        $this->assertSame(2, SmsMessage::count());
    }

    public function test_not_confirming_sends_nothing(): void
    {
        $this->student($this->branch, ['phone' => '+998 90 111 1111', 'balance' => -1000]);

        $resp = $this->post('/sms/bulk', ['audience' => 'debtors', 'message' => 'Salom']);
        $this->confirmUrl($resp);   // faqat "Tekshiring" sahifasiga o'tadi, hali tasdiqlanmadi

        $this->assertSame(0, SmsMessage::count());
        Http::assertNothingSent();
    }

    public function test_confirm_rechecks_sms_enabled_at_send_time(): void
    {
        $this->student($this->branch, ['phone' => '+998 90 111 1111', 'balance' => -1000]);

        $resp = $this->post('/sms/bulk', ['audience' => 'debtors', 'message' => 'Salom']);
        $url = $this->confirmUrl($resp);

        // Ko'rib chiqish bilan tasdiqlash orasida sozlamada SMS o'chirildi.
        $this->branch->update(['sms_enabled' => false]);

        $this->post($url)->assertRedirect('/sms')->assertSessionHasErrors('message');
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_confirm_uses_the_live_recipient_list_not_the_stale_preview(): void
    {
        $debtor1 = $this->student($this->branch, ['name' => 'Birinchi', 'phone' => '+998 90 111 1111', 'balance' => -50000]);
        $this->student($this->branch, ['name' => 'Ikkinchi', 'phone' => '+998 90 222 2222', 'balance' => -60000]);

        $resp = $this->post('/sms/bulk', ['audience' => 'debtors', 'message' => 'Qarz: {debt}']);
        $url = $this->confirmUrl($resp);

        // Tasdiqlashdan oldin bittasi to'lov qilib qarzni yopdi - u endi "qarzdor" emas.
        $debtor1->update(['balance' => 0]);

        $this->post($url)->assertRedirect()->assertSessionHas('success', "1 ta SMS navbatga qo'yildi.");
        $this->assertSame(1, SmsMessage::count());
        $this->assertSame('998902222222', SmsMessage::first()->phone);
    }

    public function test_confirm_shows_error_when_no_recipients_remain(): void
    {
        $debtor = $this->student($this->branch, ['phone' => '+998 90 111 1111', 'balance' => -50000]);

        $resp = $this->post('/sms/bulk', ['audience' => 'debtors', 'message' => 'Salom']);
        $url = $this->confirmUrl($resp);

        $debtor->update(['balance' => 0]);

        $this->post($url)->assertRedirect('/sms')->assertSessionHasErrors('message');
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_preview_requires_sms_send_permission(): void
    {
        $viewer = $this->user(Role::Manager, $this->branch, ['sms.view']);
        $this->student($this->branch, ['phone' => '+998 90 111 1111', 'balance' => -1000]);

        $this->actingAs($viewer)->post('/sms/bulk', ['audience' => 'debtors', 'message' => 'Salom'])->assertForbidden();
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_confirm_step_rechecks_permission(): void
    {
        $this->student($this->branch, ['phone' => '+998 90 111 1111', 'balance' => -1000]);

        $resp = $this->post('/sms/bulk', ['audience' => 'debtors', 'message' => 'Salom']);
        $url = $this->confirmUrl($resp);

        // Ko'rib chiqishdan keyin, tasdiqlashdan oldin ruxsat olib tashlandi.
        $this->admin->permissions()->where('permission', 'sms.send')->delete();

        $this->actingAs($this->admin->fresh())->post($url)->assertForbidden();
        $this->assertSame(0, SmsMessage::count());
    }
}
