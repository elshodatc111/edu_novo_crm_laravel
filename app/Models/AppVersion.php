<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** v12: platforma (android/ios) bo'yicha ilova versiyasi sozlamalari - `AppVersionController`. */
class AppVersion extends Model
{
    protected $fillable = ['platform', 'min_version', 'latest_version', 'update_url', 'message', 'updated_by'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
