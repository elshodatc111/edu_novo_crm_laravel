<?php

namespace App\Services;

use App\Support\BranchContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Ikki bosqichli tasdiqlash: pul chiqadigan amal (moliya chiqimi, xarajat, ehson chiqimi, ish haqi)
 * birinchi bosqichda faqat tekshiriladi va «Tekshiring» sahifasida ko'rsatiladi; foydalanuvchi to'g'riligini
 * tasdiqlagandan keyingina bajariladi. Ma'lumot serverda (sessiyada) saqlanadi, shuning uchun tasdiqlash
 * paytida uni brauzer orqali o'zgartirib bo'lmaydi. Har bir tasdiq bir marta ishlaydi.
 */
class ConfirmationService
{
    public const TTL_MINUTES = 15;
    private const KEY = 'confirmations';
    private const MAX_PENDING = 10;

    /**
     * @param  array<string,mixed>  $payload  amalni bajarish uchun kerakli ma'lumot
     * @param  array<int,array{0:string,1:string}>  $rows  «Tekshiring» sahifasidagi qatorlar [nom, qiymat]
     * @param  array{label:string,before:int,after:int}|null  $balance  balans o'zgarishi
     * @param  string|null  $warning  alohida ajratib ko'rsatiladigan qo'shimcha ogohlantirish matni
     */
    public function stash(string $kind, array $payload, string $title, array $rows, string $backUrl, ?array $balance = null, ?string $warning = null): string
    {
        $token = Str::random(40);
        $items = $this->all();

        // Eskirganlarini va ortiqchalarini tozalaymiz
        $items = array_filter($items, fn ($i) => $i['expires_at'] > now()->timestamp);
        $items = array_slice($items, -(self::MAX_PENDING - 1), null, true);

        $items[$token] = [
            'kind' => $kind,
            'payload' => $payload,
            'title' => $title,
            'rows' => $rows,
            'back' => $backUrl,
            'balance' => $balance,
            'warning' => $warning,
            'user_id' => Auth::id(),
            'branch_id' => BranchContext::id(),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->timestamp,
        ];

        session([self::KEY => $items]);

        return $token;
    }

    /** Tasdiqlash ma'lumotini qaytaradi (yaroqsiz, eskirgan yoki boshqa foydalanuvchiniki bo'lsa null). */
    public function peek(string $token): ?array
    {
        $item = $this->all()[$token] ?? null;

        if (! $item
            || $item['expires_at'] <= now()->timestamp
            || $item['user_id'] !== Auth::id()
            || $item['branch_id'] !== BranchContext::id()
            || Cache::has($this->usedKey($token))) {
            return null;
        }

        return $item;
    }

    /**
     * Tasdiqni bir martalik "olib qo'yadi": parallel ikki marta bosilsa ham amal faqat bir marta bajariladi.
     */
    public function claim(string $token): ?array
    {
        $item = $this->peek($token);
        if (! $item) {
            return null;
        }

        $lock = Cache::lock('confirm-lock:'.$token, 15);
        if (! $lock->get()) {
            return null;
        }

        try {
            if (Cache::has($this->usedKey($token))) {
                return null;
            }
            Cache::put($this->usedKey($token), true, now()->addMinutes(self::TTL_MINUTES + 5));
            $this->forget($token);

            return $item;
        } finally {
            $lock->release();
        }
    }

    public function forget(string $token): void
    {
        $items = $this->all();
        unset($items[$token]);
        session([self::KEY => $items]);
    }

    /** Amal bajarilmay qolsa (masalan, mablag' yetmadi), tasdiq qayta ishlatilmasligi uchun emas — foydalanuvchi qayta kiritadi. */
    private function usedKey(string $token): string
    {
        return 'confirm-used:'.$token;
    }

    /** @return array<string, array<string,mixed>> */
    private function all(): array
    {
        return (array) session(self::KEY, []);
    }
}
