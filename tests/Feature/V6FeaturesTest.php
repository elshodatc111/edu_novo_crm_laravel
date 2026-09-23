<?php

namespace Tests\Feature;

use App\Enums\PayMethod;
use App\Enums\Role;
use App\Enums\Wallet;
use App\Models\AiChat;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Services\CashboxService;
use App\Services\AiLeadService;
use App\Support\KnowledgeBase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class V6FeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
    }

    // ---- Kassa tarixi ----

    public function test_cashbox_history_only_for_permitted_users(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['cashbox.view', 'cashbox.request', 'cashbox.approve', 'cashbox.history']);
        $manager = $this->user(Role::Manager, $branch, ['cashbox.view', 'cashbox.request']);
        $this->fund($branch, Wallet::TillCash, 500000);
        $this->actingAs($admin);
        $req = app(CashboxService::class)->request('expense', PayMethod::Cash, 10000, 'Maxsus xarajat sababi', $admin);
        app(CashboxService::class)->approve($req, $admin);

        $this->actingAs($admin)->get('/cashbox')->assertOk()->assertSee("So'nggi 30 kun", false)->assertSee('Maxsus xarajat sababi');
        $this->actingAs($this->user(Role::SAdmin))->withSession(['current_branch_id' => $branch->id])->get('/cashbox')->assertOk()->assertSee("So'nggi 30 kun", false);
        $this->actingAs($manager)->get('/cashbox')->assertOk()->assertDontSee("So'nggi 30 kun", false)->assertDontSee('Maxsus xarajat sababi');
    }

    // ---- Chegirma qoidalari sozlamasi ----

    public function test_discount_rules_settings_page(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['settings.branch']);

        $this->actingAs($admin)->get('/settings/discount-rules')->assertOk()->assertSee('Chegirma qoidalari');
        $this->put('/settings/discount-rules', ['discount_days_before' => 14, 'discount_days_after' => 5])->assertRedirect();
        $this->assertSame(14, $branch->fresh()->discount_days_before);
        $this->assertSame(5, $branch->fresh()->discount_days_after);

        $this->put('/settings/discount-rules', ['discount_days_before' => -1, 'discount_days_after' => 999])->assertSessionHasErrors(['discount_days_before', 'discount_days_after']);

        $manager = $this->user(Role::Manager, $branch, ['students.view']);
        $this->actingAs($manager)->get('/settings/discount-rules')->assertForbidden();
    }

    // ---- Varonka: havola, iframe, AI ----

    public function test_lead_form_link_follows_selected_branch_and_embed_works(): void
    {
        $a = $this->branch('Samarqand');
        $b = $this->branch('Buxoro');
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->get('/leads')->assertOk()->assertSee('filialni tanlang');
        $this->actingAs($sadmin)->withSession(['current_branch_id' => $a->id])->get('/leads')->assertOk()
            ->assertSee("/apply/{$a->code}", false)->assertSee('iframe', false)->assertDontSee("/apply/{$b->code}", false);
        $this->actingAs($sadmin)->withSession(['current_branch_id' => $b->id])->get('/leads')->assertOk()->assertSee("/apply/{$b->code}", false);

        $manager = $this->user(Role::Manager, $a, ['leads.view']);
        $this->actingAs($manager)->get('/leads')->assertOk()->assertSee("/apply/{$a->code}", false);

        // Iframe uchun yengil sahifa (menyusiz) va yuborilgandan keyin ham embed rejimida qoladi
        $this->get("/apply/{$a->code}?embed=1")->assertOk()->assertDontSee('© ', false)->assertSee('name="embed"', false);
        $this->post("/apply/{$a->code}", ['name' => 'Ali', 'phone' => '+998 90 123 4567', 'embed' => 1])->assertRedirect("/apply/{$a->code}?embed=1");
    }

    public function test_ai_lead_analysis_is_saved_without_personal_data(): void
    {
        config(['services.openai.key' => 'sk-test', 'services.openai.daily_limit' => 50]);
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['leads.view', 'leads.manage']);
        $json = json_encode(['priority' => 'yuqori', 'probability' => 72, 'summary' => 'Qiziqish yuqori.', 'next_step' => "Ertaga qo'ng'iroq qiling.", 'talking_points' => ['Narxni ayting', 'Sinov darsini taklif qiling'], 'risks' => []], JSON_UNESCAPED_UNICODE);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $json]]]])]);

        // Yangi murojaat tushganda avtomatik tahlil (sync navbat)
        $this->post("/apply/{$branch->code}", ['name' => 'Sherzod Qodirov', 'phone' => '+998 90 123 4567', 'address' => 'Chilonzor, Sherzod uyi']);
        $lead = Lead::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('yuqori', $lead->ai_analysis['priority']);
        $this->assertSame(72, $lead->ai_analysis['probability']);
        $this->assertNotNull($lead->ai_analyzed_at);
        $this->assertSame(1, LeadNote::where('lead_id', $lead->id)->where('type', 'ai')->count());

        // Izoh yozilganda qayta tahlil; izohdagi ism va raqam OpenAI'ga ketmaydi
        $this->actingAs($manager)->post("/leads/{$lead->id}/note", ['body' => "Sherzod bilan gaplashdim, +998 90 123 4567 raqami, ertaga keladi"]);
        $sent = json_encode(Http::recorded()->last()[0]->data(), JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('Sherzod', $sent);
        $this->assertStringNotContainsString('123 4567', $sent);
        $this->assertStringNotContainsString('998901234567', $sent);
        $this->assertStringContainsString('[raqam]', $sent);
        $this->assertSame(2, LeadNote::where('lead_id', $lead->id)->where('type', 'ai')->count());

        $this->actingAs($manager)->get("/leads/{$lead->id}")->assertOk()->assertSee('AI fikri')->assertSee('72% qabul ehtimoli')->assertSee('Sinov darsini taklif qiling');
        $this->actingAs($manager)->get('/leads?status=in_progress')->assertOk()->assertSee('yuqori · 72%');
    }

    public function test_ai_lead_analysis_manual_button_errors_and_limits(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['leads.view', 'leads.manage']);
        $lead = Lead::create(['branch_id' => $branch->id, 'name' => 'ALI', 'phone' => '+998 90 123 4567', 'status' => 'new']);

        // Kalit yo'q: avtomatik jimgina o'tkazib yuboriladi, qo'lda bosilsa xato ko'rsatiladi
        $this->assertNull(app(AiLeadService::class)->analyze($lead));
        $this->actingAs($manager)->post("/leads/{$lead->id}/analyze")->assertSessionHasErrors('ai');

        config(['services.openai.key' => 'sk-test', 'services.openai.daily_limit' => 1]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => json_encode(['priority' => 'past', 'probability' => 20, 'summary' => 'Xulosa'])]]]])]);
        $this->actingAs($manager)->post("/leads/{$lead->id}/analyze")->assertSessionHas('success');
        $this->actingAs($manager)->post("/leads/{$lead->id}/analyze")->assertSessionHasErrors('ai');       // kunlik limit

        $viewer = $this->user(Role::Manager, $branch, ['leads.view']);
        $this->actingAs($viewer)->post("/leads/{$lead->id}/analyze")->assertForbidden();
    }

    // ---- Yordam (AI) va bilim bazasi ----

    public function test_knowledge_is_filtered_by_role_and_permissions(): void
    {
        $branch = $this->branch();
        $teacher = $this->user(Role::Teacher, $branch, ['attendance.view', 'attendance.take']);
        $manager = $this->user(Role::Manager, $branch, ['students.view', 'payments.create']);
        $sadmin = $this->user(Role::SAdmin);

        $t = KnowledgeBase::forUser($teacher);
        $this->assertStringContainsString('Davomad faqat guruhning DARS KUNI', $t);
        $this->assertStringNotContainsString('ehson foizini sozlash', $t);                 // moliya bo'limi ochilmaydi
        $this->assertStringNotContainsString('chiqim (egasi uchun)', $t);
        $this->assertStringContainsString("ruxsati doirasida emas", $t);                    // yashirilgan bo'lim ishorasi
        $this->assertStringContainsString('Telefon raqami qoidalari', $t);                 // umumiy bo'lim hammaga

        $m = KnowledgeBase::forUser($manager);
        $this->assertStringContainsString("To'lovni qaytarish", $m);
        $this->assertStringContainsString("avval to'lov qabul qiling", $m);

        $s = KnowledgeBase::forUser($sadmin);
        $this->assertStringContainsString('ehson foizini sozlash', $s);
        $this->assertStringNotContainsString("ruxsati doirasida emas", $s);
        $this->assertGreaterThan(strlen($t), strlen($s));
    }

    public function test_help_assistant_answers_by_role_and_is_private(): void
    {
        config(['services.openai.key' => 'sk-test', 'services.openai.daily_limit' => 3]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => "Guruh sahifasiga kiring va davomadni belgilang."]]]])]);
        $branch = $this->branch('Toshkent');
        $teacher = $this->user(Role::Teacher, $branch, ['attendance.view', 'attendance.take'], ['name' => 'Ustoz Ali']);

        // O'qituvchi ham yordamchidan foydalana oladi (ai.chat kerak emas)
        $this->actingAs($teacher)->get('/help')->assertOk()->assertSee('davomadni qanday olaman');
        $this->post('/help', ['message' => 'Davomadni qanday olaman?'])->assertRedirect();

        $chat = AiChat::firstOrFail();
        $this->assertSame('help', $chat->kind);
        $prompt = collect(Http::recorded()[0][0]->data()['messages'])->firstWhere('role', 'system')['content'];
        $this->assertStringContainsString("O'qituvchi", $prompt);
        $this->assertStringContainsString('Toshkent', $prompt);
        $this->assertStringContainsString('Davomad olish', $prompt);                        // o'z ruxsati
        $this->assertStringNotContainsString('ehson foizini sozlash', $prompt);             // boshqa rol ruxsati emas
        $this->assertStringContainsString('QO\'LLANMA', $prompt);

        $this->get("/help?chat={$chat->id}")->assertOk()->assertSee('davomadni belgilang');

        // Boshqa foydalanuvchi suhbatni ko'ra olmaydi; tahlilchi suhbatlari bu yerda chiqmaydi
        $other = $this->user(Role::Manager, $branch, ['students.view']);
        $this->actingAs($other)->get("/help?chat={$chat->id}")->assertNotFound();
        $this->actingAs($other)->delete("/help/{$chat->id}")->assertNotFound();
        $analytics = AiChat::create(['user_id' => $teacher->id, 'title' => 'Tahlil', 'kind' => 'analytics']);
        $this->actingAs($teacher)->get("/help?chat={$analytics->id}")->assertNotFound();

        // Limit
        $this->post('/help', ['message' => 'Yana savol', 'chat_id' => $chat->id])->assertRedirect();
        $this->post('/help', ['message' => 'Uchinchi', 'chat_id' => $chat->id])->assertRedirect();
        $this->post('/help', ['message' => 'To\'rtinchi', 'chat_id' => $chat->id])->assertSessionHasErrors('message');
    }

    public function test_analytics_prompt_also_contains_role_filtered_knowledge(): void
    {
        config(['services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Javob']]]])]);
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['ai.chat', 'statistics.view']);

        $this->actingAs($admin)->post('/ai', ['message' => "Qarzi bor o'quvchini guruhga qo'shsam bo'ladimi?"])->assertRedirect();

        $prompt = collect(Http::recorded()[0][0]->data()['messages'])->firstWhere('role', 'system')['content'];
        $this->assertStringContainsString("QO'LLANMA", $prompt);
        $this->assertStringContainsString('Statistika', $prompt);
    }
}
