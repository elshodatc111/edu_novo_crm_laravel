<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\SystemStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

/** Tizimning umumiy sog'ligi (cron, zaxira, disk, ...) - faqat sAdmin (route darajasida `role:sadmin`), filialga bog'liq emas. */
class SystemStatusController extends Controller
{
    public function index(SystemStatusService $status)
    {
        return view('system-status.index', ['checks' => $status->checks()]);
    }

    /**
     * v12: sAdmin kutilmagan (masalan mysqldump muammosini tuzatgandan keyin) zaxirani darhol
     * qo'lda ishga tushirishi mumkin - cron'ning ertagi 03:00'sini kutish shart emas.
     */
    public function runBackupNow(): RedirectResponse
    {
        $exitCode = Artisan::call('app:backup');
        $output = trim(Artisan::output());

        AuditLog::record('system.backup_manual', null, "sAdmin zaxirani qo'lda ishga tushirdi: ".($exitCode === 0 ? 'muvaffaqiyatli' : 'xato'));

        return $exitCode === 0
            ? back()->with('success', "Zaxira olindi. {$output}")
            : back()->with('error', "Zaxira olishda xatolik: {$output}");
    }
}
