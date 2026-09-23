<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Room;
use App\Services\CalendarService;
use App\Services\GroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** V7: bosh sahifa kalendari — filial bo'yicha, hamma hodimga, guruh havolasi bilan. */
class V7CalendarTest extends TestCase
{
    use RefreshDatabase;

    private $branch;
    private array $cat;
    private $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-21 10:00:00');
        $this->branch = $this->branch('Toshkent');
        $this->cat = $this->catalog($this->branch);
        $this->admin = $this->user(Role::Admin, $this->branch, ['groups.create', 'groups.view']);
        $this->actingAs($this->admin);
        app(GroupService::class)->create($this->groupPayload($this->cat, ['name' => 'KALENDAR-GURUHI']), $this->admin);   // toq kunlar: 21, 23, 25, 28-sentabr
    }

    public function test_service_returns_days_with_time_room_group_and_link(): void
    {
        $m = app(CalendarService::class)->month('2026-09', $this->admin);

        $this->assertSame('Sentabr 2026', $m['label']);
        $this->assertSame('2026-08', $m['prev']);
        $this->assertSame('2026-10', $m['next']);
        $this->assertSame(['2026-09-21', '2026-09-23', '2026-09-25', '2026-09-28'], array_keys($m['days']));

        $lesson = $m['days']['2026-09-21'][0];
        $this->assertSame('KALENDAR-GURUHI', $lesson['group']);
        $this->assertSame('09:00', $lesson['start']);
        $this->assertSame('10:30', $lesson['end']);
        $this->assertSame('1-xona', $lesson['room']);
        $this->assertStringContainsString('/groups/', $lesson['url']);

        // 1-sentabr 2026 — seshanba: oldida 1 ta bo'sh katak; jami kataklar = 1 + 30
        $this->assertNull($m['cells'][0]);
        $this->assertSame('2026-09-01', $m['cells'][1]);
        $this->assertCount(31, $m['cells']);
        $this->assertSame([['id' => $this->cat['room']->id, 'name' => '1-xona']], $m['rooms']);

        $this->assertSame([], app(CalendarService::class)->month('2026-11', $this->admin)['days']);
    }

    public function test_dashboard_shows_calendar_to_all_staff_of_branch_only(): void
    {
        $this->get('/')->assertOk()->assertSee('Dars kalendari')->assertSee('KALENDAR-GURUHI')->assertSee('Sentabr 2026');
        $this->get('/?cal=2026-10')->assertOk()->assertSee('Oktabr 2026')->assertDontSee('KALENDAR-GURUHI');
        $this->get('/?cal=xato')->assertOk()->assertSee('Sentabr 2026');            // noto'g'ri oy — joriy oy

        // Menejer va o'qituvchi ham ko'radi
        $this->actingAs($this->user(Role::Manager, $this->branch))->get('/')->assertOk()->assertSee('KALENDAR-GURUHI');
        $this->actingAs($this->cat['teacher'])->get('/')->assertOk()->assertSee('KALENDAR-GURUHI');

        // Boshqa filial hodimi bu guruhni ko'rmaydi
        $other = $this->branch('Boshqa');
        $this->actingAs($this->user(Role::Manager, $other))->get('/')->assertOk()->assertSee('Dars kalendari')->assertDontSee('KALENDAR-GURUHI');
    }

    public function test_sadmin_needs_selected_branch(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->get('/')->assertOk()->assertSee('filialni tanlang')->assertDontSee('KALENDAR-GURUHI');
        $this->withSession(['current_branch_id' => $this->branch->id])->get('/')->assertOk()->assertSee('KALENDAR-GURUHI');
    }

    public function test_teacher_gets_link_only_for_own_groups(): void
    {
        $t2 = $this->user(Role::Teacher, $this->branch, ['attendance.view']);
        $cat2 = [...$this->cat, 'teacher' => $t2, 'room' => Room::create(['branch_id' => $this->branch->id, 'name' => '2-xona'])];
        app(GroupService::class)->create($this->groupPayload($cat2, ['name' => 'BOSHQA-USTOZ-GURUHI']), $this->admin);

        $days = app(CalendarService::class)->month('2026-09', $this->cat['teacher'])['days']['2026-09-21'];
        $byName = collect($days)->keyBy('group');
        $this->assertNotNull($byName['KALENDAR-GURUHI']['url']);       // o'z guruhi — ochiladi
        $this->assertNull($byName['BOSHQA-USTOZ-GURUHI']['url']);      // boshqa ustoz guruhi — faqat ko'rinadi
        $this->assertCount(2, app(CalendarService::class)->month('2026-09', $this->cat['teacher'])['rooms']);
    }
}
