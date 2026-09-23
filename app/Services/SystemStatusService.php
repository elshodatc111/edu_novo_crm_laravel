<?php

namespace App\Services;

use App\Models\WalletAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * v8 A4: "Tizim holati" sahifasi uchun server/baza sog'ligini tekshiradi.
 * Filialga bog'liq emas - butun tizim darajasidagi ma'lumot, faqat sAdmin ko'radi (route: role:sadmin).
 * Har bir tekshiruv ['key','label','status' => ok|warning|critical,'detail'] ko'rinishida qaytadi.
 */
class SystemStatusService
{
    public function checks(): array
    {
        return [
            $this->cronCheck(),
            $this->queueWorkerCheck(),
            $this->backupCheck(),
            $this->failedJobsCheck(),
            $this->migrationsCheck(),
            $this->walletIntegrityCheck(),
            $this->diskCheck(),
            $this->debugCheck(),
        ];
    }

    private function cronCheck(): array
    {
        $last = Cache::get('system.cron_heartbeat');

        if (! $last) {
            return $this->row('cron', 'Rejalashtiruvchi (cron)', 'critical', "Signal hali kelmagan. Serverda `php artisan schedule:run` uchun cron sozlanganini tekshiring.");
        }

        $minutesAgo = Carbon::parse($last)->diffInMinutes(now());

        if ($minutesAgo > 15) {
            return $this->row('cron', 'Rejalashtiruvchi (cron)', 'warning', "Oxirgi signal {$minutesAgo} daqiqa oldin edi. Cron vaqtincha to'xtagan bo'lishi mumkin.");
        }

        return $this->row('cron', 'Rejalashtiruvchi (cron)', 'ok', "Ishlayapti (oxirgi signal {$minutesAgo} daqiqa oldin).");
    }

    /**
     * v10 (4-band): cron o'zi to'g'ridan-to'g'ri ishlaydigan `cronCheck()`dan farqli, bu yerda
     * signal alohida `queue:work` jarayoni vazifani NAVBATDAN OLIB BAJARGANDA yangilanadi - shuning
     * uchun bu tekshiruv aynan navbat ishchisi ishlab turganini bildiradi.
     */
    private function queueWorkerCheck(): array
    {
        $last = Cache::get('system.queue_heartbeat');

        if (! $last) {
            return $this->row('queue', 'Navbat ishchisi (queue worker)', 'critical', "Signal hali kelmagan. Serverda `php artisan queue:work` doimiy ishlab turganini (masalan supervisor bilan) tekshiring.");
        }

        $minutesAgo = Carbon::parse($last)->diffInMinutes(now());

        if ($minutesAgo > 15) {
            return $this->row('queue', 'Navbat ishchisi (queue worker)', 'warning', "Oxirgi signal {$minutesAgo} daqiqa oldin edi. SMS, AI tahlil va boshqa fon vazifalari to'xtab qolgan bo'lishi mumkin.");
        }

        return $this->row('queue', 'Navbat ishchisi (queue worker)', 'ok', "Ishlayapti (oxirgi signal {$minutesAgo} daqiqa oldin).");
    }

    private function backupCheck(): array
    {
        $dir = storage_path('app/backups');
        $latest = null;

        if (File::isDirectory($dir)) {
            foreach (File::files($dir) as $f) {
                if (str_ends_with($f->getFilename(), '.sql.gz') && (! $latest || $f->getMTime() > $latest->getMTime())) {
                    $latest = $f;
                }
            }
        }

        // v12: `BackupDatabase` har urinishdan keyin (muvaffaqiyatli yoki yo'q) natijani shu faylga
        // yozadi - agar oxirgi urinish MUVAFFAQIYATSIZ bo'lsa va undan keyin yangi fayl paydo
        // bo'lmagan bo'lsa, haqiqiy sababni (masalan "mysqldump topilmadi") ko'rsatamiz - logga
        // kirmasdan. Bu "Hali birorta zaxira nusxa yaratmagan" kabi noaniq xabarni almashtiradi.
        $status = $this->readBackupStatus($dir);

        if ($status && ! $status['ok'] && (! $latest || $status['at'] > $latest->getMTime())) {
            $when = Carbon::createFromTimestamp($status['at'])->diffForHumans();

            return $this->row('backup', 'Zaxira nusxa', 'critical', "Oxirgi urinish muvaffaqiyatsiz tugadi ({$when}): {$status['message']}");
        }

        if (! $latest) {
            return $this->row('backup', 'Zaxira nusxa', 'critical', "Hali birorta zaxira nusxa yaratilmagan. `php artisan app:backup` kunlik jadvalda ishga tushishini tekshiring (yoki «Hozir zaxira olish» tugmasini bosing).");
        }

        $hoursAgo = Carbon::createFromTimestamp($latest->getMTime())->diffInHours(now());
        $size = $this->humanSize($latest->getSize());

        if ($hoursAgo > 30) {
            return $this->row('backup', 'Zaxira nusxa', 'critical', "Oxirgi zaxira {$hoursAgo} soat oldin ({$size}) - kutilganidan ancha eski, cron ishlamayotgan bo'lishi mumkin.");
        }

        return $this->row('backup', 'Zaxira nusxa', 'ok', "Oxirgi zaxira {$hoursAgo} soat oldin, hajmi {$size}.");
    }

