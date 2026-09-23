<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lead;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** v9: lidni o'quvchi qilishda bir vaqtning o'zida guruhga qo'shish (5-band) va telefonni nusxalash (6-band). */
class V9LeadEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $manager;
    private $group;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = $this->branch();
        $this->manager = $this->user(Role::Manager, $this->branch, ['leads.view', 'leads.manage', 'students.create', 'students.view', 'groups.members']);
        $this->actingAs($this->manager);

        $cat = $this->catalog($this->branch);
        $this->group = app(GroupService::class)->create($this->groupPayload($cat), $this->manager);
    }

    public function test_convert_can_enroll_into_a_group_in_one_step(): void
    {
        $lead = Lead::create(['branch_id' => $this->branch->id, 'name' => 'YANGI MIJOZ', 'phone' => '+998 90 111 2233', 'status' => Lead::NEW]);

        $response = $this->actingAs($this->manager)->post("/leads/{$lead->id}/convert", ['group_id' => $this->group->id]);

        $response->assertRedirect()->assertSessionHas('credentials');
        $student = $lead->fresh()->student;
        $this->assertNotNull($student);
        $this->assertSame(1, $this->group->activeMembers()->count());
        $this->assertTrue($student->fresh()->balance < 0, "Guruhga qo'shilgach balans yechilishi kerak.");
        $this->assertTrue($lead->fresh()->notes()->where('body', 'like', "Guruhga qo'shildi:%")->exists());
    }

    public function test_convert_without_group_id_still_works_as_before(): void
    {
        $lead = Lead::create(['branch_id' => $this->branch->id, 'name' => 'ODDIY MIJOZ', 'phone' => '+998 90 111 2244', 'status' => Lead::NEW]);

        $this->actingAs($this->manager)->post("/leads/{$lead->id}/convert")->assertRedirect()->assertSessionHas('credentials');

        $this->assertSame(0, $this->group->activeMembers()->count());
    }

    public function test_group_id_is_rejected_without_groups_members_permission(): void
    {
        $narrow = $this->user(Role::Manager, $this->branch, ['leads.view', 'leads.manage', 'students.create']);
        $lead = Lead::create(['branch_id' => $this->branch->id, 'name' => 'BOSHQA MIJOZ', 'phone' => '+998 90 111 2255', 'status' => Lead::NEW]);

        $this->actingAs($narrow)->post("/leads/{$lead->id}/convert", ['group_id' => $this->group->id])->assertSessionHasErrors('group_id');

        $this->assertNull($lead->fresh()->student_id);
    }

    public function test_group_from_another_branch_is_rejected(): void
    {
        $other = $this->branch('Boshqa');
        $catB = $this->catalog($other);
        $managerB = $this->user(Role::Manager, $other, ['groups.create']);
        $this->actingAs($managerB);
        $foreignGroup = app(GroupService::class)->create($this->groupPayload($catB), $managerB);

        $this->actingAs($this->manager);
        $lead = Lead::create(['branch_id' => $this->branch->id, 'name' => 'UCHINCHI MIJOZ', 'phone' => '+998 90 111 2266', 'status' => Lead::NEW]);

        $this->actingAs($this->manager)->post("/leads/{$lead->id}/convert", ['group_id' => $foreignGroup->id])->assertSessionHasErrors('group_id');
    }

    public function test_lead_pages_show_copyable_phone_without_spaces(): void
    {
        $lead = Lead::create(['branch_id' => $this->branch->id, 'name' => 'TELEFONLI MIJOZ', 'phone' => '+998 97 100 2205', 'status' => Lead::NEW]);

        $this->actingAs($this->manager)->get('/leads')->assertOk()->assertSee('+998971002205', false);
        $this->actingAs($this->manager)->get("/leads/{$lead->id}")->assertOk()->assertSee('+998971002205', false);
    }
}
