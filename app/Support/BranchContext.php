<?php

namespace App\Support;

use App\Enums\Role;
use Illuminate\Support\Facades\Auth;

/**
 * Joriy so'rov qaysi filial ma'lumotlari bilan ishlashini aniqlaydi.
 *
 *  - admin / menejer / o'qituvchi / o'quvchi: doim o'z filiali;
 *  - sAdmin (veb): tanlangan filial (sessiyada), tanlanmagan bo'lsa barcha filiallar;
 *  - sAdmin (mobil API, v11): so'rovdagi `X-Branch-Id` sarlavhasi - mobil API stateless
 *    (sessiya cookie yubormaydi), shuning uchun sAdmin har so'rovda shu sarlavha bilan
 *    qaysi filial bilan ishlayotganini ko'rsatadi; berilmasa - barcha filiallar (faqat o'qish);
 *  - tizimga kirmagan holat (konsol, navbat): cheklov yo'q.
 */
class BranchContext
{
    public const SESSION_KEY = 'current_branch_id';

    public const HEADER = 'X-Branch-Id';

    /** Ma'lumotlar cheklanishi kerak bo'lgan filial ID si, yoki null (cheklovsiz). */
    public static function id(): ?int
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        if ($user->role === Role::SAdmin) {
            $header = request()?->header(self::HEADER);

            if ($header !== null) {
                $id = (int) $header;

                return $id > 0 ? $id : null;
            }

            $id = session(self::SESSION_KEY);

            return $id ? (int) $id : null;
        }

        return $user->branch_id ? (int) $user->branch_id : 0;
    }

    /** Faqat joriy filialdagi yozuvni qabul qiladigan `exists` qoidasi. */
    public static function exists(string $table, string $column = 'id'): \Illuminate\Validation\Rules\Exists
    {
        return \Illuminate\Validation\Rule::exists($table, $column)->where('branch_id', self::id() ?: 0);
    }

    public static function select(?int $branchId): void
    {
        if ($branchId) {
            session([self::SESSION_KEY => $branchId]);
        } else {
            session()->forget(self::SESSION_KEY);
        }
    }
}
