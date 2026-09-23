<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Validation\ValidationException;

/** OpenAI yordamida test savollari loyihasini yaratadi. Saqlashdan oldin xodim ko'rib chiqadi. */
class AiQuestionService
{
    public function __construct(private OpenAiService $ai) {}

    /** @return array<int, array{question:string, correct:string, wrong:array<int,string>}> */
    public function generate(Course $course, string $topic, int $count, string $level): array
    {
        $count = max(1, min(10, $count));

        $reply = $this->ai->chat([
            ['role' => 'system', 'content' => "Sen til o'rgatish markazi uchun test tuzuvchi metodistsan. Faqat JSON qaytar: {\"questions\":[{\"question\":\"...\",\"correct\":\"...\",\"wrong\":[\"...\",\"...\",\"...\"]}]}. "
                ."Har savolda aniq bitta to'g'ri va uchta ishonarli, lekin noto'g'ri javob bo'lsin. Javoblar bir-biridan farq qilsin. Savollar va javoblar kurs tilida bo'lsin, tushuntirishlarsiz."],
            ['role' => 'user', 'content' => "Kurs: {$course->name}. Mavzu: {$topic}. Daraja: {$level}. Savollar soni: {$count}."],
        ], [], json: true);

        $data = json_decode((string) ($reply['content'] ?? ''), true);
        $out = [];

        foreach ((array) ($data['questions'] ?? []) as $q) {
            $question = trim((string) ($q['question'] ?? ''));
            $correct = trim((string) ($q['correct'] ?? ''));
            $wrong = array_values(array_unique(array_filter(array_map(fn ($w) => trim((string) $w), (array) ($q['wrong'] ?? [])), fn ($w) => $w !== '' && $w !== $correct)));

            if ($question !== '' && $correct !== '' && count($wrong) === 3) {
                $out[] = ['question' => mb_substr($question, 0, 2000), 'correct' => mb_substr($correct, 0, 500), 'wrong' => array_map(fn ($w) => mb_substr($w, 0, 500), $wrong)];
            }
        }

        if (! $out) {
            throw ValidationException::withMessages(['topic' => "AI yaroqli savol qaytarmadi. Mavzuni aniqroq yozib, qayta urinib ko'ring."]);
        }

        return $out;
    }
}
