<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * v12: `SendPushNotificationJob` avval "sozlanganmi" holatini FCM xato matnini satr sifatida
 * solishtirib aniqlar edi. Endi `FcmService::isConfigured()` orqali aniq tekshiriladi - shu
 * bilan Firebase HAQIQATDA sozlangan, lekin so'rov boshqa sababdan (masalan server xatosi)
 * muvaffaqiyatsiz bo'lgan holatlar endi noto'g'ri "sozlanmagan" (SKIPPED) emas, to'g'ri
 * "muvaffaqiyatsiz" (FAILED) deb belgilanadi.
 */
class V12PushFailureStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_configured_fcm_with_real_failure_marks_recipient_failed_not_skipped(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            // Unregistered/not-found emas - haqiqiy server xatosi (masalan FCM vaqtincha ishlamayapti).
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'INTERNAL']], 500),
        ]);

        config([
            'services.fcm.project_id' => 'test-project',
            'services.fcm.credentials' => $this->fakeServiceAccountPath(),
        ]);

        $branch = $this->branch();
        $student = $this->student($branch);
        $sadmin = $this->user(Role::SAdmin);

        DeviceToken::create(['user_id' => $student->id, 'token' => 'tok', 'device_name' => 'mobile']);

        $notification = Notification::create(['title' => 't', 'body' => 'b', 'sent_by' => $sadmin->id, 'recipients_count' => 1]);
        NotificationRecipient::create(['notification_id' => $notification->id, 'user_id' => $student->id]);

        (new SendPushNotificationJob($notification->id, $student->id))->handle(app(FcmService::class));

        $this->assertSame(NotificationRecipient::FAILED, NotificationRecipient::first()->push_status);
        // Token noto'g'ri (unregistered) emas, shuning uchun o'chirilmasligi kerak.
        $this->assertDatabaseHas('device_tokens', ['user_id' => $student->id, 'token' => 'tok']);
    }

    public function test_unregistered_token_is_deleted_and_marked_skipped_when_it_is_the_only_token(): void
    {
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'fake-token'], 200),
            'fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNREGISTERED']], 404),
        ]);

        config([
            'services.fcm.project_id' => 'test-project',
            'services.fcm.credentials' => $this->fakeServiceAccountPath(),
        ]);

        $branch = $this->branch();
        $student = $this->student($branch);
        $sadmin = $this->user(Role::SAdmin);

        DeviceToken::create(['user_id' => $student->id, 'token' => 'eskirgan', 'device_name' => 'mobile']);

        $notification = Notification::create(['title' => 't', 'body' => 'b', 'sent_by' => $sadmin->id, 'recipients_count' => 1]);
        NotificationRecipient::create(['notification_id' => $notification->id, 'user_id' => $student->id]);

        (new SendPushNotificationJob($notification->id, $student->id))->handle(app(FcmService::class));

        $this->assertDatabaseMissing('device_tokens', ['token' => 'eskirgan']);
        $this->assertSame(NotificationRecipient::SKIPPED, NotificationRecipient::first()->push_status);
    }

    private function fakeServiceAccountPath(): string
    {
        $path = sys_get_temp_dir().'/fcm_test_'.uniqid().'.json';
        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($privateKey, $pem);

        file_put_contents($path, json_encode(['client_email' => 'test@example.com', 'private_key' => $pem]));

        register_shutdown_function(fn () => @unlink($path));

        return $path;
    }
}
