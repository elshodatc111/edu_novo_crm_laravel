<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseAudio;
use App\Models\CourseQuestion;
use App\Models\CourseVideo;
use App\Services\AiQuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Kurs materiallari: video, audio va test savollari. */
class CourseContentController extends Controller
{
    public function show(Request $request, Course $course)
    {
        abort_unless($request->user()->can('courses.view') || $request->user()->can('courses.manage'), 403);

        return view('courses.show', [
            'course' => $course,
            'videos' => $course->videos()->get(),
            'audios' => $course->audios()->get(),
            'questions' => $course->questions()->latest('id')->get(),
            'nextVideo' => (int) $course->videos()->max('number') + 1,
            'nextAudio' => (int) $course->audios()->max('number') + 1,
        ]);
    }

    public function storeVideo(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('courses.manage');

        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:1000', Rule::unique('course_videos', 'number')->where('course_id', $course->id)],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:500'],
        ], [], ['number' => 'Dars raqami', 'title' => 'Nomi', 'url' => 'Video havolasi']);

        CourseVideo::create($data + ['course_id' => $course->id, 'created_by' => $request->user()->id]);

        return back()->with('success', "Video qo'shildi.");
    }

    public function destroyVideo(Course $course, CourseVideo $video): RedirectResponse
    {
        $this->authorize('courses.manage');
        abort_unless($video->course_id === $course->id, 404);

        $video->delete();

        return back()->with('success', "Video o'chirildi.");
    }

    public function storeAudio(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('courses.manage');

        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:1000', Rule::unique('course_audios', 'number')->where('course_id', $course->id)],
            'title' => ['required', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:500', 'required_without:file'],
            'file' => ['nullable', 'file', 'mimes:mp3,wav,m4a,ogg,aac', 'max:51200', 'required_without:url'],
        ], [], ['number' => 'Audio raqami', 'title' => 'Nomi', 'url' => 'Havola', 'file' => 'Audio fayl']);

        $external = ! $request->hasFile('file');
        $path = $external ? $data['url'] : $request->file('file')->store("audio/{$course->branch_id}", 'public');

        CourseAudio::create([
            'course_id' => $course->id, 'number' => $data['number'], 'title' => $data['title'],
            'path' => $path, 'is_external' => $external, 'created_by' => $request->user()->id,
        ]);

        return back()->with('success', "Audio qo'shildi.");
    }

    public function destroyAudio(Course $course, CourseAudio $audio): RedirectResponse
    {
        $this->authorize('courses.manage');
        abort_unless($audio->course_id === $course->id, 404);

        if (! $audio->is_external) {
            Storage::disk('public')->delete($audio->path);
        }
        $audio->delete();

        return back()->with('success', "Audio o'chirildi.");
    }

    public function storeQuestion(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('courses.manage');

        $data = $request->validate([
            'question' => ['required', 'string', 'max:2000'],
            'correct' => ['required', 'string', 'max:500'],
            'wrong' => ['required', 'array', 'size:3'],
            'wrong.*' => ['required', 'string', 'max:500', 'distinct'],
        ], [], ['question' => 'Savol', 'correct' => "To'g'ri javob", 'wrong' => "Noto'g'ri javoblar", 'wrong.*' => "Noto'g'ri javob"]);

        if (in_array($data['correct'], $data['wrong'], true)) {
            return back()->withErrors(['correct' => "To'g'ri javob noto'g'ri javoblardan farq qilishi kerak."])->withInput();
        }

        CourseQuestion::create($data + ['course_id' => $course->id, 'created_by' => $request->user()->id]);
        AuditLog::record('course.question_added', $course, "Test savoli qo'shildi ({$course->name})");

        return back()->with('success', "Savol qo'shildi.");
    }

    public function destroyQuestion(Course $course, CourseQuestion $question): RedirectResponse
    {
        $this->authorize('courses.manage');
        abort_unless($question->course_id === $course->id, 404);

        $question->delete();

        return back()->with('success', "Savol o'chirildi.");
    }

    /** AI yordamida savol loyihalari yaratadi (hali saqlanmaydi, xodim ko'rib chiqadi). */
    public function aiGenerate(Request $request, Course $course, AiQuestionService $ai): RedirectResponse
    {
        $this->authorize('courses.manage');

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:200'],
            'count' => ['required', 'integer', 'min:1', 'max:10'],
            'level' => ['required', Rule::in(['boshlang\'ich', 'o\'rta', 'yuqori'])],
        ], [], ['topic' => 'Mavzu', 'count' => 'Savollar soni', 'level' => 'Daraja']);

        $questions = $ai->generate($course, $data['topic'], (int) $data['count'], $data['level']);

        return back()->with('ai_questions', $questions);
    }

    /** Ko'rib chiqilgan AI savollaridan tanlanganlarini saqlaydi. */
    public function storeAiQuestions(Request $request, Course $course): RedirectResponse
    {
        $this->authorize('courses.manage');

        $data = $request->validate([
            'questions' => ['required', 'array', 'min:1', 'max:10'],
            'questions.*.question' => ['required', 'string', 'max:2000'],
            'questions.*.correct' => ['required', 'string', 'max:500'],
            'questions.*.wrong' => ['required', 'array', 'size:3'],
            'questions.*.wrong.*' => ['required', 'string', 'max:500'],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer'],
        ], ['selected.required' => 'Kamida bitta savolni belgilang.'], ['selected' => 'Savollar']);

        $saved = 0;
        foreach ($data['selected'] as $i) {
            $q = $data['questions'][$i] ?? null;
            if ($q && ! in_array($q['correct'], $q['wrong'], true)) {
                CourseQuestion::create($q + ['course_id' => $course->id, 'created_by' => $request->user()->id]);
                $saved++;
            }
        }

        AuditLog::record('course.ai_questions_added', $course, "AI yordamida {$saved} ta savol qo'shildi ({$course->name})");

        return back()->with('success', "{$saved} ta savol qo'shildi.");
    }
}
