<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Support\BranchContext;
use App\Support\PermissionRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Diqqat: User modeliga filial global scope qo'yilmagan (autentifikatsiya paytida
 * rekursiya bo'lmasligi uchun). Ro'yxatlarda `visibleToContext()` ishlatiladi.
 */
class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'branch_id', 'role', 'name', 'username', 'email', 'phone', 'password',
        'birthday', 'address', 'status', 'last_login_at',
        'phone2', 'about', 'balance', 'lead_source_id', 'archived_at', 'photo_path',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['status' => 'active'];

    /** @var array<int, string>|null */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'status' => UserStatus::class,
            'password' => 'hashed',
            'birthday' => 'date:Y-m-d',
            'last_login_at' => 'datetime',
            'archived_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'balance' => 'integer',
        ];
    }

    /** v8 A5: parol qachon o'zgarganini avtomatik yozib boradi (30 kunlik eslatma shunga qaraydi). */
    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if ($user->isDirty('password')) {
                $user->password_changed_at = now();
            }
        });
    }

    /** Parol 30 kundan ortiq oldin o'zgargan bo'lsa (yumshoq eslatma uchun - majburiy emas). */
    public function needsPasswordReminder(): bool
    {
        return $this->password_changed_at !== null && $this->password_changed_at->diffInDays(now()) >= 30;
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    /** O'quvchining guruhlardagi a'zoliklari (faol va tarixiy). */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupStudent::class, 'student_id');
    }

    public function balanceTransactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class, 'student_id');
    }

    /** O'quvchi haqida xodimlar yozgan ichki eslatmalar (v8 B8). */
    public function notes(): HasMany
    {
        return $this->hasMany(StudentNote::class, 'student_id');
    }

    /** v11: mobil ilova qurilma tokenlari (push-bildirishnoma uchun). */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    /** v11: bu foydalanuvchiga yuborilgan bildirishnomalar (o'qilgan/o'qilmagan holati bilan). */
    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SAdmin;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** v12: profil rasmining to'liq (ochiq) manzili, `storage:link` orqali (`photo_path` bo'lmasa null). */
    public function photoUrl(): ?string
    {
        return $this->photo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path) : null;
    }

    /** @return array<int, string> */
    public function permissionKeys(): array
    {
        if ($this->permissionCache === null) {
            $this->permissionCache = $this->permissions()->pluck('permission')->all();
        }

        return $this->permissionCache;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $this->permissionKeys(), true);
    }

    /** Ruxsatlarni to'liq almashtiradi (faqat ro'yxatdagi to'g'ri kalitlar saqlanadi). */
    public function syncPermissions(array $keys): void
    {
        $keys = array_values(array_unique(array_filter($keys, fn ($k) => PermissionRegistry::exists($k))));

        $this->permissions()->whereNotIn('permission', $keys)->delete();

        $existing = $this->permissions()->pluck('permission')->all();
        foreach (array_diff($keys, $existing) as $key) {
            $this->permissions()->create(['permission' => $key]);
        }

        $this->permissionCache = null;
    }

    /** Joriy filial kontekstiga ko'ra ko'rinadigan foydalanuvchilar. */
    public function scopeVisibleToContext(Builder $query): Builder
    {
        $branchId = BranchContext::id();

        if ($branchId === null) {
            return $query;
        }

        return $branchId === 0 ? $query->whereRaw('1 = 0') : $query->where('users.branch_id', $branchId);
    }

    public function scopeOfRole(Builder $query, Role $role): Builder
    {
        return $query->where('users.role', $role);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('users.name', 'like', $like)
                ->orWhere('users.username', 'like', $like)
                ->orWhere('users.phone', 'like', $like)
                ->orWhere('users.email', 'like', $like);
        });
    }
}
