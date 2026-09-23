<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v9 (1-band): Operator uchun barcha filiallar xodimlarining faqat ism/rol/filial ro'yxati. */
class V9StaffDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_sees_staff_from_all_branches(): void
    {
        $branchA = $this->branch('Samarqand');
        $branchB = $this->branch('Buxoro');
        $operator = $this->user(Role::Operator, $branchA, ['staff.view_all_branches']);
        $this->user(Role::Admin, $branchA, [], ['name' => 'Samarqand Admini']);
        $this->user(Role::Manager, $branchB, [], ['name' => 'Buxoro Menejeri']);
        $this->student($branchB, ['name' => 'Sirdaryo Talabasi']);

        $response = $this->actingAs($operator)->get('/staff-directory');

        $response->assertOk()->assertSee('Samarqand')->assertSee('Buxoro')
            ->assertSee('Samarqand Admini')->assertSee('Buxoro Menejeri')
            ->assertDontSee('Sirdaryo Talabasi');
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['leads.view']);

        $this->actingAs($manager)->get('/staff-directory')->assertForbidden();
    }

    public function test_nav_link_only_shows_with_permission(): void
    {
        $branch = $this->branch();
        $operator = $this->user(Role::Operator, $branch, ['staff.view_all_branches', 'leads.view']);
        $noPerm = $this->user(Role::Manager, $branch, ['leads.view']);

        $this->actingAs($operator)->get('/leads')->assertOk()->assertSee('Filiallar xodimlari');
        $this->actingAs($noPerm)->get('/leads')->assertOk()->assertDontSee('Filiallar xodimlari');
    }
}
