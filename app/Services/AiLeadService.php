<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadNote;
use Illuminate\Validation\ValidationException;

/**
 * Har bir murojaat (lid) bo'yicha AI tahlili: ustuvorlik, qabul ehtimoli, keyingi qadam.
 * Ism va telefon OpenAI'ga yuborilmaydi; izohlardagi raqamlar va murojaatchi ismi olib tashlanadi.
 */
class AiLeadService
{
    public function __construct(private OpenAiService $ai) {}

    public function available(): bool
    {
        return $this->ai->configured();
    }

    /** @return array<string,mixed>|null */
    public function analyze(Lead $lead, bool $force = false): ?array
    {
        if (! $this->ai->configured()) {
            if ($force) {
                throw ValidationException::withMessages(['ai' => "OpenAI kaliti sozlanmagan. .env faylida OPENAI_API_KEY ni kiriting."]);
            }

            return null;
        }

        $limit = (int) config('services.openai.daily_limit');
        $today = Lead::withoutGlobalScopes()->where('branch_id', $lead->branch_id)->whereDate('ai_analyzed_at', today())->count();
        if ($limit > 0 && $today >= $limit) {
            if ($force) {
                throw ValidationException::withMessages(['ai' => "AI tahlillari kunlik limitiga ({$limit}) yetdi."]);
            }

            return null;
        }

        $reply = $this->ai->chat([
            ['role' => 'system', 'content' => "Sen o'quv markazi qabul bo'limi uchun murojaatlarni baholaydigan yordamchisan. Faqat JSON qaytar: "
                .'{"priority":"yuqori|o\'rta|past","probability":0-100 orasida butun son,"summary":"1-2 jumla xulosa","next_step":"menejer uchun aniq keyingi qadam","talking_points":["suhbat uchun 2-3 ta tavsiya"],"risks":["e\'tibor berish kerak bo\'lgan xavflar (bo\'lmasa bo\'sh)"]}. '
                ."O'zbek tilida yoz. Faqat berilgan ma'lumotga tayan, o'zingdan fakt to'qima; ma'lumot kam bo'lsa, buni xulosada ayt."],
            ['role' => 'user', 'content' => json_encode($this->context($lead), JSON_UNESCAPED_UNICODE)],
        ], [], json: true);

        $data = json_decode((string) ($reply['content'] ?? ''), true);
        if (! is_array($data) || ! isset($data['summary'])) {
            throw ValidationException::withMessages(['ai' => "AI tushunarsiz javob qaytardi. Qayta urinib ko'ring."]);
        }

        $analysis = [
            'priority' => in_array($data['priority'] ?? '', ['yuqori', "o'rta", 'past'], true) ? $data['priority'] : "o'rta",
            'probability' => max(0, min(100, (int) ($data['probability'] ?? 50))),
            'summary' => mb_substr((string) $data['summary'], 0, 600),
            'next_step' => mb_substr((string) ($data['next_step'] ?? ''), 0, 400),
            'talking_points' => array_slice(array_map(fn ($p) => mb_substr((string) $p, 0, 250), (array) ($data['talking_points'] ?? [])), 0, 4),
            'risks' => array_slice(array_map(fn ($p) => mb_substr((string) $p, 0, 250), (array) ($data['risks'] ?? [])), 0, 3),
        ];

        $lead->forceFill(['ai_analysis' => $analysis, 'ai_analyzed_at' => now()])->save();

        LeadNote::create([
            'lead_id' => $lead->id, 'user_id' => null, 'type' => 'ai',
            'body' => "AI fikri — ustuvorlik: {$analysis['priority']}, qabul ehtimoli: {$analysis['probability']}%. {$analysis['summary']}".($analysis['next_step'] ? " Keyingi qadam: {$analysis['next_step']}" : ''),
        ]);

        return $analysis;
    }

    /** OpenAI'ga yuboriladigan shaxsiy ma'lumotsiz kontekst. */
    private function context(Lead $lead): array
    {
        $names = array_filter(preg_split('/\s+/u', mb_strtolower($lead->name)) ?: [], fn ($n) => mb_strlen($n) >= 3);

        $scrub = function (string $text) use ($names) {
            $text = preg_replace('/\+?\d[\d\s\-()]{5,}\d/u', '[raqam]', $text) ?? $text;
            foreach ($names as $n) {
                $text = preg_replace('/'.preg_quote($n, '/').'\w*/iu', '[ism]', $text) ?? $text;
            }

            return $text;
        };

        return [
            'holat' => $lead->status_label,
            'manba' => $lead->source?->name ?? "ko'rsatilmagan",
            'takror_murojaat' => $lead->is_repeat,
            'murojaatdan_beri_kun' => (int) $lead->created_at->diffInDays(now()),
            'hudud' => mb_substr($scrub((string) $lead->address), 0, 80),
            'izohlar' => $lead->notes()->where('type', 'note')->limit(12)->get()->reverse()->values()->map(fn ($n) => [
                'kim' => $n->user_id ? 'xodim' : 'tizim', 'sana' => $n->created_at->toDateString(), 'matn' => mb_substr($scrub($n->body), 0, 300),
            ])->all(),
        ];
    }
}
