<?php

namespace App\Services;

use App\Enums\Role;
use App\Jobs\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * v11: sAdmin tomonidan mobil ilova foydalanuvchilariga bildirishnoma yuborish
 * ("barchaga" yoki bitta filialga filtrlab). sAdmin o'zi qabul qiluvchi bo'lmaydi.
 */
class NotificationService
{
    /** @return array{0:Notification,1:int} yuborilgan bildirishnoma va qabul qiluvchilar soni */
    public function send(string $title, string $body, ?int $branchId, User $sender, array $data = []): array
    {
        return DB::transaction(function () use ($title, $body, $branchId, $sender, $data) {
            $notification = Notification::create([
                'branch_id' => $branchId,
                'title' => $title,
                'body' => $body,
                'data' => $data ?: null,
                'sent_by' => $sender->id,
            ]);

            $userIds = User::query()
                ->where('role', '!=', Role::SAdmin)
                ->whereNull('archived_at')
                ->where('status', 'active')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->pluck('id');

            $rows = $userIds->map(fn ($id) => [
                'notification_id' => $notification->id,
                'user_id' => $id,
                'push_status' => NotificationRecipient::PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                NotificationRecipient::insert($chunk);
            }

            $notification->update(['recipients_count' => count($rows)]);

            foreach ($userIds as $id) {
                SendPushNotificationJob::dispatch($notification->id, $id);
            }

            return [$notification, count($rows)];
        });
    }
}
