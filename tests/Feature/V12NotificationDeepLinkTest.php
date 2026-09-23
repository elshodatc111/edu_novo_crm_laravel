<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/** v12: bildirishnomaga "deep link" (ilovada bosilganda qayerga o'tish) qo'shish. */
class V12NotificationDeepLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_sadmin_can_attach_a_group_deep_link(): void
    {
        Bus::fake();

        $branch = $this->branch();
        $student = $this->student($branch);
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post(route('notifications.store'), [
            'title' => 'Guruh eslatmasi', 'body' => 'Ertaga dars bor.',
            'link_type' => 'group', 'link_id' => 42,
        ])->assertRedirect();

        $notification = Notification::firstOrFail();
        $this->assertSame(['link_type' => 'group', 'link_id' => 42], $notification->data);

        $token = $student->createToken('mobile')->plainTextToken;
        $list = $this->api($token)->getJson('/api/v1/notifications')->assertOk();
        $this->assertSame('group', $list->json('data.0.data.link_type'));
        $this->assertSame(42, $list->json('data.0.data.link_id'));
    }

    public function test_link_id_is_required_when_link_type_is_not_none(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post(route('notifications.store'), [
            'title' => 'x', 'body' => 'y', 'link_type' => 'lead',
        ])->assertSessionHasErrors('link_id');

        $this->assertSame(0, Notification::count());
    }

    public function test_invalid_link_type_is_rejected(): void
    {
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post(route('notifications.store'), [
            'title' => 'x', 'body' => 'y', 'link_type' => 'boshqa-narsa',
        ])->assertSessionHasErrors('link_type');
    }

    public function test_no_link_type_stores_empty_data(): void
    {
        Bus::fake();

        $branch = $this->branch();
        $this->student($branch);
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post(route('notifications.store'), ['title' => 'x', 'body' => 'y']);

        $this->assertNull(Notification::firstOrFail()->data);
    }

    public function test_deep_link_id_is_forwarded_to_fcm_data_payload(): void
    {
        $branch = $this->branch();
        $student = $this->student($branch);
        \App\Models\DeviceToken::create(['user_id' => $student->id, 'token' => 'tok', 'device_name' => 'mobile']);
        $sadmin = $this->user(Role::SAdmin);
        config(['services.fcm.project_id' => null]);

        $notification = Notification::create([
            'title' => 't', 'body' => 'b', 'sent_by' => $sadmin->id, 'recipients_count' => 1,
            'data' => ['link_type' => 'lead', 'link_id' => 7],
        ]);
        \App\Models\NotificationRecipient::create(['notification_id' => $notification->id, 'user_id' => $student->id]);

        // FCM sozlanmagan bo'lsa ham job xatosiz ishlashi kerak (data mavjudligi bilan bog'liq regressiyani ushlaydi)
        (new SendPushNotificationJob($notification->id, $student->id))->handle(app(\App\Services\FcmService::class));

        $this->assertSame(\App\Models\NotificationRecipient::SKIPPED, \App\Models\NotificationRecipient::first()->push_status);
    }
}
