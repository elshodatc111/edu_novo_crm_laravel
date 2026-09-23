<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\AiLeadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Validation\ValidationException;

class AnalyzeLeadJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $leadId) {}

    public function handle(AiLeadService $service): void
    {
        $lead = Lead::withoutGlobalScopes()->with('source')->find($this->leadId);

        if (! $lead || ! $lead->isOpen()) {
            return;
        }

        try {
            $service->analyze($lead);
        } catch (ValidationException $e) {
            report($e);   // OpenAI xatosi (kalit, limit, tarmoq) tahlilni to'xtatadi, murojaatga ta'sir qilmaydi
        }
    }
}
