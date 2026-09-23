<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * v12: ommaviy bildirishnoma yuborish (sAdmin) endi Harakatlar jurnaliga (`audit_logs`) yoziladi -
 * avval bu amal boshqa deyarli hamma amaldan farqli, jurnalsiz o'tar edi.
 */
class V12NotificationAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_notification_to_all_branches_is_logged(): void
    {
        Bus::fake();

        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post(route('notifications.store'), [
            'title' => 'Eslatma', 'body' => 'Barcha uchun xabar.',
        ])->assertRedirect();

        $log = AuditLog::where('action', 'notification.sent')->firstOrFail();
        $this->assertSame($sadmin->id, $log->user_id);
        $this->assertNull($log->branch_id);
        $this->assertStringContainsString('Eslatma', $log->description);
    }

    public function test_sending_notification_to_one_branch_is_logged_with_that_branch(): void
    {
        Bus::fake();

        $branch = $this->branch();
        $this->student($branch);
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post(route('notifications.store'), [
            'title' => 'Filial xabari', 'body' => 'Matn.', 'branch_id' => $branch->id,
        ])->assertRedirect();

        $log = AuditLog::where('action', 'notification.sent')->firstOrFail();
        $this->assertSame($branch->id, $log->branch_id);
    }
}
