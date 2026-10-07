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

        // v13: qo'shimcha filialli operator - o'ziga ruxsat berilgan filiallardan birini tanlay oladi
        if ($user->role === Role::Operator && $user->branch_id) {
            $extra = self::extraBranchIds($user);

            if ($extra !== []) {
                $header = request()?->header(self::HEADER);
                $wanted = $header !== null ? (int) $header : (int) session(self::SESSION_KEY);

                if ($wanted > 0 && ($wanted === (int) $user->branch_id || in_array($wanted, $extra, true))) {
                    return $wanted;
                }
            }
        }

        return $user->branch_id ? (int) $user->branch_id : 0;
    }

    /**
     * v13: foydalanuvchiga (operator) berilgan faol qo'shimcha filial ID lari. Global scope bo'lmagan oddiy so'rov
     * (rekursiya bo'lmasligi uchun) va so'rov davomida bir marta keshlanadi.
     *
     * @return array<int, int>
     */
    public static function extraBranchIds(\App\Models\User $user): array
    {
        $key = 'extra_branch_ids_'.$user->id;
        $attrs = request()?->attributes;

        if ($attrs && $attrs->has($key)) {
            return $attrs->get($key);
        }

        $ids = \Illuminate\Support\Facades\DB::table('user_branches')
            ->join('branches', 'branches.id', '=', 'user_branches.branch_id')
            ->where('user_branches.user_id', $user->id)->where('branches.status', 'active')
            ->pluck('user_branches.branch_id')->map(fn ($v) => (int) $v)->all();

        $attrs?->set($key, $ids);

        return $ids;
    }

    /** Foydalanuvchi ishlay oladigan barcha filial ID lari (o'zi + qo'shimcha). */
    public static function accessibleBranchIds(\App\Models\User $user): array
    {
        return array_values(array_unique(array_filter([(int) $user->branch_id, ...self::extraBranchIds($user)])));
    }

    /** Keshni tozalash (ruxsatlar shu so'rovda o'zgargan bo'lsa). */
    public static function forgetExtra(\App\Models\User $user): void
    {
        request()?->attributes->remove('extra_branch_ids_'.$user->id);
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
