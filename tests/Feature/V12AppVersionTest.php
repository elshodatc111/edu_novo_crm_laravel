<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v12: mobil ilova versiyasini tekshirish (majburiy/yumshoq yangilash) va sAdmin sozlamalari. */
class V12AppVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_rows_exist_after_migration_and_do_not_force_update(): void
    {
        $this->getJson('/api/v1/app/version?platform=android&version=1.0.0')
            ->assertOk()
            ->assertJsonPath('data.force_update', false)
            ->assertJsonPath('data.update_available', false)
            ->assertJsonPath('data.min_version', '1.0.0')
            ->assertJsonPath('data.latest_version', '1.0.0');
    }

    public function test_route_does_not_require_authentication(): void
    {
        $this->getJson('/api/v1/app/version?platform=ios&version=1.0.0')->assertOk();
    }

    public function test_old_version_below_minimum_is_forced_to_update(): void
    {
        AppVersion::where('platform', 'android')->update(['min_version' => '2.0.0', 'latest_version' => '2.3.0']);

        $this->getJson('/api/v1/app/version?platform=android&version=1.5.0')
            ->assertOk()
            ->assertJsonPath('data.force_update', true)
            ->assertJsonPath('data.update_available', true);
    }

    public function test_version_between_min_and_latest_is_soft_nudge_only(): void
    {
        AppVersion::where('platform', 'android')->update(['min_version' => '1.0.0', 'latest_version' => '2.3.0']);

        $this->getJson('/api/v1/app/version?platform=android&version=2.0.0')
            ->assertOk()
            ->assertJsonPath('data.force_update', false)
            ->assertJsonPath('data.update_available', true);
    }

    public function test_invalid_platform_or_version_is_rejected(): void
    {
        $this->getJson('/api/v1/app/version?platform=windows&version=1.0.0')->assertStatus(422);
        $this->getJson('/api/v1/app/version?platform=android&version=abc')->assertStatus(422);
    }

    public function test_sadmin_can_update_platform_version_settings(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->put(route('app-version.update'), [
            'platform' => 'ios', 'min_version' => '1.2.0', 'latest_version' => '1.5.0',
            'update_url' => 'https://apps.apple.com/app/x', 'message' => "Yangi funksiyalar qo'shildi.",
        ])->assertRedirect();

        $row = AppVersion::where('platform', 'ios')->firstOrFail();
        $this->assertSame('1.2.0', $row->min_version);
        $this->assertSame('1.5.0', $row->latest_version);
        $this->assertSame($sadmin->id, $row->updated_by);

        $this->getJson('/api/v1/app/version?platform=ios&version=1.1.0')
            ->assertJsonPath('data.force_update', true)
            ->assertJsonPath('data.message', "Yangi funksiyalar qo'shildi.")
            ->assertJsonPath('data.update_url', 'https://apps.apple.com/app/x');
    }

    public function test_min_version_cannot_exceed_latest_version(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->put(route('app-version.update'), [
            'platform' => 'android', 'min_version' => '3.0.0', 'latest_version' => '2.0.0',
        ])->assertSessionHasErrors('min_version');
    }

    public function test_non_sadmin_cannot_access_app_version_settings(): void
    {
        $admin = $this->user(Role::Admin);

        $this->actingAs($admin)->get(route('app-version.edit'))->assertForbidden();
        $this->actingAs($admin)->put(route('app-version.update'), [
            'platform' => 'android', 'min_version' => '1.0.0', 'latest_version' => '1.0.0',
        ])->assertForbidden();
    }
}
