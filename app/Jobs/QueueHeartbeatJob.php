<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * v10 (4-band): "Tizim holati" sahifasida navbat (queue) ishchisi (worker) ishlab turganini bilish uchun.
 * Rejalashtiruvchi (schedule) bu vazifani navbatga QO'YADI (dispatch); faqat `php artisan queue:work`
 * ishlab turgan bo'lsa BAJARILADI va signal yangilanadi - shu farq bilan cron signalidan ajraladi
 * (cron o'zi to'g'ridan-to'g'ri ishlaydi, workerga bog'liq emas).
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Cache::put('system.queue_heartbeat', now(), now()->addHours(2));
    }
}
