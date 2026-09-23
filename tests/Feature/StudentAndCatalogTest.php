<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Holiday;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StudentAndCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
    }

    public function test_manager_creates_student_with_generated_login_and_password(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['students.view', 'students.create']);

        $response = $this->actingAs($manager)->post('/students', ['name' => 'ali valiyev', 'phone' => '+998 90 123 4567']);

        $student = User::where('role', 'student')->firstOrFail();
        $response->assertRedirect(route('students.show', $student))->assertSessionHas('credentials');
        $this->assertSame('ALI VALIYEV', $student->name);
        $this->assertSame('998901234567', $student->username);
        $this->assertSame($branch->id, $student->branch_id);

        $password = session('credentials')['password'];
        $this->postJson('/api/v1/auth/login', ['login' => '998901234567', 'password' => $password])->assertOk();

        // Bir filialda bir xil asosiy telefon bilan ikkinchi o'quvchi bo'lmaydi
        $this->actingAs($manager)->post('/students', ['name' => 'Aka', 'phone' => '+998 90 123 4567'])->assertSessionHasErrors('phone');
        $this->assertSame(1, User::where('role', 'student')->count());

        // Boshqa filialda shu raqam bo'la oladi (login takrorlanmasligi uchun -2 qo'shiladi)
        $other = $this->user(Role::Manager, $this->branch('Boshqa'), ['students.view', 'students.create']);
        $this->actingAs($other)->post('/students', ['name' => 'Aka', 'phone' => '+998 90 123 4567'])->assertRedirect();
        $this->assertSame(['998901234567', '998901234567-2'], User::where('role', 'student')->orderBy('id')->pluck('username')->all());
    }

    public function test_student_pages_are_scoped_and_permissioned(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $mine = $this->student($a, ['name' => 'Mening', 'balance' => -1000]);
        $foreign = $this->student($b, ['name' => 'Begona']);

        $admin = $this->user(Role::Admin, $a, ['students.view', 'students.update']);

        $this->actingAs($admin)->get('/students')->assertOk()->assertSee('Mening')->assertDontSee('Begona');
        $this->actingAs($admin)->get('/students?status=debt')->assertOk()->assertSee('Mening');
        $this->actingAs($admin)->get("/students/{$mine->id}")->assertOk();
        $this->actingAs($admin)->get("/students/{$foreign->id}")->assertNotFound();

        $teacher = $this->user(Role::Teacher, $a, ['attendance.view']);
        $this->actingAs($teacher)->get('/students')->assertForbidden();

        $noCreate = $this->user(Role::Manager, $a, ['students.view']);
        $this->actingAs($noCreate)->post('/students', ['name' => 'X', 'phone' => '+998 90 111 2233'])->assertForbidden();
    }

    public function test_student_update_and_password_reset(): void
    {
        $branch = $this->branch();
        $student = $this->student($branch);
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'students.update']);

        $this->actingAs($admin)->put("/students/{$student->id}", ['name' => 'yangi ism', 'phone' => '+998 90 123 4567'])->assertRedirect();
        $this->assertSame('YANGI ISM', $student->fresh()->name);

        $this->actingAs($admin)->post("/students/{$student->id}/reset-password")->assertSessionHas('credentials');
    }

    public function test_settings_require_permission_and_sadmin_needs_branch(): void
    {
        $branch = $this->branch();
        $manager = $this->user(Role::Manager, $branch, ['students.view']);
        $admin = $this->user(Role::Admin, $branch, ['settings.branch', 'courses.manage']);

        $this->actingAs($manager)->get('/settings/rooms')->assertForbidden();
        $this->actingAs($admin)->get('/settings/rooms')->assertOk();
        $this->actingAs($admin)->get('/settings/courses')->assertOk();

        $sadmin = $this->user(Role::SAdmin);
        $this->actingAs($sadmin)->get('/settings/rooms')->assertRedirect(route('dashboard'));
        $this->actingAs($sadmin)->withSession(['current_branch_id' => $branch->id])->get('/settings/rooms')->assertOk();
    }

    public function test_catalog_crud_and_uniqueness(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['settings.branch']);
        $this->actingAs($admin);

        $this->post('/settings/rooms', ['name' => '1-xona'])->assertSessionHasNoErrors();
        $this->post('/settings/rooms', ['name' => '1-xona'])->assertSessionHasErrors('name');

        $room = Room::firstOrFail();
        $this->put("/settings/rooms/{$room->id}", ['name' => 'Katta xona'])->assertRedirect();
        $this->assertSame('Katta xona', $room->fresh()->name);

        $this->post("/settings/rooms/{$room->id}/toggle");
        $this->assertFalse($room->fresh()->is_active);

        $this->post('/settings/lesson-times', ['starts_at' => '10:30', 'ends_at' => '09:00'])->assertSessionHasErrors('ends_at');
        $this->post('/settings/lesson-times', ['starts_at' => '09:00', 'ends_at' => '10:30'])->assertSessionHasNoErrors();
        $this->post('/settings/price-plans', ['name' => 'Std', 'amount' => 500000, 'early_discount' => 600000, 'max_discount' => 0])->assertSessionHasErrors('early_discount');
        $this->post('/settings/price-plans', ['name' => 'Std', 'amount' => 500000, 'early_discount' => 50000, 'max_discount' => 100000])->assertSessionHasNoErrors();

        // Boshqa filialdagi yozuvni o'zgartirib bo'lmaydi
        $other = Room::create(['branch_id' => $this->branch('B')->id, 'name' => 'Boshqa']);
        $this->put("/settings/rooms/{$other->id}", ['name' => 'Hack'])->assertNotFound();
    }

    public function test_holidays_generate_is_idempotent_and_used_by_groups(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['settings.branch']);
        $this->actingAs($admin);

        $this->post('/settings/holidays/generate')->assertRedirect();
        $count = Holiday::count();
        $this->assertGreaterThan(50, $count);
        $this->assertTrue(Holiday::whereDate('date', '2026-09-27')->exists());   // yakshanba
        $this->assertTrue(Holiday::whereDate('date', '2026-10-01')->exists());   // O'qituvchilar kuni

        $this->post('/settings/holidays/generate');
        $this->assertSame($count, Holiday::count());

        $this->post('/settings/holidays', ['date' => '2026-11-05', 'comment' => 'Sinov'])->assertSessionHasNoErrors();
        $this->post('/settings/holidays', ['date' => '2026-11-05'])->assertSessionHasErrors('date');
        $this->get('/settings/holidays')->assertOk();
    }
}
