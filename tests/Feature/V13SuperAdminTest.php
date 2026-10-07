<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: bir nechta sAdmin - qo'shish, bloklash, olib tashlash va himoyalar. */
class V13SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $over = []): array
    {
        return ['name' => 'Ikkinchi Admin', 'username' => 'sa.ikki', 'phone' => '+998 90 333 4455', 'password' => 'parol12345', 'password_confirmation' => 'parol12345', ...$over];
    }

    public function test_only_sadmin_can_open_and_use_the_section(): void
    {
        $admin = $this->user(Role::Admin, $this->branch(), ['staff.manage']);

        $this->actingAs($admin)->get('/superadmins')->assertForbidden();
        $this->actingAs($admin)->post('/superadmins', $this->payload())->assertForbidden();
        $this->assertSame(0, User::where('username', 'sa.ikki')->count());
    }

    public function test_sadmin_adds_another_sadmin_who_can_login_and_sees_all_branches(): void
    {
        $sa = $this->user(Role::SAdmin);
        $this->branch('A');

        $this->actingAs($sa)->post('/superadmins', $this->payload())->assertRedirect(route('superadmins.index'));

        $new = User::where('username', 'sa.ikki')->firstOrFail();
        $this->assertSame(Role::SAdmin, $new->role);
        $this->assertNull($new->branch_id);
        $this->assertTrue($new->isActive());

        $this->actingAs($new)->get('/superadmins')->assertOk()->assertSee('Ikkinchi Admin');
        $this->actingAs($new)->get('/branches')->assertOk();
    }

    public function test_duplicate_username_and_weak_data_are_rejected(): void
    {
        $sa = $this->user(Role::SAdmin);

        $this->actingAs($sa)->post('/superadmins', $this->payload(['username' => $sa->username]))->assertSessionHasErrors('username');
        $this->actingAs($sa)->post('/superadmins', $this->payload(['password' => 'qisqa', 'password_confirmation' => 'qisqa']))->assertSessionHasErrors('password');
        $this->actingAs($sa)->post('/superadmins', $this->payload(['phone' => '12345']))->assertSessionHasErrors('phone');
    }

    public function test_block_unblock_and_last_active_protection(): void
    {
        $one = $this->user(Role::SAdmin);
        $two = $this->user(Role::SAdmin);

        // O'zini bloklab bo'lmaydi
        $this->actingAs($one)->post(route('superadmins.toggle', $one->id))->assertSessionHas('error');
        $this->assertTrue($one->fresh()->isActive());

        // Boshqasini bloklaydi
        $this->actingAs($one)->post(route('superadmins.toggle', $two->id))->assertSessionHas('success');
        $this->assertSame(UserStatus::Blocked, $two->fresh()->status);

        // Qayta faollashtiradi
        $this->actingAs($one)->post(route('superadmins.toggle', $two->id))->assertSessionHas('success');
        $this->assertTrue($two->fresh()->isActive());
    }

    public function test_cannot_remove_last_active_sadmin_but_can_remove_when_another_exists(): void
    {
        $one = $this->user(Role::SAdmin);
        $two = $this->user(Role::SAdmin);

        // `two` bloklangan bo'lsa, `one` yagona faol - `two` ni olib tashlash mumkin, lekin `one`ni boshqa faol yo'q
        $this->actingAs($one)->post(route('superadmins.toggle', $two->id));
        $this->actingAs($one)->delete(route('superadmins.destroy', $one->id))->assertSessionHas('error');

        $this->actingAs($one)->delete(route('superadmins.destroy', $two->id))->assertSessionHas('success');
        $this->assertNotNull($two->fresh()->archived_at);
        $this->assertSame(UserStatus::Blocked, $two->fresh()->status);

        // Faol ikkinchi sAdmin bo'lsa, boshqasini olib tashlash ruxsat
        $three = $this->user(Role::SAdmin);
        $this->actingAs($one)->delete(route('superadmins.destroy', $three->id))->assertSessionHas('success');
        $this->assertTrue($one->fresh()->isActive());
    }

    public function test_cannot_toggle_non_sadmin_through_this_section(): void
    {
        $sa = $this->user(Role::SAdmin);
        $admin = $this->user(Role::Admin, $this->branch());

        $this->actingAs($sa)->post(route('superadmins.toggle', $admin->id))->assertNotFound();
        $this->actingAs($sa)->delete(route('superadmins.destroy', $admin->id))->assertNotFound();
    }
}
