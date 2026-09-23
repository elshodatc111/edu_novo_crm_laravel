<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Concerns\BelongsToBranch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

class ScopedThing extends Model
{
    use BelongsToBranch;

    protected $table = 'scoped_things';
    protected $guarded = [];
}

class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('scoped_things', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('branch_id');
            $t->string('title');
            $t->timestamps();
        });
    }

    public function test_staff_only_sees_own_branch_rows(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        ScopedThing::create(['branch_id' => $a->id, 'title' => 'a1']);
        ScopedThing::create(['branch_id' => $b->id, 'title' => 'b1']);

        $admin = $this->user(Role::Admin, $a);

        $this->actingAs($admin);
        $this->assertSame(['a1'], ScopedThing::pluck('title')->all());
    }

    public function test_new_rows_get_users_branch_automatically(): void
    {
        $a = $this->branch('A');
        $this->actingAs($this->user(Role::Manager, $a));

        $this->assertSame($a->id, ScopedThing::create(['title' => 'x'])->branch_id);
    }

    public function test_sadmin_sees_all_or_selected_branch(): void
    {
        $a = $this->branch('A');
        $b = $this->branch('B');
        ScopedThing::create(['branch_id' => $a->id, 'title' => 'a1']);
        ScopedThing::create(['branch_id' => $b->id, 'title' => 'b1']);

        $this->actingAs($this->user(Role::SAdmin));
        $this->assertCount(2, ScopedThing::all());

        BranchContext::select($b->id);
        $this->assertSame(['b1'], ScopedThing::pluck('title')->all());
    }

    public function test_sadmin_must_pick_branch_before_creating(): void
    {
        $this->actingAs($this->user(Role::SAdmin));

        $this->expectException(LogicException::class);
        ScopedThing::create(['title' => 'x']);
    }

    public function test_user_without_branch_sees_nothing(): void
    {
        $a = $this->branch('A');
        ScopedThing::create(['branch_id' => $a->id, 'title' => 'a1']);

        $orphan = $this->user(Role::Admin, $a);
        $orphan->forceFill(['branch_id' => null])->save();

        $this->actingAs($orphan);
        $this->assertCount(0, ScopedThing::all());
    }
}
