<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseQuestion;
use App\Models\Group;
use App\Models\TestAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * O'quvchi testlari. Javoblar serverda tekshiriladi: to'g'ri javob o'quvchiga test topshirilgunga qadar yuborilmaydi.
 */
class TestService
{
    public const QUESTIONS_PER_TEST = 15;

    /** O'quvchi o'qiyotgan (guruhi tugamagan) kurslar ID si. */
    public function accessibleCourseIds(User $student): array
    {
        return Group::whereIn('id', $student->memberships()->where('is_active', true)->select('group_id'))
            ->whereDate('ends_on', '>=', today())
            ->pluck('course_id')->unique()->values()->all();
    }

    public function assertAccess(User $student, Course $course): void
    {
        if (! in_array($course->id, $this->accessibleCourseIds($student), true)) {
            abort(404);
        }
    }

    public function start(User $student, Course $course): TestAttempt
    {
        $this->assertAccess($student, $course);

        $questions = CourseQuestion::where('course_id', $course->id)->inRandomOrder()->limit(self::QUESTIONS_PER_TEST)->get();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages(['course' => "Bu kursda hali test savollari yo'q."]);
        }

        $shown = [];
        $key = [];
        foreach ($questions as $q) {
            $options = collect([$q->correct, ...$q->wrong])->shuffle()->values();
            $shown[] = ['id' => $q->id, 'question' => $q->question, 'options' => $options->all()];
            $key[] = $options->search($q->correct);
        }

        return TestAttempt::create([
            'branch_id' => $student->branch_id, 'student_id' => $student->id, 'course_id' => $course->id,
            'questions' => $shown, 'answer_key' => $key, 'total' => count($shown),
        ]);
    }

    /**
     * @param  array<int|string,int>  $answers  savol ID si => tanlangan variant indeksi
     * @return array{score:int,correct:int,total:int,details:array<int,array<string,mixed>>}
     */
    public function submit(TestAttempt $attempt, array $answers): array
    {
        return DB::transaction(function () use ($attempt, $answers) {
            $locked = TestAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($locked->finished_at !== null) {
                throw ValidationException::withMessages(['attempt' => "Bu test allaqachon topshirilgan."]);
            }

            $correct = 0;
            $details = [];
            foreach ($locked->questions as $i => $q) {
                $chosen = isset($answers[$q['id']]) ? (int) $answers[$q['id']] : null;
                $right = $locked->answer_key[$i];
                $ok = $chosen === $right;
                $correct += $ok ? 1 : 0;

                $details[] = ['question_id' => $q['id'], 'chosen' => $chosen, 'correct_index' => $right, 'correct_answer' => $q['options'][$right] ?? null, 'is_correct' => $ok];
            }

            $score = (int) round($correct * 100 / max(1, $locked->total));
            $locked->update(['correct_count' => $correct, 'score' => $score, 'finished_at' => now()]);

            return ['score' => $score, 'correct' => $correct, 'total' => $locked->total, 'details' => $details];
        });
    }
}
