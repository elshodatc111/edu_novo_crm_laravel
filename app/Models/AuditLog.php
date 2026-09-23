<?php

namespace App\Models;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'branch_id', 'user_id', 'action', 'subject_type', 'subject_id',
        'description', 'old_values', 'new_values', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Harakatni jurnalga yozadi.
     *
     * @param  string  $action  masalan: "auth.login", "branch.closed", "permissions.updated"
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $old = [],
        array $new = [],
        ?int $branchId = null,
    ): self {
        $actor = Auth::user();

        $branchId ??= $subject?->getAttribute('branch_id')
            ?? ($subject instanceof Branch ? $subject->id : null)
            ?? (BranchContext::id() ?: null)     // sAdmin tanlagan filial (subyektsiz harakatlar ham shu filial jurnaliga tushadi)
            ?? $actor?->branch_id;

        return static::create([
            'branch_id' => $branchId,
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }

    /** Filial kontekstiga ko'ra ko'rinadigan yozuvlar. */
    public function scopeVisibleToContext($query)
    {
        $branchId = BranchContext::id();

        if ($branchId === null) {
            return $query;
        }

        return $query->where('audit_logs.branch_id', $branchId ?: -1);
    }
}
