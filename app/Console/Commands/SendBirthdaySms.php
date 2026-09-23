<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\SmsNotifier;
use Illuminate\Console\Command;

class SendBirthdaySms extends Command
{
    protected $signature = 'sms:birthdays';

    protected $description = "Bugun tug'ilgan kuni bo'lgan o'quvchilarga tabrik SMS yuboradi";

    public function handle(SmsNotifier $notifier): int
    {
        $count = 0;

        foreach (Branch::active()->where('sms_enabled', true)->get() as $branch) {
            $students = User::where('branch_id', $branch->id)->where('role', Role::Student)->whereNull('archived_at')
                ->whereMonth('birthday', today()->month)->whereDay('birthday', today()->day)->get();

            foreach ($students as $student) {
                $already = SmsMessage::withoutGlobalScopes()->where('recipient_id', $student->id)->where('template_key', 'birthday')->whereDate('created_at', today())->exists();
                if (! $already) {
                    $notifier->notify('birthday', $student);
                    $count++;
                }
            }
        }

        $this->info("Tabrik SMS navbatga qo'yildi: {$count}");

        return self::SUCCESS;
    }
}
