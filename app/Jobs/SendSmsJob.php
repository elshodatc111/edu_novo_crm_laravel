<?php

namespace App\Jobs;

use App\Models\SmsMessage;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendSmsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $smsId) {}

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(SmsService $service): void
    {
        $sms = SmsMessage::withoutGlobalScopes()->find($this->smsId);

        if ($sms && $sms->status === SmsMessage::QUEUED) {
            $service->deliver($sms);
        }
    }

    public function failed(Throwable $e): void
    {
        SmsMessage::withoutGlobalScopes()->whereKey($this->smsId)->update(['status' => SmsMessage::FAILED, 'provider_response' => mb_substr($e->getMessage(), 0, 500)]);
    }
}
