<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\StudentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v13: eslatmalar qo'ng'iroqchasi - filial doirasi, faolsizlantirish (o'chmaydi), sonning o'zgarishi. */
class V13NoteBellTest extends TestCase
{
    use RefreshDatabase;

    private function note(Branch $branch, User $student, User $author, string $body = 'Qo\'ng\'iroq qiling'): StudentNote
    {
        return StudentNote::create(['branch_id' => $branch->id, 'student_id' => $student->id, 'user_id' => $author->id, 'body' => $body]);
    }

    public function test_feed_shows_only_own_branch_active_notes_with_count(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $staff = $this->user(Role::Manager, $a, ['students.notes']);
        $sa = $this->student($a);
        $sb = $this->student($b);

        $this->note($a, $sa, $staff, 'A filial eslatmasi');
        $closed = $this->note($a, $sa, $staff, 'Yopilgan eslatma');
        $closed->forceFill(['closed_at' => now(), 'closed_by' => $staff->id])->save();
        $this->note($b, $sb, $staff, 'B filial eslatmasi');

        $this->actingAs($staff)->getJson('/notes/feed')->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.body', 'A filial eslatmasi')
            ->assertJsonPath('items.0.student', $sa->name);

        $this->actingAs($staff)->getJson('/notes/feed?status=closed')->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.body', 'Yopilgan eslatma');
    }

    public function test_close_keeps_note_and_changes_count_and_reopen_restores_it(): void
    {
        $branch = $this->branch();
        $staff = $this->user(Role::Manager, $branch, ['students.notes']);
        $student = $this->student($branch);
        $n1 = $this->note($branch, $student, $staff, 'Birinchi');
        $this->note($branch, $student, $staff, 'Ikkinchi');

        $this->actingAs($staff)->postJson("/notes/{$n1->id}/close")->assertOk()->assertJsonPath('count', 1);

        $n1->refresh();
        $this->assertNotNull($n1->closed_at);
        $this->assertSame($staff->id, $n1->closed_by);
        $this->assertSame(2, StudentNote::count(), 'Faolsizlantirilgan eslatma o\'chib ketmasligi kerak.');

        $this->actingAs($staff)->postJson("/notes/{$n1->id}/reopen")->assertOk()->assertJsonPath('count', 2);
        $this->assertNull($n1->fresh()->closed_at);
    }

    public function test_other_branch_note_cannot_be_closed(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $staff = $this->user(Role::Manager, $a, ['students.notes']);
        $foreign = $this->note($b, $this->student($b), $this->user(Role::Manager, $b, ['students.notes']));

        $this->actingAs($staff)->postJson("/notes/{$foreign->id}/close")->assertNotFound();
        $this->assertNull($foreign->fresh()->closed_at);
    }

    public function test_user_without_permission_has_no_bell_and_cannot_use_feed(): void
    {
        $branch = $this->branch();
        $staff = $this->user(Role::Manager, $branch, ['students.view']);

        $this->actingAs($staff)->getJson('/notes/feed')->assertForbidden();
        $this->actingAs($staff)->get('/')->assertOk()->assertDontSee('notesBell');
    }

    public function test_bell_is_rendered_with_permission(): void
    {
        $branch = $this->branch();
        $staff = $this->user(Role::Manager, $branch, ['students.notes']);

        $this->actingAs($staff)->get('/')->assertOk()->assertSee('notesBell', false);
    }

    public function test_sadmin_sees_selected_branch_notes_only(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $author = $this->user(Role::Manager, $a, ['students.notes']);
        $this->note($a, $this->student($a), $author, 'A');
        $this->note($b, $this->student($b), $author, 'B');
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->withSession(['current_branch_id' => $a->id])->getJson('/notes/feed')
            ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('items.0.body', 'A');

        $this->actingAs($sadmin)->withSession(['current_branch_id' => $b->id])->getJson('/notes/feed')
            ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('items.0.body', 'B');
    }
}
