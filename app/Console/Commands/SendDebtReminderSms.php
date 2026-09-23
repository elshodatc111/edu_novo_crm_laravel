<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\Branch;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\SmsNotifier;
use App\Support\Format;
use Illuminate\Console\Command;

/** v8 B1: filialda yoqilgan bo'lsa, balansi manfiy o'quvchilarga har kuni eslatma SMS (kuniga bir marta). */
class SendDebtReminderSms extends Command
{
    protected $signature = 'sms:debts';

    protected $description = "Qarzdor o'quvchilarga qarz eslatmasi SMS yuboradi (avtomatik yoqilgan filiallarda)";

    public function handle(SmsNotifier $notifier): int
    {
        $count = 0;

        foreach (Branch::active()->where('sms_enabled', true)->where('sms_auto_debt', true)->get() as $branch) {
            $debtors = User::where('branch_id', $branch->id)->where('role', Role::Student)->whereNull('archived_at')
                ->where('balance', '<', 0)->get();

            foreach ($debtors as $student) {
                $already = SmsMessage::withoutGlobalScopes()->where('recipient_id', $student->id)
                    ->where('template_key', 'debt_reminder')->whereDate('created_at', today())->exists();

                if (! $already) {
                    $notifier->notify('debt_reminder', $student, ['debt' => Format::money(max(0, -$student->balance))]);
                    $count++;
                }
            }
        }

        $this->info("Qarz eslatmasi SMS navbatga qo'yildi: {$count}");

        return self::SUCCESS;
    }
}
