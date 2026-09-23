<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * v12: `NotificationService::send()` bildirishnoma yozuvini yaratib, so'ng darhol
 * `SendPushNotificationJob`ni navbatga qo'yadi. `after_commit => false` bo'lganda navbat
 * ishchisi ba'zan yozuv tranzaksiyasi COMMIT bo'lishidan OLDIN vazifani boshlab, "topilmadi"
 * xatosiga uchrashi mumkin edi. Bu test shu sozlamaning HAR BIR ulanish uchun `true`
 * ekanini tekshiradi - kelajakda kimdir `config/queue.php`ni tahrirlab buni bilmasdan
 * qaytarib qo'yishining oldini oladi.
 */
class V12QueueAfterCommitTest extends TestCase
{
    public function test_all_queue_connections_use_after_commit(): void
    {
        $connections = config('queue.connections');

        foreach ($connections as $name => $connection) {
            if (! array_key_exists('after_commit', $connection)) {
                continue;
            }

            $this->assertTrue($connection['after_commit'], "'{$name}' ulanishi uchun after_commit true bo'lishi kerak.");
        }
    }
}
