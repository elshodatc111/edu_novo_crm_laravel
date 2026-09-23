<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AiChat;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\CourseQuestion;
use App\Models\User;
use App\Services\AiAnalystService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
        config(['services.openai.key' => 'sk-test', 'services.openai.model' => 'gpt-test', 'services.openai.daily_limit' => 5]);

        $this->branch = $this->branch('Toshkent');
        $this->admin = $this->user(Role::Admin, $this->branch, ['ai.chat', 'statistics.view', 'payments.view', 'finance.view', 'groups.view', 'leads.view', 'attendance.stats', 'courses.manage']);
    }

    private function reply(array $message): \Illuminate\Http\Client\ResponseSequence
    {
        return Http::sequence()->push(['choices' => [['message' => $message]]]);
    }

    public function test_analyst_calls_tools_and_saves_conversation_without_personal_data(): void
    {
        $this->student($this->branch, ['name' => 'Maxfiy Ismli', 'phone' => '+998 90 123 4567', 'balance' => -250000]);

        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [
                ['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'debtors_summary', 'arguments' => '{}']],
                ['id' => 'c2', 'type' => 'function', 'function' => ['name' => 'overview', 'arguments' => '{"from":"2026-09-01","to":"2026-09-30"}']],
            ]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => "Qarz 250 000 so'm, tavsiya: eslatma yuboring."]]]]),
        ]);

        $this->actingAs($this->admin)->post('/ai', ['message' => 'Qarzdorlik qanday?'])->assertRedirect();

        $chat = AiChat::firstOrFail();
        $this->assertSame(['user', 'assistant'], $chat->messages()->pluck('role')->all());
        $this->assertStringContainsString('250 000', $chat->messages()->get()->last()->content);

        $sent = Http::recorded();
        $this->assertCount(2, $sent);
        $second = json_encode($sent[1][0]->data(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('250000', $second);                  // tool natijasi yuborilgan
        $this->assertStringNotContainsString('Maxfiy Ismli', $second);         // ism yuborilmagan
        $this->assertStringNotContainsString('998901234567', $second);         // telefon yuborilmagan
        $this->assertSame('Bearer sk-test', $sent[0][0]->header('Authorization')[0]);
        $this->assertSame('gpt-test', $sent[0][0]->data()['model']);

        $this->actingAs($this->admin)->get("/help?mode=analyst&chat={$chat->id}")->assertOk()->assertSee('tavsiya');
    }

    public function test_tools_follow_permissions_and_scope(): void
    {
        $limited = $this->user(Role::Admin, $this->branch, ['ai.chat', 'statistics.view']);
        $this->actingAs($limited);
        $tools = app(AiAnalystService::class)->tools($limited);

        $this->assertSame(['overview', 'monthly_trend'], array_keys($tools));
        $overview = ($tools['overview']['run'])([]);
        $this->assertArrayNotHasKey('income', $overview);       // payments.view yo'q
        $this->assertArrayNotHasKey('profit', $overview);       // finance.view yo'q
        $this->assertArrayHasKey('debtors', $overview);
        $trend = ($tools['monthly_trend']['run'])(['months' => 4]);
        $this->assertArrayNotHasKey('net_income', $trend[0]);

        $this->actingAs($this->admin);
        $full = app(AiAnalystService::class)->tools($this->admin);
        $this->assertArrayHasKey('debtors_summary', $full);
        $this->assertArrayHasKey('attendance_summary', $full);
        $this->assertArrayNotHasKey('branches_comparison', $full);     // faqat sAdmin
        $this->assertArrayHasKey('income', ($full['overview']['run'])([]));

        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin);
        $this->assertArrayHasKey('branches_comparison', app(AiAnalystService::class)->tools($sadmin));
        \App\Support\BranchContext::select($this->branch->id);
        $this->assertArrayNotHasKey('branches_comparison', app(AiAnalystService::class)->tools($sadmin));
    }

    public function test_unauthorized_tool_call_is_refused_not_executed(): void
    {
        $limited = $this->user(Role::Admin, $this->branch, ['ai.chat', 'statistics.view']);
        Http::fake(['api.openai.com/*' => Http::sequence()
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'debtors_summary', 'arguments' => '{}']]]]]]])
            ->push(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Ruxsat yo\'q.']]]]),
        ]);

        $this->actingAs($limited)->post('/ai', ['message' => 'Qarzdorlar?'])->assertRedirect();

        $toolMsg = collect(Http::recorded()[1][0]->data()['messages'])->firstWhere('role', 'tool');
        $this->assertStringContainsString("ruxsat yo'q", mb_strtolower($toolMsg['content']));
    }

    public function test_access_quota_and_errors(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => 'Javob']]]])]);

        // Ruxsatsiz rollar
        $this->actingAs($this->user(Role::Manager, $this->branch, ['statistics.view']))->get('/ai')->assertForbidden();
        $this->actingAs($this->user(Role::Teacher, $this->branch, ['attendance.view']))->post('/ai', ['message' => 'x'])->assertForbidden();

        // Kunlik limit (5)
        $this->actingAs($this->admin);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/ai', ['message' => "Savol {$i}"])->assertRedirect();
        }
        $this->post('/ai', ['message' => 'Oltinchi'])->assertSessionHasErrors('message');
        $this->assertSame(5, AiMessage::where('role', 'user')->count());

        // Ertasi kuni limit yangilanadi
        Carbon::setTestNow('2026-09-22 10:00:00');
        $this->post('/ai', ['message' => 'Yangi kun'])->assertRedirect();
    }

    public function test_missing_key_and_provider_failures_are_reported_nicely(): void
    {
        config(['services.openai.key' => null]);
        $this->actingAs($this->admin)->post('/ai', ['message' => 'Salom'])->assertSessionHasErrors('message');
        $this->assertSame(0, AiChat::count());                       // bo'sh suhbat qolmaydi
        $this->get('/help?mode=analyst')->assertOk()->assertSee('OpenAI kaliti sozlanmagan');

        config(['services.openai.key' => 'sk-test']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'bad key']], 401)]);
        $this->post('/ai', ['message' => 'Salom'])->assertSessionHasErrors('message');

        Http::fake(['api.openai.com/*' => Http::response('', 500)]);
        $this->post('/ai', ['message' => 'Salom'])->assertSessionHasErrors('message');
    }

    public function test_chats_are_private_and_deletable(): void
    {
        $chat = AiChat::create(['user_id' => $this->admin->id, 'branch_id' => $this->branch->id, 'title' => 'Mening', 'kind' => 'analytics']);
        $other = $this->user(Role::Admin, $this->branch, ['ai.chat']);

        $this->actingAs($other)->get("/help?mode=analyst&chat={$chat->id}")->assertNotFound();
        $this->actingAs($other)->delete("/ai/{$chat->id}")->assertNotFound();
        $this->actingAs($this->admin)->delete("/ai/{$chat->id}")->assertRedirect(route('help.index', ['mode' => 'analyst']));
        $this->assertSame(0, AiChat::count());
    }

    public function test_ai_question_generation_review_and_save(): void
    {
        $course = Course::create(['branch_id' => $this->branch->id, 'name' => 'TOPIK 1']);
        $json = json_encode(['questions' => [
            ['question' => 'Salom koreyscha?', 'correct' => '안녕하세요', 'wrong' => ['감사합니다', '죄송합니다', '안녕히 가세요']],
            ['question' => 'Yaroqsiz (2 ta variant)', 'correct' => 'A', 'wrong' => ['B', 'C']],
            ['question' => 'Takror javob', 'correct' => 'A', 'wrong' => ['A', 'B', 'C']],
        ]], JSON_UNESCAPED_UNICODE);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['role' => 'assistant', 'content' => $json]]]])]);

        $response = $this->actingAs($this->admin)->post("/courses/{$course->id}/questions/ai", ['topic' => 'Salomlashish', 'count' => 3, 'level' => "boshlang'ich"]);
        $response->assertRedirect()->assertSessionHas('ai_questions');
        $questions = session('ai_questions');
        $this->assertCount(1, $questions);                            // faqat yaroqli savol qoldi
        $this->assertSame(0, CourseQuestion::count());                 // hali saqlanmagan

        Http::assertSent(fn (Request $r) => ($r->data()['response_format']['type'] ?? null) === 'json_object');

        $this->post("/courses/{$course->id}/questions/ai-store", ['questions' => $questions, 'selected' => [0]])->assertRedirect();
        $this->assertSame(1, CourseQuestion::count());
        $this->assertSame('안녕하세요', CourseQuestion::first()->correct);

        $this->post("/courses/{$course->id}/questions/ai-store", ['questions' => $questions])->assertSessionHasErrors('selected');
        $this->post("/courses/{$course->id}/questions/ai", ['topic' => '', 'count' => 3, 'level' => "o'rta"])->assertSessionHasErrors('topic');

        // Ruxsatsiz
        $manager = $this->user(Role::Manager, $this->branch, ['courses.view']);
        $this->actingAs($manager)->post("/courses/{$course->id}/questions/ai", ['topic' => 'x', 'count' => 3, 'level' => "o'rta"])->assertForbidden();
    }
}