    /** @return array{ok:bool,message:string,at:int}|null */
    private function readBackupStatus(string $dir): ?array
    {
        $path = $dir.'/.status.json';

        if (! File::exists($path)) {
            return null;
        }

        $data = json_decode((string) File::get($path), true);

        if (! is_array($data) || ! isset($data['ok'], $data['message'], $data['at'])) {
            return null;
        }

        return ['ok' => (bool) $data['ok'], 'message' => (string) $data['message'], 'at' => (int) $data['at']];
    }

    private function failedJobsCheck(): array
    {
        try {
            $count = DB::table('failed_jobs')->count();
        } catch (\Throwable) {
            return $this->row('jobs', 'Muvaffaqiyatsiz vazifalar', 'warning', "Tekshirib bo'lmadi (failed_jobs jadvali topilmadi).");
        }

        if ($count > 0) {
            return $this->row('jobs', 'Muvaffaqiyatsiz vazifalar', 'warning', "{$count} ta muvaffaqiyatsiz vazifa bor. `php artisan queue:failed` bilan ko'ring.");
        }

        return $this->row('jobs', 'Muvaffaqiyatsiz vazifalar', 'ok', "Yo'q.");
    }

    private function migrationsCheck(): array
    {
        try {
            $migrator = app('migrator');
            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
            $ran = $migrator->getRepository()->getRan();
            $pending = count(array_diff($files, $ran));
        } catch (\Throwable) {
            return $this->row('migrations', 'Migratsiyalar', 'warning', "Tekshirib bo'lmadi.");
        }

        if ($pending > 0) {
            return $this->row('migrations', 'Migratsiyalar', 'warning', "{$pending} ta bajarilmagan migratsiya bor. `php artisan migrate --force` ishga tushiring.");
        }

        return $this->row('migrations', 'Migratsiyalar', 'ok', 'Barchasi bajarilgan.');
    }

    private function walletIntegrityCheck(): array
    {
        $negative = WalletAccount::where('balance', '<', 0)->count();

        if ($negative > 0) {
            return $this->row('wallets', 'Kassa balanslari', 'critical', "{$negative} ta kassa hisobi manfiy balansda! Bu bo'lmasligi kerak, zudlik bilan tekshiring.");
        }

        return $this->row('wallets', 'Kassa balanslari', 'ok', 'Barcha kassalar manfiy emas.');
    }

    private function diskCheck(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return $this->row('disk', 'Disk hajmi', 'warning', "Tekshirib bo'lmadi (server bu ma'lumotni bermayapti).");
        }

        $usedPercent = round((1 - $free / $total) * 100, 1);
        $freeText = $this->humanSize((int) $free);

        if ($usedPercent >= 90) {
            return $this->row('disk', 'Disk hajmi', 'critical', "{$usedPercent}% to'lgan, atigi {$freeText} bo'sh joy qoldi.");
        }
        if ($usedPercent >= 75) {
            return $this->row('disk', 'Disk hajmi', 'warning', "{$usedPercent}% to'lgan, {$freeText} bo'sh joy qoldi.");
        }

        return $this->row('disk', 'Disk hajmi', 'ok', "{$usedPercent}% to'lgan, {$freeText} bo'sh joy bor.");
    }

    private function debugCheck(): array
    {
        if (config('app.env') === 'production' && config('app.debug')) {
            return $this->row('debug', 'APP_DEBUG', 'critical', "Ishlab chiqarish (production) muhitida APP_DEBUG=true turibdi! Xatoliklar sahifasida sirlar ochilib qolishi mumkin - .env faylida darhol false qiling.");
        }

        return $this->row('debug', 'APP_DEBUG', 'ok', 'Xavfsiz sozlangan.');
    }

    private function row(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }

    private function humanSize(int $bytes): string
    {
        return $bytes >= 1073741824 ? round($bytes / 1073741824, 1).' GB' : round($bytes / 1048576, 1).' MB';
    }
}
