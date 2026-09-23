<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** V7: harakatlar jurnali filial bo'yicha ko'rinadi; sAdmin hammasini yoki tanlangan filialni ko'radi. */
class V7AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_sadmin_action_without_subject_is_attributed_to_selected_branch(): void
    {
        $a = $this->branch('A filial');
        $b = $this->branch('B filial');
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->withSession(['current_branch_id' => $a->id]);
        $log = AuditLog::record('settings.updated', null, 'Sozlama o\'zgardi');
        $this->assertSame($a->id, $log->branch_id);

        // Filial tanlanmagan bo'lsa, filialsiz qoladi
        $this->flushSession();
        $this->assertNull(AuditLog::record('system.check', null, 'Tizim')->branch_id);
        $this->assertNotNull($b->id);
    }

    public function test_each_branch_sees_only_own_entries_and_sadmin_sees_all(): void
    {
        $a = $this->branch('A filial');
        $b = $this->branch('B filial');
        $adminA = $this->user(Role::Admin, $a, ['audit.view']);
        $adminB = $this->user(Role::Admin, $b, ['audit.view']);
        $sadmin = $this->user(Role::SAdmin);

        AuditLog::create(['branch_id' => $a->id, 'action' => 'x.a', 'description' => 'Faqat-A-yozuvi']);
        AuditLog::create(['branch_id' => $b->id, 'action' => 'x.b', 'description' => 'Faqat-B-yozuvi']);
        AuditLog::create(['branch_id' => null, 'action' => 'x.n', 'description' => 'Filialsiz-yozuv']);

        $this->actingAs($adminA)->get('/audit-log')->assertOk()->assertSee('Faqat-A-yozuvi')->assertDontSee('Faqat-B-yozuvi')->assertDontSee('Filialsiz-yozuv')
            ->assertSee('Filial: A filial');
        // Admin `branch` parametri bilan boshqa filialni ochib ko'ra olmaydi
        $this->get('/audit-log?branch='.$b->id)->assertOk()->assertDontSee('Faqat-B-yozuvi');
        $this->actingAs($adminB)->get('/audit-log')->assertOk()->assertSee('Faqat-B-yozuvi')->assertDontSee('Faqat-A-yozuvi');

        // sAdmin: filial tanlangan bo'lsa faqat o'shanikini
        $this->actingAs($sadmin)->withSession(['current_branch_id' => $a->id])->get('/audit-log')->assertOk()
            ->assertSee('Faqat-A-yozuvi')->assertDontSee('Faqat-B-yozuvi')->assertDontSee('Filialsiz-yozuv');

        // sAdmin: barcha filiallar + sahifa ichidagi filial filtri
        $this->flushSession();
        $this->actingAs($sadmin)->get('/audit-log')->assertOk()->assertSee('Barcha filiallar')
            ->assertSee('Faqat-A-yozuvi')->assertSee('Faqat-B-yozuvi')->assertSee('Filialsiz-yozuv');
        $this->get('/audit-log?branch='.$b->id)->assertOk()->assertSee('Faqat-B-yozuvi')->assertDontSee('Faqat-A-yozuvi');
        $this->get('/audit-log?branch=none')->assertOk()->assertSee('Filialsiz-yozuv')->assertDontSee('Faqat-A-yozuvi');
    }

    public function test_user_name_filter(): void
    {
        $a = $this->branch('A filial');
        $admin = $this->user(Role::Admin, $a, ['audit.view'], ['name' => 'Zafar Test']);
        $other = $this->user(Role::Manager, $a, [], ['name' => 'Boshqa Odam']);
        AuditLog::create(['branch_id' => $a->id, 'user_id' => $admin->id, 'action' => 'x.1', 'description' => 'Zafar-yozuvi']);
        AuditLog::create(['branch_id' => $a->id, 'user_id' => $other->id, 'action' => 'x.2', 'description' => 'Boshqa-yozuvi']);

        $this->actingAs($admin)->get('/audit-log?user=Zafar')->assertOk()->assertSee('Zafar-yozuvi')->assertDontSee('Boshqa-yozuvi');
    }
}
