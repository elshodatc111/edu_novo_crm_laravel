<?php

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: yon menyu bo'limlarga ajratilgan; ruxsati bo'lmagan bo'lim sarlavhasi ham chiqmaydi. */
class V13SidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_sadmin_sees_all_sections(): void
    {
        $this->actingAs($this->user(Role::SAdmin))->get('/')->assertOk()
            ->assertSee("O'quv jarayoni")->assertSee('Murojaat va aloqa')->assertSee('Moliya')
            ->assertSee('Tahlil')->assertSee('Boshqaruv')->assertSee('Tizim holati');
    }

    public function test_sections_without_visible_items_are_hidden(): void
    {
        $teacher = $this->user(Role::Teacher, null, ['attendance.view', 'attendance.take']);

        $this->actingAs($teacher)->get('/')->assertOk()
            ->assertSee('Bugungi davomad')
            ->assertDontSee('Murojaat va aloqa')
            ->assertDontSee('Tizim holati')
            ->assertDontSee('Ish haqi');
    }

    public function test_money_section_shows_only_permitted_items(): void
    {
        $manager = $this->user(Role::Manager, null, ['payments.view']);

        $this->actingAs($manager)->get('/')->assertOk()
            ->assertSee("To'lovlar")->assertDontSee('Ish haqi');
    }
}
