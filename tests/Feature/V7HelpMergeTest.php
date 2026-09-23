<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AiChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** V7: "AI yordamchi" va "Yordam" bitta menyu/sahifaga birlashtirildi (ikki rejim). */
class V7HelpMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_menu_item_and_two_modes_for_analyst_users(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['ai.chat', 'statistics.view']);

        $html = $this->actingAs($admin)->get('/help')->assertOk()->assertSee("Qo'llanma", false)->assertSee('Tahlil (AI)')->getContent();
        $this->assertStringNotContainsString('AI yordamchi', $html);
        $this->assertSame(1, preg_match_all('/<span>\s*Yordam\s*<\/span>/', $html), 'Menyuda bitta Yordam bandi');
        $this->get('/help?mode=analyst')->assertOk()->assertSee('Filial holatini umumiy tahlil');

        // Eski manzil endi bitta sahifaning tahlil rejimiga yo'naltiradi
        $this->get('/ai')->assertRedirect(route('help.index', ['mode' => 'analyst']));
    }

    public function test_users_without_ai_permission_see_only_help_mode(): void
    {
        $branch = $this->branch();
        $teacher = $this->user(Role::Teacher, $branch, ['attendance.view']);

        $this->actingAs($teacher)->get('/help')->assertOk()->assertDontSee('Tahlil (AI)');
        // Tahlil rejimini so'rasa ham, qo'llanma rejimi ochiladi (ruxsat kengaymaydi)
        $this->get('/help?mode=analyst')->assertOk()->assertDontSee('Tahlil (AI)')->assertSee('davomadni qanday olaman');
        $this->get('/ai')->assertForbidden();
    }

    public function test_chat_lists_are_separated_by_mode(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['ai.chat', 'statistics.view']);
        $h = AiChat::create(['user_id' => $admin->id, 'branch_id' => $branch->id, 'title' => 'Qollanma savoli', 'kind' => 'help']);
        $a = AiChat::create(['user_id' => $admin->id, 'branch_id' => $branch->id, 'title' => 'Tahlil savoli', 'kind' => 'analytics']);

        $this->actingAs($admin)->get('/help')->assertOk()->assertSee('Qollanma savoli')->assertDontSee('Tahlil savoli');
        $this->get('/help?mode=analyst')->assertOk()->assertSee('Tahlil savoli')->assertDontSee('Qollanma savoli');

        // Bir rejimdagi suhbatni ikkinchi rejimda ochib bo'lmaydi
        $this->get("/help?mode=analyst&chat={$h->id}")->assertNotFound();
        $this->get("/help?chat={$a->id}")->assertNotFound();
        $this->delete("/help/{$a->id}")->assertNotFound();
        $this->delete("/ai/{$h->id}")->assertNotFound();
    }
}
