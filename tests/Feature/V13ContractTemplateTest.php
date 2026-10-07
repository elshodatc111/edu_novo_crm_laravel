<?php
 
namespace Tests\Feature;

use App\Enums\Role;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: to'liq standart shartnoma matni va uning sozlamalar sahifasida ko'rinishi. */
class V13ContractTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_template_uses_only_known_placeholders(): void
    {
        $text = ContractService::defaultTemplate();

        preg_match_all('/\{([a-z_]+)\}/', $text, $m);
        $known = array_map(fn ($k) => trim($k, '{}'), array_keys(ContractService::PLACEHOLDERS));

        $this->assertNotEmpty($m[1]);
        $this->assertSame([], array_values(array_diff(array_unique($m[1]), $known)), "Noma'lum o'rinbosar bor");
        $this->assertStringContainsString("TA'LIM XIZMATLARI KO'RSATISH SHARTNOMASI", $text);
        foreach (['1. SHARTNOMA PREDMETI', '2. XIZMAT NARXI', '5. SHARTNOMANI BEKOR QILISH', '6. SHAXSIY MA\'LUMOTLAR', '10. TOMONLARNING REKVIZITLARI'] as $section) {
            $this->assertStringContainsString($section, $text);
        }
    }

    public function test_settings_page_shows_default_template_in_textarea(): void
    {
        $branch = $this->branch('Chilonzor');
        $admin = $this->user(Role::Admin, $branch, ['settings.branch']);

        $this->actingAs($admin)->get('/settings/contract')->assertOk()->assertSee('SHARTNOMA PREDMETI', false);
    }

    public function test_to_html_builds_title_headings_clauses_and_signature_table(): void
    {
        $html = ContractService::toHtml(ContractService::defaultTemplate());

        $this->assertStringContainsString('<h1>', $html);
        $this->assertSame(10, substr_count($html, '<h2>'), '10 ta bo\'lim sarlavhasi bo\'lishi kerak');
        $this->assertStringContainsString('<div class="meta">', $html);
        $this->assertStringContainsString('<table class="sign">', $html);
        $this->assertStringContainsString('<td class="b">MARKAZ:</td>', $html);
        $this->assertStringContainsString('<td class="b">BUYURTMACHI:</td>', $html);
        $this->assertGreaterThan(40, substr_count($html, '<p>'));
    }

    public function test_to_html_escapes_untrusted_text(): void
    {
        $html = ContractService::toHtml("SARLAVHA\n\n1. BO'LIM\n1.1. <script>alert(1)</script> Ali & Vali");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
    }

    public function test_to_html_works_with_plain_custom_template(): void
    {
        $html = ContractService::toHtml('MAXSUS SHARTNOMA: Test Talaba - Guruh - 500 000 so\'m');

        $this->assertStringContainsString('<h1>MAXSUS SHARTNOMA', $html);
        $this->assertStringNotContainsString('<table', $html);
    }

    public function test_printed_contract_is_official_a4_document_and_blank_stir_gets_a_line(): void
    {
        $branch = $this->branch('Chilonzor');
        $admin = $this->user(Role::Admin, $branch, ['groups.create', 'groups.members']);
        $this->actingAs($admin);
        $cat = $this->catalog($branch);
        $group = app(\App\Services\GroupService::class)->create($this->groupPayload($cat), $admin);
        $student = $this->student($branch, ['name' => 'Test Talaba', 'phone' => '+998 90 555 4433']);
        app(\App\Services\EnrollmentService::class)->enroll($group, $student, null, $admin);

        $this->get("/groups/{$group->id}/students/{$student->id}/contract")
            ->assertOk()
            ->assertSee('size: A4', false)
            ->assertSee('<table class="sign">', false)
            ->assertSee('____________', false)
            ->assertDontSee('(STIR: )', false);
    }
}
