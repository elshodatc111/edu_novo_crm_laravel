<?php

use App\Jobs\QueueHeartbeatJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Har kuni ertalab tug'ilgan kun tabriklari (php artisan schedule:work yoki serverda cron kerak)
Schedule::command('sms:birthdays')->dailyAt('09:00');

// v8 B1: qarzdorlarga avtomatik eslatma (faqat sms_auto_debt yoqilgan filiallarda)
Schedule::command('sms:debts')->dailyAt('09:15');

// v8: eskirgan bir martalik tokenlarni (ikki marta yuborishdan himoya) tozalash
Schedule::command('model:prune', ['--model' => [\App\Models\SubmissionToken::class]])->daily();

// v8 A3: har kuni tunda avtomatik zaxira nusxa (storage/app/backups, 14 kunlik saqlanadi)
Schedule::command('app:backup')->dailyAt('03:00');

// v8 A4: cron/rejalashtiruvchi ishlab turganini "Tizim holati" sahifasida ko'rsatish uchun signal
Schedule::call(fn () => Cache::put('system.cron_heartbeat', now(), now()->addHours(2)))->everyFiveMinutes();

// v10 (4-band): navbat ishchisi (queue worker) ishlab turganini bilish uchun signal - bu vazifa
// navbatga QO'YILADI, faqat `php artisan queue:work` uni bajarganda signal yangilanadi.
Schedule::job(new QueueHeartbeatJob())->everyFiveMinutes();
