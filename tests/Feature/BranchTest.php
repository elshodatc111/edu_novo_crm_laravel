<?php

namespace Tests\Feature;

use App\Enums\BranchStatus;
use App\Enums\Role;
use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_sadmin_can_manage_branches(): void
    {
        $admin = $this->user(Role::Admin, permissions: ['staff.view']);

        $this->actingAs($admin)->get('/branches')->assertForbidden();
        $this->actingAs($admin)->post('/branches', ['name' => 'Yangi'])->assertForbidden();
    }

    public function test_sadmin_creates_updates_closes_and_reopens_branch(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post('/branches', ['name' => 'Samarqand filiali', 'phone' => '+998 90 111 2233'])
            ->assertRedirect(route('branches.index'));

        $branch = Branch::where('name', 'Samarqand filiali')->firstOrFail();
        $this->assertSame('samarqand-filiali', $branch->code);
        $this->assertTrue($branch->isActive());

        $this->actingAs($sadmin)->put("/branches/{$branch->id}", [
            'name' => 'Samarqand filiali', 'eskiz_email' => 'sms@edunova.uz', 'eskiz_password' => 'maxfiy', 'eskiz_from' => '4546',
        ])->assertRedirect();
        $this->assertTrue($branch->fresh()->hasOwnSmsAccount());
        $this->assertNotSame('maxfiy', $branch->getRawOriginal('eskiz_password'));

        // Parol bo'sh qoldirilsa saqlanib qoladi
        $this->actingAs($sadmin)->put("/branches/{$branch->id}", ['name' => 'Samarqand filiali', 'eskiz_email' => 'sms@edunova.uz', 'eskiz_password' => ''])->assertRedirect();
        $this->assertSame('maxfiy', $branch->fresh()->eskiz_password);

        // Eskiz email o'chirilsa umumiy akkauntga qaytadi
        $this->actingAs($sadmin)->put("/branches/{$branch->id}", ['name' => 'Samarqand filiali'])->assertRedirect();
        $this->assertFalse($branch->fresh()->hasOwnSmsAccount());

        $this->actingAs($sadmin)->post("/branches/{$branch->id}/close", ['closed_reason' => 'Ijara tugadi'])->assertRedirect();
        $this->assertSame(BranchStatus::Closed, $branch->fresh()->status);

        $this->actingAs($sadmin)->post("/branches/{$branch->id}/reopen")->assertRedirect();
        $this->assertTrue($branch->fresh()->isActive());
    }

    public function test_branch_names_must_be_unique_and_codes_do_not_collide(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $this->branch('Toshkent');

        $this->actingAs($sadmin)->post('/branches', ['name' => 'Toshkent'])->assertSessionHasErrors('name');
        $this->assertSame('toshkent-2', Branch::makeCode('Toshkent'));
    }

    public function test_sadmin_can_switch_branch_context(): void
    {
        $sadmin = $this->user(Role::SAdmin);
        $branch = $this->branch();

        $this->actingAs($sadmin)->post('/branch/switch', ['branch_id' => $branch->id]);
        $this->assertSame($branch->id, BranchContext::id());

        $this->actingAs($sadmin)->post('/branch/switch', ['branch_id' => '']);
        $this->assertNull(BranchContext::id());
    }
}
