<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\StudentNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v8 B8: xodimlar o'rtasidagi ichki eslatmalar (`students.notes` ruxsatini ishga tushiradi). */
class V8StudentNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_with_permission_can_add_a_note(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'students.notes']);
        $student = $this->student($branch);

        $this->actingAs($admin)
            ->post("/students/{$student->id}/notes", ['body' => "Ota-onasi bilan gaplashildi, keyingi hafta to'laydi."])
            ->assertRedirect();

        $note = StudentNote::first();
        $this->assertNotNull($note);
        $this->assertSame($student->id, $note->student_id);
        $this->assertSame($admin->id, $note->user_id);
        $this->assertSame($branch->id, $note->branch_id);
        $this->assertSame("Ota-onasi bilan gaplashildi, keyingi hafta to'laydi.", $note->body);
    }

    public function test_note_requires_non_empty_body(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'students.notes']);
        $student = $this->student($branch);

        $this->actingAs($admin)
            ->post("/students/{$student->id}/notes", ['body' => ''])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, StudentNote::count());
    }

    public function test_note_appears_on_student_page_with_author_and_date(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'students.notes'], ['name' => 'Dilnoza Yusupova']);
        $student = $this->student($branch);
        $this->actingAs($admin)->post("/students/{$student->id}/notes", ['body' => 'Sinov eslatmasi matni']);

        $this->get("/students/{$student->id}")
            ->assertOk()
            ->assertSee('Ichki eslatmalar')
            ->assertSee('Sinov eslatmasi matni')
            ->assertSee('Dilnoza Yusupova');
    }

    public function test_note_card_is_hidden_without_permission(): void
    {
        $branch = $this->branch();
        $viewer = $this->user(Role::Manager, $branch, ['students.view']);
        $student = $this->student($branch);

        $this->actingAs($viewer)->get("/students/{$student->id}")
            ->assertOk()
            ->assertDontSee('Ichki eslatmalar');
    }

    public function test_store_requires_students_notes_permission(): void
    {
        $branch = $this->branch();
        $viewer = $this->user(Role::Manager, $branch, ['students.view']);
        $student = $this->student($branch);

        $this->actingAs($viewer)
            ->post("/students/{$student->id}/notes", ['body' => 'ruxsatsiz urinish'])
            ->assertForbidden();
    }

    public function test_staff_with_permission_can_delete_a_note(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'students.notes']);
        $student = $this->student($branch);
        $this->actingAs($admin)->post("/students/{$student->id}/notes", ['body' => "O'chiriladigan eslatma"]);
        $note = StudentNote::firstOrFail();

        $this->actingAs($admin)
            ->delete("/students/{$student->id}/notes/{$note->id}")
            ->assertRedirect();

        $this->assertSame(0, StudentNote::count());
    }

    public function test_destroy_requires_students_notes_permission(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'students.notes']);
        $student = $this->student($branch);
        $this->actingAs($admin)->post("/students/{$student->id}/notes", ['body' => 'eslatma']);
        $note = StudentNote::firstOrFail();

        $viewer = $this->user(Role::Manager, $branch, ['students.view']);
        $this->actingAs($viewer)
            ->delete("/students/{$student->id}/notes/{$note->id}")
            ->assertForbidden();

        $this->assertSame(1, StudentNote::count());
    }

    public function test_notes_are_branch_scoped(): void
    {
        $branchA = $this->branch('A');
        $branchB = $this->branch('B');
        $adminA = $this->user(Role::Admin, $branchA, ['students.view', 'students.notes']);
        $adminB = $this->user(Role::Admin, $branchB, ['students.view', 'students.notes']);
        $studentA = $this->student($branchA);
        $this->actingAs($adminA)->post("/students/{$studentA->id}/notes", ['body' => 'A filiali eslatmasi']);
        $note = StudentNote::firstOrFail();

        // Boshqa filial admini o'quvchining o'ziga ham, eslatmasiga ham yeta olmaydi.
        $this->actingAs($adminB)->get("/students/{$studentA->id}")->assertNotFound();
        $this->actingAs($adminB)->post("/students/{$studentA->id}/notes", ['body' => 'x'])->assertNotFound();
        $this->actingAs($adminB)->delete("/students/{$studentA->id}/notes/{$note->id}")->assertNotFound();

        // Hisoblashda joriy (adminB, B filiali) filial kontekstidan mustaqil tekshiramiz.
        $this->assertSame(1, StudentNote::withoutGlobalScopes()->count());
    }

    public function test_note_cannot_be_deleted_via_mismatched_student_in_url(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'students.notes']);
        $studentOne = $this->student($branch);
        $studentTwo = $this->student($branch);
        $this->actingAs($admin)->post("/students/{$studentOne->id}/notes", ['body' => "Birinchi o'quvchi eslatmasi"]);
        $note = StudentNote::firstOrFail();

        $this->actingAs($admin)
            ->delete("/students/{$studentTwo->id}/notes/{$note->id}")
            ->assertNotFound();

        $this->assertSame(1, StudentNote::count());
    }

    public function test_notes_are_independent_from_the_about_field(): void
    {
        $branch = $this->branch();
        $admin = $this->user(Role::Admin, $branch, ['students.view', 'students.notes', 'students.update']);
        $student = $this->student($branch, ['about' => 'Profil eslatmasi (about)']);

        $this->actingAs($admin)->post("/students/{$student->id}/notes", ['body' => 'Ichki eslatma matni']);

        $resp = $this->get("/students/{$student->id}")->assertOk();
        $resp->assertSee('Profil eslatmasi (about)')->assertSee('Ichki eslatma matni');
        $this->assertSame('Profil eslatmasi (about)', $student->fresh()->about);
    }
}
