<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** v8 A5: xavfsizlik sarlavhalari, API chegarasi va ishonchli proksi (trustProxies) testlari. */
class V8SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_present_on_normal_pages(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $response = $this->actingAs($sadmin)->get('/');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeaderMissing('Strict-Transport-Security');   // http'da hali HSTS yo'q
    }

    public function test_hsts_header_only_appears_over_https(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $response = $this->actingAs($sadmin)->get('https://edunova.test/');

        $response->assertHeader('Strict-Transport-Security', 'max-age=15552000; includeSubDomains');
    }

    public function test_apply_embed_page_is_not_blocked_from_framing(): void
    {
        $branch = $this->branch('Andijon');

        $response = $this->get('/apply/'.$branch->code);

        $response->assertOk();
        $response->assertHeaderMissing('X-Frame-Options');
        // Boshqa sarlavhalar hamon qo'yiladi - faqat freymlashni bloklovchisi olib tashlanadi
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_api_requests_are_throttled(): void
    {
        // Haqiqiy (120/daqiqa) chegara bilan sinov sekin bo'lardi - shu test uchun pastroq chegara qo'yamiz.
        RateLimiter::for('api', fn () => Limit::perMinute(3));

        $student = $this->student($this->branch('Namangan'));
        $token = $student->createToken('test')->plainTextToken;

        for ($i = 0; $i < 3; $i++) {
            $this->api($token)->getJson('/api/v1/auth/me')->assertOk();
        }

        $this->api($token)->getJson('/api/v1/auth/me')->assertStatus(429);
    }

    public function test_forwarded_ip_is_trusted_only_from_local_proxy(): void
    {
        $admin = $this->user(Role::Admin, permissions: []);

        // "Haqiqiy" so'rov mahalliy proksidan (127.0.0.1) keladi va haqiqiy mijoz IP'sini bildiradi -
        // trustProxies shuni tan olishi kerak.
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->post('/login', ['login' => $admin->username, 'password' => 'parol12345'], ['X-Forwarded-For' => '203.0.113.9'])
            ->assertRedirect(route('dashboard'));

        $trusted = AuditLog::where('action', 'auth.login')->latest('id')->first();
        $this->assertSame('203.0.113.9', $trusted->ip_address);

        $this->post('/logout');

        // Endi "so'rov" boshqa (ishonchsiz) manzildan keladi - X-Forwarded-For e'tiborga olinmasligi kerak,
        // aks holda tashqi hujumchi bu sarlavha bilan haqiqiy IP manzilini yashirib olardi.
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.5'])
            ->post('/login', ['login' => $admin->username, 'password' => 'parol12345'], ['X-Forwarded-For' => '203.0.113.9'])
            ->assertRedirect(route('dashboard'));

        $untrusted = AuditLog::where('action', 'auth.login')->latest('id')->first();
        $this->assertSame('198.51.100.5', $untrusted->ip_address);
    }
}
