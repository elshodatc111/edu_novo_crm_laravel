<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * v8 A6: `courses.view` ruxsati ilgari config/permissions.php'da e'lon qilingan va menejerga standart
 * berilgan edi (config/permissions.php -> defaults.manager), lekin hech qayerda tekshirilmasdi - shu
 * ruxsat bilan xodim kurslar ro'yxati va materiallar sahifasiga kira olmasdi (faqat courses.manage
 * tekshirilardi). Endi courses.view - faqat ko'rish, courses.manage - qo'shish/tahrirlash/o'chirish.
 */
class V8CoursesViewPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_only_manager_can_see_catalog_and_content_but_not_edit(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['courses.view']);
        $course = Course::create(['branch_id' => $branch->id, 'name' => 'Ingliz tili']);

        $catalog = $this->actingAs($manager)->get(route('catalog.index', 'courses'));
        $catalog->assertOk();
        $catalog->assertSee('Ingliz tili');
        $catalog->assertDontSee("Yangi: kurs");   // qo'shish shakli ko'rinmaydi
        $catalog->assertSee('Materiallar');       // lekin materiallarga o'tish havolasi bor

        $show = $this->actingAs($manager)->get(route('courses.show', $course));
        $show->assertOk();
        $show->assertDontSee("Video qo'shish", false);
        $show->assertDontSee("Audio qo'shish", false);
        $show->assertDontSee("Savol qo'shish", false);

        // Nav havolasi ko'rinishi kerak
        $this->actingAs($manager)->get(route('dashboard'))->assertSee('Kurslar');
    }

    public function test_view_only_manager_cannot_mutate_courses(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['courses.view']);
        $course = Course::create(['branch_id' => $branch->id, 'name' => 'Ingliz tili']);

        $this->actingAs($manager)->post(route('catalog.store', 'courses'), ['name' => 'Yangi kurs'])->assertForbidden();
        $this->actingAs($manager)->post(route('courses.videos.store', $course), [
            'number' => 1, 'title' => 'Dars 1', 'url' => 'https://example.com/v1',
        ])->assertForbidden();

        $this->assertDatabaseMissing('courses', ['name' => 'Yangi kurs']);
    }

    public function test_manager_without_any_course_permission_is_forbidden_and_sees_no_nav_link(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, []);
        $course = Course::create(['branch_id' => $branch->id, 'name' => 'Ingliz tili']);

        $this->actingAs($manager)->get(route('catalog.index', 'courses'))->assertForbidden();
        $this->actingAs($manager)->get(route('courses.show', $course))->assertForbidden();
        $this->actingAs($manager)->get(route('dashboard'))->assertDontSee('Kurslar');
    }

    public function test_manage_permission_still_gives_full_access_as_before(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['courses.manage']);
        $course = Course::create(['branch_id' => $branch->id, 'name' => 'Ingliz tili']);

        $catalog = $this->actingAs($manager)->get(route('catalog.index', 'courses'));
        $catalog->assertOk();
        $catalog->assertSee("Yangi: kurs");

        $show = $this->actingAs($manager)->get(route('courses.show', $course));
        $show->assertOk();
        $show->assertSee("Video qo'shish", false);
        $show->assertSee("Audio qo'shish", false);
        $show->assertSee("Savol qo'shish", false);

        $this->actingAs($manager)->post(route('courses.videos.store', $course), [
            'number' => 1, 'title' => 'Dars 1', 'url' => 'https://example.com/v1',
        ])->assertRedirect();
        $this->assertDatabaseHas('course_videos', ['course_id' => $course->id, 'title' => 'Dars 1']);
    }

    /** Boshqa katalog turlari (courses bo'lmagan) eskicha ishlayveradi - view_permission qo'shilmagan. */
    public function test_other_catalog_types_are_unaffected(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['settings.branch']);

        $response = $this->actingAs($admin)->get(route('catalog.index', 'rooms'));
        $response->assertOk();
        $response->assertSee('Yangi: xona');
    }
}
