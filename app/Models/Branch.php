<?php

namespace App\Models;

use App\Enums\BranchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Branch extends Model
{
    /** v9: umumiy Edunova rangi (agar filial o'z rangini tanlamagan bo'lsa). */
    public const DEFAULT_BRAND_COLOR = '#dc2626';

    protected $fillable = [
        'name', 'code', 'phone', 'address', 'charity_percent', 'discount_days_before', 'discount_days_after', 'sms_enabled', 'status', 'opened_at', 'closed_at', 'closed_reason',
        'eskiz_email', 'eskiz_password', 'eskiz_from', 'sms_auto_debt', 'sms_auto_absent',
        'stir', 'director_name', 'director_title', 'contract_template',
        'brand_color', 'public_about',
    ];

    protected $hidden = ['eskiz_password'];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'eskiz_password' => 'encrypted',
            'charity_percent' => 'float',
            'sms_enabled' => 'boolean',
            'sms_auto_debt' => 'boolean',
            'sms_auto_absent' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', BranchStatus::Active);
    }

    public function isActive(): bool
    {
        return $this->status === BranchStatus::Active;
    }

    public function hasOwnSmsAccount(): bool
    {
        return filled($this->eskiz_email) && filled($this->eskiz_password);
    }

    /** v9: ochiq murojaat sahifasida ishlatiladigan rang — filial o'zi tanlagan yoki standart. */
    public function effectiveBrandColor(): string
    {
        return $this->brand_color ?: self::DEFAULT_BRAND_COLOR;
    }

    /** Filial nomidan takrorlanmaydigan kod (slug) yasaydi. */
    public static function makeCode(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'filial';
        $code = $base;
        $i = 2;

        while (static::where('code', $code)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $code = $base.'-'.$i++;
        }

        return $code;
    }
}
