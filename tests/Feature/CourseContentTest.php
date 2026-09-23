<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Course;
use App\Models\CourseAudio;
use App\Models\CourseQuestion;
use App\Models\CourseVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseContentTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $admin;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = $this->branch();
        $this->admin = $this->user(Role::Admin, $this->branch, ['courses.manage']);
        $this->course = Course::create(['branch_id' => $this->branch->id, 'name' => 'TOPIK 1']);
    }

    public function test_video_crud_and_unique_number(): void
    {
        $this->actingAs($this->admin)->get("/courses/{$this->course->id}")->assertOk()->assertSee('TOPIK 1');

        $this->post("/courses/{$this->course->id}/videos", ['number' => 1, 'title' => '1-dars', 'url' => 'https://youtu.be/abc'])->assertSessionHasNoErrors();
        $this->post("/courses/{$this->course->id}/videos", ['number' => 1, 'title' => 'Takror', 'url' => 'https://youtu.be/xyz'])->assertSessionHasErrors('number');
        $this->post("/courses/{$this->course->id}/videos", ['number' => 2, 'title' => 'Xato', 'url' => 'havola'])->assertSessionHasErrors('url');

        $video = CourseVideo::firstOrFail();
        $this->delete("/courses/{$this->course->id}/videos/{$video->id}")->assertRedirect();
        $this->assertSame(0, CourseVideo::count());
    }

    public function test_audio_upload_and_delete_removes_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        $this->post("/courses/{$this->course->id}/audios", ['number' => 1, 'title' => 'Tinglash', 'file' => UploadedFile::fake()->create('a.mp3', 500, 'audio/mpeg')])->assertSessionHasNoErrors();
        $audio = CourseAudio::firstOrFail();
        $this->assertFalse($audio->is_external);
        Storage::disk('public')->assertExists($audio->path);
        $this->assertStringContainsString('/storage/audio/', $audio->url);

        $this->post("/courses/{$this->course->id}/audios", ['number' => 2, 'title' => 'Havola', 'url' => 'https://example.com/a.mp3'])->assertSessionHasNoErrors();
        $this->assertTrue(CourseAudio::where('number', 2)->firstOrFail()->is_external);

        $this->post("/courses/{$this->course->id}/audios", ['number' => 3, 'title' => 'Bo\'sh'])->assertSessionHasErrors(['file', 'url']);
        $this->post("/courses/{$this->course->id}/audios", ['number' => 4, 'title' => 'Exe', 'file' => UploadedFile::fake()->create('x.exe', 10)])->assertSessionHasErrors('file');

        $this->delete("/courses/{$this->course->id}/audios/{$audio->id}");
        Storage::disk('public')->assertMissing($audio->path);
    }

    public function test_question_validation_and_delete(): void
    {
        $this->actingAs($this->admin);

        $this->post("/courses/{$this->course->id}/questions", ['question' => '2+2?', 'correct' => '4', 'wrong' => ['3', '5', '6']])->assertSessionHasNoErrors();
        $this->post("/courses/{$this->course->id}/questions", ['question' => 'X?', 'correct' => '4', 'wrong' => ['4', '5', '6']])->assertSessionHasErrors('correct');
        $this->post("/courses/{$this->course->id}/questions", ['question' => 'X?', 'correct' => '4', 'wrong' => ['3', '3', '6']])->assertSessionHasErrors();
        $this->post("/courses/{$this->course->id}/questions", ['question' => 'X?', 'correct' => '4', 'wrong' => ['3', '5']])->assertSessionHasErrors('wrong');

        $this->assertSame(1, CourseQuestion::count());
        $q = CourseQuestion::firstOrFail();
        $this->delete("/courses/{$this->course->id}/questions/{$q->id}")->assertRedirect();
        $this->assertSame(0, CourseQuestion::count());
    }

    public function test_permission_and_branch_scope(): void
    {
        // v8: courses.view - faqat ko'rish uchun yetadi (V8CoursesViewPermissionTest'da alohida tekshirilgan);
        // bu yerda hech qanday kurs ruxsati bo'lmagan menejer haqiqatan 403 olishini tekshiramiz.
        $manager = $this->user(Role::Manager, $this->branch, []);
        $this->actingAs($manager)->get("/courses/{$this->course->id}")->assertForbidden();

        $foreign = $this->user(Role::Admin, $this->branch('Boshqa'), ['courses.manage']);
        $this->actingAs($foreign)->get("/courses/{$this->course->id}")->assertNotFound();

        // Boshqa kursning videosini o'chirib bo'lmaydi
        $other = Course::create(['branch_id' => $this->branch->id, 'name' => 'Boshqa kurs']);
        $video = CourseVideo::create(['branch_id' => $this->branch->id, 'course_id' => $other->id, 'number' => 1, 'title' => 'x', 'url' => 'https://a.uz']);
        $this->actingAs($this->admin)->delete("/courses/{$this->course->id}/videos/{$video->id}")->assertNotFound();
    }

    public function test_courses_catalog_links_to_materials_and_books_catalog(): void
    {
        $admin = $this->user(Role::Admin, $this->branch, ['courses.manage', 'settings.branch']);
        $this->actingAs($admin)->get('/settings/courses')->assertOk()->assertSee('Materiallar');
        $this->post('/settings/books', ['name' => 'Koreys tili 1', 'url' => 'https://example.com/book.pdf'])->assertSessionHasNoErrors();
        $this->post('/settings/books', ['name' => 'Xato', 'url' => 'havola'])->assertSessionHasErrors('url');
        $this->get('/settings/books')->assertOk()->assertSee('Koreys tili 1');
    }
}
