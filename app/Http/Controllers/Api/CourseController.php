<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Course;
use App\Models\TestAttempt;
use App\Services\TestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** O'quvchi uchun kurs materiallari va testlar (faqat o'z guruhi kurslari). */
class CourseController extends Controller
{
    public function __construct(private TestService $tests) {}

    public function index(Request $request): JsonResponse
    {
        $student = $this->student($request);

        $courses = Course::whereIn('id', $this->tests->accessibleCourseIds($student))
            ->withCount(['videos', 'audios', 'questions'])->orderBy('name')->get();

        $best = TestAttempt::where('student_id', $student->id)->whereNotNull('finished_at')
            ->selectRaw('course_id, max(score) as best, count(*) as attempts')->groupBy('course_id')->get()->keyBy('course_id');

        return response()->json(['success' => true, 'data' => $courses->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name,
            'videos_count' => $c->videos_count, 'audios_count' => $c->audios_count, 'questions_count' => $c->questions_count,
            'best_score' => $best[$c->id]->best ?? null, 'attempts' => (int) ($best[$c->id]->attempts ?? 0),
        ])]);
    }

    public function videos(Request $request, Course $course): JsonResponse
    {
        $this->tests->assertAccess($this->student($request), $course);

        return response()->json(['success' => true, 'data' => $course->videos()->get()->map(fn ($v) => [
            'id' => $v->id, 'number' => $v->number, 'title' => $v->title, 'url' => $v->url,
        ])]);
    }

    public function audios(Request $request, Course $course): JsonResponse
    {
        $this->tests->assertAccess($this->student($request), $course);

        return response()->json(['success' => true, 'data' => $course->audios()->get()->map(fn ($a) => [
            'id' => $a->id, 'number' => $a->number, 'title' => $a->title, 'url' => $a->url,
        ])]);
    }

    /** Yangi test boshlash: savollar va aralashtirilgan variantlar qaytadi (to'g'ri javob yuborilmaydi). */
    public function startTest(Request $request, Course $course): JsonResponse
    {
        $attempt = $this->tests->start($this->student($request), $course);

        return response()->json(['success' => true, 'data' => [
            'attempt_id' => $attempt->id,
            'total' => $attempt->total,
            'questions' => $attempt->questions,
        ]]);
    }

    public function submitTest(Request $request, int $attempt): JsonResponse
    {
        $student = $this->student($request);

        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.choice' => ['required', 'integer', 'min:0', 'max:3'],
        ], [], ['answers' => 'Javoblar']);

        $model = TestAttempt::where('student_id', $student->id)->findOrFail($attempt);
        $map = collect($data['answers'])->mapWithKeys(fn ($a) => [$a['question_id'] => $a['choice']])->all();

        return response()->json(['success' => true, 'data' => $this->tests->submit($model, $map)]);
    }

    public function results(Request $request, Course $course): JsonResponse
    {
        $student = $this->student($request);
        $this->tests->assertAccess($student, $course);

        return response()->json(['success' => true, 'data' => TestAttempt::where('student_id', $student->id)->where('course_id', $course->id)
            ->whereNotNull('finished_at')->latest('id')->limit(20)->get(['id', 'total', 'correct_count', 'score', 'finished_at'])]);
    }

    public function books(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Book::active()->orderBy('name')->get(['id', 'name', 'url'])]);
    }

    private function student(Request $request)
    {
        abort_unless($request->user()->role === Role::Student, 403);

        return $request->user();
    }
}
