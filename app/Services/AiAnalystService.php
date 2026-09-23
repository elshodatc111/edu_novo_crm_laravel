<?php

namespace App\Services;

use App\Models\AiChat;
use App\Models\AiMessage;
use App\Models\Branch;
use App\Models\Group;
use App\Models\Lead;
use App\Models\User;
use App\Support\BranchContext;
use Carbon\CarbonImmutable;

/**
 * AI tahlilchi. AI bazaga to'g'ridan-to'g'ri kirmaydi: faqat quyidagi "asboblar" orqali,
 * foydalanuvchining filiali va RUXSATLARI doirasida hisoblangan raqamlarni oladi.
 * Ism va telefonlar OpenAI'ga yuborilmaydi.
 */
class AiAnalystService
{
    private const MAX_ROUNDS = 5;

    public function __construct(private OpenAiService $ai, private StatisticsService $stats, private AttendanceService $attendance) {}

    public function ask(User $actor, AiChat $chat, string $question): string
    {
        $branchId = BranchContext::id();
        $this->ai->assertQuota($actor, $branchId);

        $tools = $this->tools($actor);
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($actor, array_keys($tools))]];

        foreach ($chat->messages()->latest('id')->limit(10)->get()->reverse() as $m) {
            $messages[] = ['role' => $m->role, 'content' => $m->content];
        }
        $messages[] = ['role' => 'user', 'content' => $question];

        $answer = null;
        $specs = array_map(fn ($t) => ['type' => 'function', 'function' => ['name' => $t['name'], 'description' => $t['description'], 'parameters' => $t['parameters']]], array_values($tools));

        for ($round = 0; $round < self::MAX_ROUNDS && $answer === null; $round++) {
            $reply = $this->ai->chat($messages, $specs);
            $calls = $reply['tool_calls'] ?? [];

            if (! $calls) {
                $answer = trim((string) ($reply['content'] ?? ''));
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $reply['content'] ?? null, 'tool_calls' => $calls];
            foreach ($calls as $call) {
                $name = $call['function']['name'] ?? '';
                $args = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];

                $result = isset($tools[$name])
                    ? $this->safely(fn () => ($tools[$name]['run'])($args))
                    : ['xato' => "Bu ma'lumotni olishga ruxsat yo'q."];

                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'] ?? $name, 'content' => json_encode($result, JSON_UNESCAPED_UNICODE)];
            }
        }

        $answer = $answer !== null && $answer !== '' ? $answer : "Kechirasiz, javob tayyorlay olmadim. Savolni boshqacha yozib ko'ring.";

        // Savol faqat muvaffaqiyatli javobdan keyin saqlanadi (xatoda kunlik limit sarflanmaydi)
        AiMessage::create(['ai_chat_id' => $chat->id, 'branch_id' => $branchId, 'user_id' => $actor->id, 'role' => 'user', 'content' => $question]);
        AiMessage::create(['ai_chat_id' => $chat->id, 'branch_id' => $branchId, 'user_id' => $actor->id, 'role' => 'assistant', 'content' => $answer]);
        $chat->touch();

        return $answer;
    }

    /** @return array<string, array{name:string,description:string,parameters:array,run:callable}> faqat ruxsat etilganlari */
    public function tools(User $actor): array
    {
        $period = ['type' => 'object', 'properties' => [
            'from' => ['type' => 'string', 'description' => 'Boshlanish sanasi YYYY-MM-DD (standart: shu oyning boshi)'],
            'to' => ['type' => 'string', 'description' => 'Tugash sanasi YYYY-MM-DD (standart: bugun)'],
        ]];
        $none = ['type' => 'object', 'properties' => new \stdClass];
        $tools = [];

        if ($actor->can('statistics.view')) {
            $tools['overview'] = ['name' => 'overview', 'description' => "Davr bo'yicha asosiy ko'rsatkichlar: tushum, xarajat, yangi o'quvchilar, qarzdorlar, murojaatlar, davomad.", 'parameters' => $period,
                'run' => fn ($a) => $this->filterOverview($actor, $this->stats->overview(...$this->dates($a)))];
            $tools['monthly_trend'] = ['name' => 'monthly_trend', 'description' => "So'nggi oylar dinamikasi: sof tushum, yangi o'quvchi va murojaatlar.", 'parameters' => ['type' => 'object', 'properties' => ['months' => ['type' => 'integer', 'description' => '3 dan 12 gacha']]],
                'run' => fn ($a) => array_map(fn ($r) => $actor->can('payments.view') ? $r : array_diff_key($r, ['income' => 1, 'net_income' => 1]), $this->stats->trend(max(3, min(12, (int) ($a['months'] ?? 6)))))];
        }

        if ($actor->can('payments.view')) {
            $tools['debtors_summary'] = ['name' => 'debtors_summary', 'description' => "Qarzdorlar: soni, umumiy qarz, qarz oralig'lari bo'yicha taqsimot va eng katta 5 ta qarz (shaxsiy ma'lumotsiz).", 'parameters' => $none, 'run' => fn () => $this->debtors()];
        }

        if ($actor->can('attendance.stats')) {
            $tools['attendance_summary'] = ['name' => 'attendance_summary', 'description' => "Oylik davomad: umumiy foiz, guruhlar bo'yicha foiz va davomad olinmagan kunlar.", 'parameters' => ['type' => 'object', 'properties' => ['month' => ['type' => 'string', 'description' => 'YYYY-MM']]],
                'run' => fn ($a) => $this->attendanceSummary((string) ($a['month'] ?? today()->format('Y-m')))];
        }

        if ($actor->can('groups.view')) {
            $tools['groups_summary'] = ['name' => 'groups_summary', 'description' => "Joriy guruhlar: soni, o'rtacha o'quvchi soni, kam o'quvchili guruhlar.", 'parameters' => $none, 'run' => fn () => $this->groupsSummary()];
        }

        if ($actor->can('leads.view')) {
            $tools['leads_summary'] = ['name' => 'leads_summary', 'description' => "Varonka: holatlar bo'yicha murojaatlar va manbalar bo'yicha qabul foizi.", 'parameters' => $none, 'run' => fn () => $this->leadsSummary()];
        }

        if ($actor->isSuperAdmin() && BranchContext::id() === null) {
            $tools['branches_comparison'] = ['name' => 'branches_comparison', 'description' => 'Barcha filiallarni solishtirish: sof tushum, yangi va faol o\'quvchilar, qarz.', 'parameters' => $period,
                'run' => fn ($a) => $this->stats->branchComparison(...$this->dates($a))];
        }

        return $tools;
    }

    private function systemPrompt(User $actor, array $toolNames): string
    {
        $scope = BranchContext::id() ? 'Filial: '.Branch::find(BranchContext::id())?->name : ($actor->isSuperAdmin() ? 'Barcha filiallar (sAdmin)' : 'Filial: '.$actor->branch?->name);

        return "Sen Edunova CRM o'quv markazlari boshqaruvi tizimining tahlilchi yordamchisisan. O'zbek tilida, qisqa va aniq javob ber.\n"
            ."{$scope}. Bugungi sana: ".today()->toDateString().".\n"
            ."QOIDALAR: 1) Faqat berilgan asboblardan (funksiyalardan) olingan raqamlarga tayan; raqamni o'zingdan to'qima. 2) Ma'lumot yetishmasa yoki asbob mavjud bo'lmasa, buni ochiq ayt. "
            ."3) Tahlil qilganda: qisqa xulosa, aniqlangan kamchiliklar (raqamlar bilan) va amaliy tavsiyalarni alohida ko'rsat. 4) So'm summalarini o'qilishi oson formatda yoz. "
            ."5) Foydalanuvchi ruxsati bo'lmagan ma'lumotni so'rasa, bunga ruxsati yo'qligini ayt. Mavjud asboblar: ".implode(', ', $toolNames).".\n"
            ."6) Foydalanuvchi tizimdan foydalanish (qoidalar, qadamlar) haqida so'rasa, quyidagi qo'llanmadan javob ber; qo'llanmada yo'qini to'qima.\n\n=== QO'LLANMA ===\n".\App\Support\KnowledgeBase::forUser($actor);
    }

    /** Foydalanuvchi ruxsat etilmagan pul ko'rsatkichlarini olib tashlaydi. */
    private function filterOverview(User $actor, array $o): array
    {
        if (! $actor->can('payments.view')) {
            $o = array_diff_key($o, array_flip(['income', 'income_cash', 'income_card', 'refunds', 'discounts', 'net_income', 'payments_count']));
        }
        if (! $actor->can('finance.view')) {
            $o = array_diff_key($o, array_flip(['expenses', 'salaries', 'profit']));
        }

        return $o;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function dates(array $a): array
    {
        $parse = function (?string $v, CarbonImmutable $default) {
            try {
                return $v ? CarbonImmutable::parse($v)->startOfDay() : $default;
            } catch (\Throwable) {
                return $default;
            }
        };

        $from = $parse($a['from'] ?? null, CarbonImmutable::today()->startOfMonth());
        $to = $parse($a['to'] ?? null, CarbonImmutable::today());

        return $from->gt($to) ? [$to, $from] : [$from, $to];
    }

    private function debtors(): array
    {
        $balances = User::visibleToContext()->where('role', 'student')->whereNull('archived_at')->where('balance', '<', 0)->orderBy('balance')->get(['id', 'balance']);
        $buckets = ['0-100 ming' => 0, '100-300 ming' => 0, '300-500 ming' => 0, '500 mingdan yuqori' => 0];
        foreach ($balances as $b) {
            $d = -$b->balance;
            $buckets[match (true) { $d <= 100000 => '0-100 ming', $d <= 300000 => '100-300 ming', $d <= 500000 => '300-500 ming', default => '500 mingdan yuqori' }]++;
        }

        return [
            'qarzdorlar_soni' => $balances->count(), 'umumiy_qarz' => (int) -$balances->sum('balance'), 'oraliqlar' => $buckets,
            'eng_katta_5_qarz' => $balances->take(5)->map(fn ($b) => ["oquvchi_id" => $b->id, 'qarz' => (int) -$b->balance])->values()->all(),
        ];
    }

    private function attendanceSummary(string $month): array
    {
        $m = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : today()->format('Y-m');
        $data = $this->attendance->monthly($m);

        return [
            'oy' => $m, 'umumiy_foiz' => $data['totals']['rate'], 'kelmagan_jami' => $data['totals']['absent'],
            'guruhlar' => array_map(fn ($r) => ['guruh' => $r['group']->name, 'foiz' => $r['rate'], 'rejadagi_kunlar' => $r['scheduled'], 'davomad_olingan_kunlar' => $r['held']], $data['rows']),
        ];
    }

    private function groupsSummary(): array
    {
        $groups = Group::status('current')->withCount('activeMembers as students')->get();

        return [
            'joriy_guruhlar' => $groups->count(), 'ortacha_oquvchi' => $groups->count() ? round($groups->avg('students'), 1) : 0,
            'kam_oquvchili_guruhlar' => $groups->where('students', '<', 6)->map(fn ($g) => ['guruh' => $g->name, 'oquvchilar' => $g->students])->values()->all(),
        ];
    }

    private function leadsSummary(): array
    {
        $bySource = Lead::query()->leftJoin('lead_sources', 'lead_sources.id', '=', 'leads.lead_source_id')
            ->selectRaw("coalesce(lead_sources.name, 'Korsatilmagan') as manba, count(*) as jami, sum(case when leads.status = 'converted' then 1 else 0 end) as qabul")
            ->groupBy('lead_sources.name')->get()->map(fn ($r) => ['manba' => $r->manba, 'jami' => (int) $r->jami, 'qabul' => (int) $r->qabul])->all();

        return ['holatlar' => Lead::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')->all(), 'manbalar' => $bySource];
    }

    private function safely(callable $fn): array
    {
        try {
            return (array) $fn();
        } catch (\Throwable $e) {
            report($e);

            return ['xato' => "Ma'lumotni olib bo'lmadi."];
        }
    }
}
