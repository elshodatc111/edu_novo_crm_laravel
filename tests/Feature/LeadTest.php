<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = $this->branch('Toshkent');
        $this->manager = $this->user(Role::Manager, $this->branch, ['leads.view', 'leads.manage', 'students.create', 'students.view']);
    }

    public function test_public_form_creates_lead_for_branch(): void
    {
        $src = LeadSource::create(['branch_id' => $this->branch->id, 'name' => 'Telegram']);

        $this->get("/apply/{$this->branch->code}")->assertOk()->assertSee('Toshkent')->assertSee('Telegram');
        $this->post("/apply/{$this->branch->code}", ['name' => 'ali valiyev', 'phone' => '+998 90 123 4567', 'lead_source_id' => $src->id])
            ->assertRedirect()->assertSessionHas('sent');

        $lead = Lead::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('ALI VALIYEV', $lead->name);
        $this->assertSame('+998 90 123 4567', $lead->phone);
        $this->assertSame($this->branch->id, $lead->branch_id);
        $this->assertSame('new', $lead->status);
        $this->assertSame($src->id, $lead->lead_source_id);
    }

    public function test_public_form_protections(): void
    {
        // Yashirin maydon (bot)
        $this->post("/apply/{$this->branch->code}", ['name' => 'Bot', 'phone' => '+998 90 123 4567', 'website' => 'spam.uz'])->assertSessionHas('sent');
        $this->assertSame(0, Lead::withoutGlobalScopes()->count());

        // Noto'g'ri telefon
        $this->post("/apply/{$this->branch->code}", ['name' => 'Ali', 'phone' => '12345'])->assertSessionHasErrors('phone');

        // Takror yuborish 10 daqiqa ichida yangi yozuv yaratmaydi
        $this->post("/apply/{$this->branch->code}", ['name' => 'Ali', 'phone' => '+998 90 123 4567']);
        $this->post("/apply/{$this->branch->code}", ['name' => 'Ali', 'phone' => '+998 90 123 4567']);
        $this->assertSame(1, Lead::withoutGlobalScopes()->count());

        // Boshqa filial manbasi qabul qilinmaydi
        $other = $this->branch('Boshqa');
        $foreign = LeadSource::create(['branch_id' => $other->id, 'name' => 'Instagram']);
        $this->post("/apply/{$this->branch->code}", ['name' => 'Vali', 'phone' => '+998 90 222 3344', 'lead_source_id' => $foreign->id])->assertSessionHasErrors('lead_source_id');

        // Yopilgan filial
        $this->branch->update(['status' => 'closed']);
        $this->get("/apply/{$this->branch->code}")->assertNotFound();
    }

    public function test_repeat_flag_when_phone_belongs_to_student(): void
    {
        $this->student($this->branch, ['phone' => '+998 90 123 4567']);

        $this->post("/apply/{$this->branch->code}", ['name' => 'Ali', 'phone' => '+998 90 123 4567']);

        $this->assertTrue(Lead::withoutGlobalScopes()->firstOrFail()->is_repeat);
    }

    public function test_staff_workflow_note_cancel_reopen(): void
    {
        $this->post("/apply/{$this->branch->code}", ['name' => 'Ali', 'phone' => '+998 90 123 4567']);
        $lead = Lead::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->manager)->get('/leads')->assertOk()->assertSee('ALI');
        $this->actingAs($this->manager)->post("/leads/{$lead->id}/note", ['body' => 'Qo\'ng\'iroq qilindi'])->assertRedirect();
        $this->assertSame('in_progress', $lead->fresh()->status);

        $this->actingAs($this->manager)->post("/leads/{$lead->id}/cancel", ['reason' => 'Qiziqmadi'])->assertRedirect();
        $this->assertSame('cancelled', $lead->fresh()->status);
        $this->actingAs($this->manager)->post("/leads/{$lead->id}/cancel")->assertSessionHasErrors('lead');

        $this->actingAs($this->manager)->post("/leads/{$lead->id}/reopen")->assertRedirect();
        $this->assertSame('in_progress', $lead->fresh()->status);
        $this->assertGreaterThanOrEqual(4, $lead->notes()->count());

        $this->actingAs($this->manager)->get("/leads/{$lead->id}")->assertOk()->assertSee('Izohlar tarixi');
    }

    public function test_convert_creates_student_or_links_existing(): void
    {
        $this->post("/apply/{$this->branch->code}", ['name' => 'Ali', 'phone' => '+998 90 123 4567']);
        $lead = Lead::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->manager)->post("/leads/{$lead->id}/convert", ['about' => 'Yangi'])->assertRedirect()->assertSessionHas('credentials');
        $student = User::where('role', 'student')->firstOrFail();
        $this->assertSame('998901234567', $student->username);
        $this->assertSame('+998 90 123 4567', $student->phone);
        $this->assertSame($student->id, $lead->fresh()->student_id);
        $this->assertSame('converted', $lead->fresh()->status);

        // Ikkinchi marta bo'lmaydi
        $this->actingAs($this->manager)->post("/leads/{$lead->id}/convert")->assertSessionHasErrors('lead');

        // Xuddi shu telefonli yangi murojaat: mavjud o'quvchiga bog'lanadi (dublikat yaratilmaydi)
        $this->travel(11)->minutes();
        $this->post("/apply/{$this->branch->code}", ['name' => 'Ali aka', 'phone' => '+998 90 123 4567']);
        $lead2 = Lead::withoutGlobalScopes()->where('name', 'ALI AKA')->firstOrFail();
        $this->assertTrue($lead2->is_repeat);
        $this->actingAs($this->manager)->post("/leads/{$lead2->id}/convert")->assertRedirect()->assertSessionMissing('credentials');
        $this->assertSame(1, User::where('role', 'student')->count());
        $this->assertSame($student->id, $lead2->fresh()->student_id);

        // Bir filialda bir raqam bitta o'quvchiga tegishli: yana shu raqam bilan murojaat kelsa ham dublikat yaratilmaydi
        $this->travel(11)->minutes();
        $this->post("/apply/{$this->branch->code}", ['name' => 'Ali uka', 'phone' => '+998 90 123 4567']);
        $lead3 = Lead::withoutGlobalScopes()->where('name', 'ALI UKA')->firstOrFail();
        $this->actingAs($this->manager)->post("/leads/{$lead3->id}/convert")->assertRedirect()->assertSessionMissing('credentials');
        $this->assertSame(1, User::where('role', 'student')->count());
        $this->assertSame($student->id, $lead3->fresh()->student_id);
    }

    public function test_permissions_and_branch_scope(): void
    {
        $this->post("/apply/{$this->branch->code}", ['name' => 'Ali', 'phone' => '+998 90 123 4567']);
        $lead = Lead::withoutGlobalScopes()->firstOrFail();

        $viewer = $this->user(Role::Manager, $this->branch, ['leads.view']);
        $this->actingAs($viewer)->get("/leads/{$lead->id}")->assertOk();
        $this->actingAs($viewer)->post("/leads/{$lead->id}/note", ['body' => 'x'])->assertForbidden();

        $noConvert = $this->user(Role::Manager, $this->branch, ['leads.view', 'leads.manage']);
        $this->actingAs($noConvert)->post("/leads/{$lead->id}/convert")->assertForbidden();

        $foreign = $this->user(Role::Manager, $this->branch('Boshqa'), ['leads.view', 'leads.manage']);
        $this->actingAs($foreign)->get("/leads/{$lead->id}")->assertNotFound();

        $teacher = $this->user(Role::Teacher, $this->branch, ['attendance.view']);
        $this->actingAs($teacher)->get('/leads')->assertForbidden();
    }

    public function test_manual_lead_and_index_stats(): void
    {
        $this->actingAs($this->manager)->post('/leads', ['name' => 'Sara', 'phone' => '+998 90 555 6677'])->assertRedirect();
        $this->assertSame(1, Lead::count());
        $this->actingAs($this->manager)->get('/leads?status=new')->assertOk()->assertSee('Qabul foizi');
        $this->actingAs($this->manager)->get('/leads/create')->assertOk();
    }
}
