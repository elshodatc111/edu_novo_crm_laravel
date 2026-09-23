<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    use BelongsToBranch;

    public const QUEUED = 'queued';
    public const SENT = 'sent';
    public const FAILED = 'failed';

    protected $fillable = ['branch_id', 'recipient_id', 'phone', 'message', 'secret', 'template_key', 'status', 'provider_response', 'created_by', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
