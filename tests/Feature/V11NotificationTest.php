<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class V11NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sadmin_can_send_notification_to_all_branches(): void
    {
        Bus::fake();

        $a = $this->branch('A');
        $b = $this->branch('B');
        $studentA = $this->student($a);
        $studentB = $this->student($b);
        $sadmin = $this->user(Role::SAdmin);

        $response = $this->actingAs($sadmin)->post(route('notifications.store'), [
            'title' => 'Yangilik', 'body' => 'Ilova yangilandi.',
        ]);

        $response->assertRedirect();
        $notification = Notification::first();
        $this->assertNotNull($notification);
        $this->assertNull($notification->branch_id);
        $this->assertSame(2, $notification->recipients_count);

        $this->assertDatabaseHas('notification_recipients', ['notification_id' => $notification->id, 'user_id' => $studentA->id]);
        $this->assertDatabaseHas('notification_recipients', ['notification_id' => $notification->id, 'user_id' => $studentB->id]);
        $this->assertDatabaseMissing('notification_recipients', ['notification_id' => $notification->id, 'user_id' => $sadmin->id]);

        Bus::assertDispatched(SendPushNotificationJob::class, 2);
    }

    public function test_sadmin_can_filter_notification_by_branch(): void
    {
        Bus::fake();

        $a = $this->branch('A');
        $b = $this->branch('B');
        $studentA = $this->student($a);
        $this->student($b);
        $sadmin = $this->user(Role::SAdmin);

        $this->actingAs($sadmin)->post(route('notifications.store'), [
            'title' => 'A filiali', 'body' => 'Faqat A filialiga.', 'branch_id' => $a->id,
        ]);

        $notification = Notification::first();
        $this->assertSame(1, $notification->recipients_count);
        $this->assertDatabaseHas('notification_recipients', ['notification_id' => $notification->id, 'user_id' => $studentA->id]);
    }

    public function test_non_sadmin_cannot_send_notification(): void
    {
        $admin = $this->user(Role::Admin);

        $response = $this->actingAs($admin)->post(route('notifications.store'), ['title' => 'x', 'body' => 'y']);

        $response->assertForbidden();
    }

    public function test_mobile_can_register_device_token_and_list_notifications(): void
    {
        Bus::fake();

        $branch = $this->branch();
        $student = $this->student($branch);
        $sadmin = $this->user(Role::SAdmin);
        $token = $student->createToken('mobile')->plainTextToken;

        $this->api($token)->postJson('/api/v1/me/device-token', ['token' => 'fcm-token-123', 'platform' => 'android'])
            ->assertOk();

        $this->assertDatabaseHas('device_tokens', ['user_id' => $student->id, 'token' => 'fcm-token-123', 'device_name' => 'mobile']);

        $this->actingAs($sadmin)->post(route('notifications.store'), ['title' => 'Salom', 'body' => "Bu sinov xabari."]);

        $list = $this->api($token)->getJson('/api/v1/notifications');
        $list->assertOk();
        $this->assertCount(1, $list->json('data'));
        $this->assertSame(1, $list->json('meta.unread_count'));
        $this->assertFalse($list->json('data.0.read'));

        $notificationId = $list->json('data.0.id');
        $this->api($token)->postJson("/api/v1/notifications/{$notificationId}/read")->assertOk();

        $after = $this->api($token)->getJson('/api/v1/notifications');
        $this->assertTrue($after->json('data.0.read'));
        $this->assertSame(0, $after->json('meta.unread_count'));
    }

    public function test_logout_removes_device_token_for_that_device(): void
    {
        $branch = $this->branch();
        $student = $this->student($branch);
        $token = $student->createToken('mobile')->plainTextToken;

        $this->api($token)->postJson('/api/v1/me/device-token', ['token' => 'abc'])->assertOk();
        $this->assertDatabaseHas('device_tokens', ['user_id' => $student->id]);

        $this->api($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseMissing('device_tokens', ['user_id' => $student->id]);
    }

    public function test_fcm_service_sends_via_http_when_configured(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/x/messages/1'], 200),
        ]);

        config([
            'services.fcm.project_id' => 'test-project',
            'services.fcm.credentials' => $this->fakeServiceAccountPath(),
        ]);

        $fcm = app(\App\Services\FcmService::class);
        $result = $fcm->send('device-token', 'Sarlavha', 'Matn');

        $this->assertTrue($result['ok']);
        Http::assertSentCount(2);
    }

    public function test_push_job_marks_recipient_skipped_when_firebase_not_configured(): void
    {
        config(['services.fcm.project_id' => null]);

        $branch = $this->branch();
        $student = $this->student($branch);
        $sadmin = $this->user(Role::SAdmin);

        DeviceToken::create(['user_id' => $student->id, 'token' => 'tok', 'device_name' => 'mobile']);

        $notification = \App\Models\Notification::create(['title' => 't', 'body' => 'b', 'sent_by' => $sadmin->id, 'recipients_count' => 1]);
        NotificationRecipient::create(['notification_id' => $notification->id, 'user_id' => $student->id]);

        (new SendPushNotificationJob($notification->id, $student->id))->handle(app(\App\Services\FcmService::class));

        $this->assertSame(NotificationRecipient::SKIPPED, NotificationRecipient::first()->push_status);
    }

    private function fakeServiceAccountPath(): string
    {
        // Repo ichidagi storage'ga tegmaslik uchun vaqtinchalik tizim papkasida yaratiladi.
        $path = sys_get_temp_dir().'/fcm_test_'.uniqid().'.json';
        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($privateKey, $pem);

        file_put_contents($path, json_encode(['client_email' => 'test@example.com', 'private_key' => $pem]));

        return $path;
    }
}
