<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** v8 A5: parol o'zgargan sanani kuzatish, 30 kunlik yumshoq eslatma va parol o'zgarganda tokenlarni bekor qilish. */
class V8PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
    }

    public function test_password_changed_at_tracks_only_password_changes(): void
    {
        $user = $this->user(Role::Admin);
        $firstStamp = $user->password_changed_at;

        $this->assertNotNull($firstStamp);
        $this->assertTrue($firstStamp->equalTo(now()));

        Carbon::setTestNow(now()->addDay());
        $user->update(['name' => 'Boshqa ism']);
        $this->assertTrue($user->fresh()->password_changed_at->equalTo($firstStamp));   // parolga tegilmadi

        Carbon::setTestNow(now()->addDay());
        $user->update(['password' => 'yangi-parol-1']);
        $this->assertTrue($user->fresh()->password_changed_at->equalTo(now()));         // parol o'zgardi
        $this->assertFalse($user->fresh()->password_changed_at->equalTo($firstStamp));
    }

    public function test_needs_password_reminder_after_30_days(): void
    {
        $user = $this->user(Role::Admin);

        $this->assertFalse($user->fresh()->needsPasswordReminder());

        Carbon::setTestNow(now()->addDays(10));
        $this->assertFalse($user->fresh()->needsPasswordReminder());

        Carbon::setTestNow(now()->addDays(21));   // jami 31 kun
        $this->assertTrue($user->fresh()->needsPasswordReminder());
    }

    public function test_dashboard_shows_soft_reminder_only_when_overdue(): void
    {
        $fresh = $this->user(Role::Admin, permissions: []);
        $this->actingAs($fresh)->get('/')->assertDontSee("kundan beri o'zgarmagan", false);

        $overdue = $this->user(Role::Admin, permissions: []);
        $overdue->forceFill(['password_changed_at' => now()->subDays(45)])->save();

        $this->actingAs($overdue)->get('/')->assertSee("45 kundan beri o'zgarmagan", false);
    }

    public function test_changing_own_password_revokes_api_tokens(): void
    {
        $admin = $this->user(Role::Admin, permissions: []);
        $admin->createToken('eski-qurilma');
        $this->assertSame(1, $admin->tokens()->count());

        $this->actingAs($admin)->put('/profile/password', [
            'current_password' => 'parol12345',
            'password' => 'yangi-xavfsiz-parol',
            'password_confirmation' => 'yangi-xavfsiz-parol',
        ])->assertRedirect();

        $this->assertSame(0, $admin->tokens()->count());
        $this->assertTrue($admin->fresh()->password_changed_at->equalTo(now()));
    }
}
