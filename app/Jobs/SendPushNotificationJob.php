<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * v11: bitta qabul qiluvchiga push yuborish (SendSmsJob'ga o'xshab, har bir qabul qiluvchi
 * o'z navbat vazifasiga ega - shu bilan bittasi sekinlashsa/xato bersa, boshqalarga ta'sir qilmaydi).
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationId, public int $userId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(FcmService $fcm): void
    {
        $recipient = NotificationRecipient::where('notification_id', $this->notificationId)->where('user_id', $this->userId)->first();

        if (! $recipient) {
            return;
        }

        $notification = Notification::find($this->notificationId);
        $tokens = DeviceToken::where('user_id', $this->userId)->get();

        if (! $notification || $tokens->isEmpty()) {
            $recipient->update(['push_status' => NotificationRecipient::SKIPPED]);

            return;
        }

        // v12: "sozlanganmi" holati endi xato matnini solishtirib emas, to'g'ridan-to'g'ri
        // FcmService::isConfigured() orqali aniqlanadi - xato xabari matni keyinchalik
        // o'zgarsa ham (masalan tarjima/formatlash), bu tekshiruv buzilmaydi.
        $anySent = false;
        $configured = $fcm->isConfigured();
        $anyRealFailure = false;

        foreach ($tokens as $token) {
            $result = $fcm->send($token->token, $notification->title, $notification->body, ['notification_id' => (string) $notification->id] + (array) $notification->data);

            if ($result['ok']) {
                $anySent = true;
            } elseif ($result['unregistered']) {
                $token->delete();
            } elseif ($configured) {
                $anyRealFailure = true;
            }
        }

        $recipient->update(['push_status' => $anySent ? NotificationRecipient::SENT : ($anyRealFailure ? NotificationRecipient::FAILED : NotificationRecipient::SKIPPED)]);
    }
}
