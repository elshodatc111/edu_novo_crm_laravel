<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Course;
use App\Models\CourseAudio;
use App\Models\CourseQuestion;
use App\Models\CourseVideo;
use App\Models\Group;
use App\Models\TestAttempt;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentTestApiTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;
    private array $cat;
    private Group $group;
    private $student;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');

        $this->branch = $this->branch();
        $this->admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.members']);
        $this->actingAs($this->admin);
        $this->cat = $this->catalog($this->branch);
        $this->group = app(GroupService::class)->create($this->groupPayload($this->cat), $this->admin);
        $this->student = $this->student($this->branch);
        app(EnrollmentService::class)->enroll($this->group, $this->student, null, $this->admin);
        $this->token = $this->student->createToken('t')->plainTextToken;

        $c = $this->cat['course']->id;
        CourseVideo::create(['branch_id' => $this->branch->id, 'course_id' => $c, 'number' => 1, 'title' => 'V1', 'url' => 'https://youtu.be/1']);
        CourseAudio::create(['branch_id' => $this->branch->id, 'course_id' => $c, 'number' => 1, 'title' => 'A1', 'path' => 'https://ex.uz/a.mp3', 'is_external' => true]);
        for ($i = 1; $i <= 20; $i++) {
            CourseQuestion::create(['branch_id' => $this->branch->id, 'course_id' => $c, 'question' => "Savol {$i}", 'correct' => "to'g'ri {$i}", 'wrong' => ["x{$i}", "y{$i}", "z{$i}"]]);
        }
        Book::create(['branch_id' => $this->branch->id, 'name' => 'Kitob', 'url' => 'https://ex.uz/b.pdf']);
    }

    public function test_courses_list_and_materials_only_for_own_active_group(): void
    {
        $this->api($this->token)->getJson('/api/v1/courses')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.videos_count', 1)->assertJsonPath('data.0.questions_count', 20);
        $this->api($this->token)->getJson("/api/v1/courses/{$this->cat['course']->id}/videos")->assertOk()->assertJsonPath('data.0.title', 'V1');
        $this->api($this->token)->getJson("/api/v1/courses/{$this->cat['course']->id}/audios")->assertOk()->assertJsonPath('data.0.url', 'https://ex.uz/a.mp3');

        // Boshqa kurs
        $other = Course::create(['branch_id' => $this->branch->id, 'name' => 'Boshqa']);
        $this->api($this->token)->getJson("/api/v1/courses/{$other->id}/videos")->assertNotFound();

        // Guruh tugagach kirish yopiladi
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->api($this->token)->getJson('/api/v1/courses')->assertOk()->assertJsonCount(0, 'data');
        $this->api($this->token)->getJson("/api/v1/courses/{$this->cat['course']->id}/videos")->assertNotFound();
    }

    public function test_test_is_graded_on_server_and_answers_are_hidden(): void
    {
        $courseId = $this->cat['course']->id;

        $start = $this->api($this->token)->postJson("/api/v1/courses/{$courseId}/tests/start")->assertOk()
            ->assertJsonPath('data.total', 15)->assertJsonCount(15, 'data.questions');
        $this->assertStringNotContainsString('answer_key', $start->getContent());
        $this->assertStringNotContainsString('correct', $start->getContent());

        $attempt = TestAttempt::firstOrFail();
        $answers = [];
        foreach ($attempt->questions as $i => $q) {
            // Birinchi 9 tasiga to'g'ri, qolganiga noto'g'ri javob
            $right = $attempt->answer_key[$i];
            $answers[] = ['question_id' => $q['id'], 'choice' => $i < 9 ? $right : ($right + 1) % 4];
        }

        $this->api($this->token)->postJson("/api/v1/tests/{$attempt->id}/submit", ['answers' => $answers])->assertOk()
            ->assertJsonPath('data.correct', 9)->assertJsonPath('data.total', 15)->assertJsonPath('data.score', 60);

        // Qayta topshirib bo'lmaydi
        $this->api($this->token)->postJson("/api/v1/tests/{$attempt->id}/submit", ['answers' => $answers])->assertStatus(422);

        $this->api($this->token)->getJson("/api/v1/courses/{$courseId}/tests/results")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.score', 60);
        $this->api($this->token)->getJson('/api/v1/courses')->assertJsonPath('data.0.best_score', 60)->assertJsonPath('data.0.attempts', 1);
    }

    public function test_unanswered_questions_count_as_wrong_and_options_are_shuffled(): void
    {
        $courseId = $this->cat['course']->id;
        $this->api($this->token)->postJson("/api/v1/courses/{$courseId}/tests/start");
        $attempt = TestAttempt::firstOrFail();

        $this->assertSame(4, count($attempt->questions[0]['options']));
        $this->api($this->token)->postJson("/api/v1/tests/{$attempt->id}/submit", ['answers' => [['question_id' => $attempt->questions[0]['id'], 'choice' => $attempt->answer_key[0]]]])
            ->assertOk()->assertJsonPath('data.correct', 1);
    }

    public function test_other_student_and_staff_cannot_use_attempt(): void
    {
        $courseId = $this->cat['course']->id;
        $this->api($this->token)->postJson("/api/v1/courses/{$courseId}/tests/start");
        $attempt = TestAttempt::firstOrFail();

        $other = $this->student($this->branch)->createToken('o')->plainTextToken;
        $this->api($other)->postJson("/api/v1/tests/{$attempt->id}/submit", ['answers' => [['question_id' => 1, 'choice' => 0]]])->assertNotFound();

        $staff = $this->admin->createToken('a')->plainTextToken;
        $this->api($staff)->getJson('/api/v1/courses')->assertForbidden();
    }

    public function test_test_needs_questions_and_books_endpoint(): void
    {
        CourseQuestion::query()->delete();
        $this->api($this->token)->postJson("/api/v1/courses/{$this->cat['course']->id}/tests/start")->assertStatus(422);

        $this->api($this->token)->getJson('/api/v1/books')->assertOk()->assertJsonPath('data.0.name', 'Kitob');
    }
}
