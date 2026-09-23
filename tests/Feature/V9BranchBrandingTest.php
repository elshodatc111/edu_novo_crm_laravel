<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v9 (3-band): ochiq murojaat sahifasida Edunova o'rniga filialning o'z nomi/rangi/ma'lumoti. */
class V9BranchBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_show_page_uses_branch_branding_and_hides_edunova(): void
    {
        $branch = $this->branch('Andijon');
        $branch->update(['brand_color' => '#123abc', 'public_about' => "Andijon filiali haqida qisqa ma'lumot"]);

        $response = $this->get("/apply/{$branch->code}");

        $response->assertOk()
            ->assertSee('Andijon')
            ->assertSee("Andijon filiali haqida qisqa ma'lumot")
            ->assertSee('#123abc', false)
            ->assertDontSee('Edunova');
    }

    public function test_apply_show_falls_back_to_default_color_when_unset(): void
    {
        $branch = $this->branch('Namangan');

        $response = $this->get("/apply/{$branch->code}");

        $response->assertOk()->assertSee('Namangan')->assertSee(Branch::DEFAULT_BRAND_COLOR, false)->assertDontSee('Edunova');
    }

    public function test_apply_index_page_still_shows_generic_branding(): void
    {
        $this->branch('Farg\'ona');
        $this->branch('Xorazm');

        $this->get('/apply')->assertOk()->assertSee('Edunova');
    }

    public function test_sadmin_can_update_branch_branding(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $branch = $this->branch();

        $this->actingAs($sadmin)->put("/branches/{$branch->id}", [
            'name' => $branch->name,
            'brand_color' => '#00ff00',
            'public_about' => 'Yangi tavsif',
        ])->assertRedirect(route('branches.index'));

        $branch->refresh();
        $this->assertSame('#00ff00', $branch->brand_color);
        $this->assertSame('Yangi tavsif', $branch->public_about);
    }

    public function test_invalid_color_format_is_rejected(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $branch = $this->branch();

        $this->actingAs($sadmin)->put("/branches/{$branch->id}", [
            'name' => $branch->name,
            'brand_color' => 'not-a-color',
        ])->assertSessionHasErrors('brand_color');
    }

    /** v10 (5-band): tahrirlash formasida tanlangan rangning HEX qiymati matn sifatida ham ko'rinishi kerak. */
    public function test_branch_edit_form_shows_selected_color_value_as_text(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $branch = $this->branch();
        $branch->update(['brand_color' => '#00ff00']);

        $this->actingAs($sadmin)->get("/branches/{$branch->id}/edit")
            ->assertOk()
            ->assertSee('#00ff00', false)
            ->assertSee('color-swatch', false);
    }
}
