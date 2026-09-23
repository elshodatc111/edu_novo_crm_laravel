<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\PasswordResetCode;
use App\Models\SmsMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** v12: mobil ilovada "Parolni unutdingizmi" - SMS OTP orqali parolni tiklash. */
class V12ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function extractCode(): string
    {
        $sms = SmsMessage::where('template_key', 'password_reset_otp')->firstOrFail();
        preg_match('/\d{6}/', (string) $sms->secret, $m);

        return $m[0] ?? '';
    }

    public function test_forgot_password_sends_sms_and_reset_succeeds(): void
    {
        Queue::fake();

        $user = $this->user(Role::Teacher, attrs: ['phone' => '+998901234567']);

        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->username])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame(1, PasswordResetCode::where('user_id', $user->id)->whereNull('used_at')->count());
        $sms = SmsMessage::where('template_key', 'password_reset_otp')->firstOrFail();
        $this->assertSame('998901234567', $sms->phone);
        $this->assertStringNotContainsString('••••••', $sms->secret ?? ''); // haqiqiy matnda kod ochiq
        $this->assertStringContainsString('••••••', $sms->message);        // tarixda kod berkitilgan

        $code = $this->extractCode();

        $this->postJson('/api/v1/auth/reset-password', [
            'login' => $user->username, 'code' => $code,
            'password' => 'yangiParol99', 'password_confirmation' => 'yangiParol99',
        ])->assertOk()->assertJsonPath('success', true);

        $this->postJson('/api/v1/auth/login', ['login' => $user->username, 'password' => 'yangiParol99'])->assertOk();

        // Kod ishlatilgach, qayta ishlatib bo'lmaydi
        $this->postJson('/api/v1/auth/reset-password', [
            'login' => $user->username, 'code' => $code,
            'password' => 'boshqaParol1', 'password_confirmation' => 'boshqaParol1',
        ])->assertStatus(422);
    }

    public function test_reset_password_revokes_existing_tokens(): void
    {
        Queue::fake();

        $user = $this->user(Role::Teacher, attrs: ['phone' => '+998901234567']);
        $oldToken = $user->createToken('eski-qurilma')->plainTextToken;

        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->username])->assertOk();
        $code = $this->extractCode();

        $this->postJson('/api/v1/auth/reset-password', [
            'login' => $user->username, 'code' => $code,
            'password' => 'yangiParol99', 'password_confirmation' => 'yangiParol99',
        ])->assertOk();

        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_forgot_password_is_silent_for_unknown_login(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['login' => 'yoq_bunday_user'])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertSame(0, SmsMessage::count());
        $this->assertSame(0, PasswordResetCode::count());
    }

    public function test_forgot_password_skipped_for_sadmin_and_users_without_phone(): void
    {
        Queue::fake();

        $sadmin = $this->user(Role::SAdmin);
        $this->postJson('/api/v1/auth/forgot-password', ['login' => $sadmin->username])->assertOk();
        $this->assertSame(0, SmsMessage::count());

        $noPhone = $this->user(Role::Manager);
        $this->postJson('/api/v1/auth/forgot-password', ['login' => $noPhone->username])->assertOk();
        $this->assertSame(0, SmsMessage::count());
    }

    public function test_forgot_password_skipped_for_blocked_user(): void
    {
        Queue::fake();

        $user = $this->user(Role::Teacher, attrs: ['phone' => '+998901234567', 'status' => UserStatus::Blocked]);
        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->username])->assertOk();

        $this->assertSame(0, SmsMessage::count());
    }

    public function test_wrong_code_is_rejected_and_locked_after_max_attempts(): void
    {
        Queue::fake();

        $user = $this->user(Role::Teacher, attrs: ['phone' => '+998901234567']);
        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->username])->assertOk();
        $realCode = $this->extractCode();
        $wrongCode = $realCode === '111111' ? '222222' : '111111';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/reset-password', [
                'login' => $user->username, 'code' => $wrongCode,
                'password' => 'yangiParol99', 'password_confirmation' => 'yangiParol99',
            ])->assertStatus(422);
        }

        // 5 marta xato urinishdan keyin, HATTO to'g'ri kod bilan ham kod bekor hisoblanadi
        $this->postJson('/api/v1/auth/reset-password', [
            'login' => $user->username, 'code' => $realCode,
            'password' => 'yangiParol99', 'password_confirmation' => 'yangiParol99',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', ['login' => $user->username, 'password' => 'yangiParol99'])->assertStatus(422);
    }

    public function test_expired_code_is_rejected(): void
    {
        Queue::fake();

        $user = $this->user(Role::Teacher, attrs: ['phone' => '+998901234567']);
        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->username])->assertOk();
        $code = $this->extractCode();

        PasswordResetCode::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/auth/reset-password', [
            'login' => $user->username, 'code' => $code,
            'password' => 'yangiParol99', 'password_confirmation' => 'yangiParol99',
        ])->assertStatus(422);
    }

    public function test_new_request_invalidates_previous_code(): void
    {
        Queue::fake();

        $user = $this->user(Role::Teacher, attrs: ['phone' => '+998901234567']);

        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->username])->assertOk();
        $firstCode = $this->extractCode();

        SmsMessage::query()->delete();
        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->username])->assertOk();
        $secondCode = $this->extractCode();

        // Eski kod endi ishlamaydi (yangi so'rov bekor qildi)
        $this->postJson('/api/v1/auth/reset-password', [
            'login' => $user->username, 'code' => $firstCode,
            'password' => 'yangiParol99', 'password_confirmation' => 'yangiParol99',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/reset-password', [
            'login' => $user->username, 'code' => $secondCode,
            'password' => 'yangiParol99', 'password_confirmation' => 'yangiParol99',
        ])->assertOk();
    }

    public function test_forgot_password_route_is_throttled(): void
    {
        $user = $this->user(Role::Teacher, attrs: ['phone' => '+998901234567']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->username]);
        }

        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->username])->assertStatus(429);
    }
}
