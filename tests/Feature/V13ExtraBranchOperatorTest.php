<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** v13: operatorga qo'shimcha filiallar - sAdmin beradi/oladi, izolyatsiya buzilmaydi. */
class V13ExtraBranchOperatorTest extends TestCase
{
    use RefreshDatabase;

    private function grant(User $operator, array $branchIds): void
    {
        foreach ($branchIds as $id) {
            DB::table('user_branches')->insert(['user_id' => $operator->id, 'branch_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_operator_without_extra_branches_cannot_switch_and_sees_only_own_branch(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $op = $this->user(Role::Operator, $a, ['students.view']);
        $this->student($a, ['name' => 'Alpha Talaba']);
        $this->student($b, ['name' => 'Beta Talaba']);

        $this->actingAs($op)->post('/branch/switch', ['branch_id' => $b->id])->assertForbidden();
        $this->actingAs($op)->get('/students')->assertOk()->assertSee('Alpha Talaba')->assertDontSee('Beta Talaba');
    }

    public function test_operator_with_extra_branch_can_switch_only_to_granted_branches(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $c = $this->branch('C');
        $op = $this->user(Role::Operator, $a, ['students.view']);
        $this->grant($op, [$b->id]);
        $this->student($a, ['name' => 'Alpha Talaba']);
        $this->student($b, ['name' => 'Beta Talaba']);
        $this->student($c, ['name' => 'Gamma Talaba']);

        // Standart: o'z filiali
        $this->actingAs($op)->get('/students')->assertSee('Alpha Talaba')->assertDontSee('Beta Talaba');

        // Berilgan filialga o'tadi
        $this->actingAs($op)->post('/branch/switch', ['branch_id' => $b->id])->assertRedirect();
        $this->actingAs($op)->get('/students')->assertOk()->assertSee('Beta Talaba')->assertDontSee('Alpha Talaba')->assertDontSee('Gamma Talaba');

        // Berilmagan filialga o'ta olmaydi
        $this->actingAs($op)->post('/branch/switch', ['branch_id' => $c->id])->assertForbidden();
        $this->actingAs($op)->get('/students')->assertDontSee('Gamma Talaba');
    }

    public function test_header_selects_extra_branch_and_foreign_header_is_ignored(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $c = $this->branch('C');
        $op = $this->user(Role::Operator, $a);
        $this->grant($op, [$b->id]);

        $this->actingAs($op);
        $this->assertSame($a->id, BranchContext::id());

        $this->assertSame($b->id, $this->resolveWithHeader($b->id));
        $this->assertSame($a->id, $this->resolveWithHeader($c->id), "Ruxsat berilmagan filial sarlavhasi e'tiborga olinmaydi");
    }

    private function resolveWithHeader(int $id): int
    {
        request()->headers->set(BranchContext::HEADER, (string) $id);
        BranchContext::forgetExtra(auth()->user());

        return (int) BranchContext::id();
    }

    public function test_only_sadmin_assigns_extra_branches_and_can_revoke(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $sadmin = $this->user(Role::SAdmin);
        $admin = $this->user(Role::Admin, $a, ['staff.manage', 'staff.view']);
        $op = $this->user(Role::Operator, $a, [], ['phone' => '+998 90 111 2233']);

        $payload = ['name' => $op->name, 'username' => $op->username, 'phone' => '+998 90 111 2233', 'status' => 'active'];

        // Admin bera olmaydi
        $this->actingAs($admin)->put(route('staff.update', $op), $payload + ['extra_branches' => [$b->id]])->assertSessionHasErrors('extra_branches');
        $this->assertSame(0, DB::table('user_branches')->count());

        // sAdmin beradi
        $this->actingAs($sadmin)->put(route('staff.update', $op), $payload + ['extra_branches_form' => 1, 'extra_branches' => [$b->id]])->assertRedirect(route('staff.index'));
        $this->assertSame([$b->id], DB::table('user_branches')->where('user_id', $op->id)->pluck('branch_id')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.branches_changed']);

        // sAdmin olib tashlaydi (hech biri belgilanmagan)
        $this->actingAs($sadmin)->put(route('staff.update', $op), $payload + ['extra_branches_form' => 1])->assertRedirect(route('staff.index'));
        $this->assertSame(0, DB::table('user_branches')->where('user_id', $op->id)->count());
    }

    public function test_extra_branches_are_cleared_when_role_changes_away_from_operator(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $sadmin = $this->user(Role::SAdmin);
        $op = $this->user(Role::Operator, $a, [], ['phone' => '+998 90 111 2233']);
        $this->grant($op, [$b->id]);

        $this->actingAs($sadmin)->put(route('staff.update', $op), ['name' => $op->name, 'username' => $op->username, 'phone' => '+998 90 111 2233', 'status' => 'active', 'role' => 'manager'])
            ->assertRedirect(route('staff.index'));

        $this->assertSame(Role::Manager, $op->fresh()->role);
        $this->assertSame(0, DB::table('user_branches')->where('user_id', $op->id)->count());
    }

    public function test_sadmin_creates_operator_with_extra_branches(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post('/staff', [
            'name' => 'Umumiy Operator', 'username' => 'umumiy.op', 'phone' => '+998 90 222 3344', 'password' => 'parol12345', 'password_confirmation' => 'parol12345',
            'status' => 'active', 'role' => 'operator', 'branch_id' => $a->id, 'extra_branches_form' => 1, 'extra_branches' => [$a->id, $b->id],
        ])->assertRedirect(route('staff.index'));

        $op = User::where('username', 'umumiy.op')->firstOrFail();
        // o'z filiali qo'shimchaga yozilmaydi
        $this->assertSame([$b->id], DB::table('user_branches')->where('user_id', $op->id)->pluck('branch_id')->all());
    }

    public function test_archived_extra_branch_is_not_accessible(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        $op = $this->user(Role::Operator, $a);
        $this->grant($op, [$b->id]);
        $b->forceFill(['status' => 'closed'])->save();

        $this->actingAs($op)->post('/branch/switch', ['branch_id' => $b->id])->assertForbidden();
    }
}
