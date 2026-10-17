<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Group;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * v13: o'qituvchi «Usluga» ustamasi (staj bo'yicha qat'iy summa).
 *
 *  usluga (o'quvchi) = o'quvchilar soni x pog'onadagi «o'quvchi usluga» summasi
 *  usluga (bonus)    = bonusli o'quvchilar soni x pog'onadagi «bonus usluga» summasi
 *
 * Pog'ona = o'qituvchining staji guruh boshlangan sanada: 0-6, 6-12, 12-18, 18+ oy.
 * Faqat `branches.usluga_from` sanasidan keyin boshlangan guruhlarga qo'llanadi (eski guruhlar o'zgarmaydi).
 * Guruh boshlangach summa guruhga qotiriladi: keyin sozlama yoki ishga olingan sana o'zgarsa ham hisob o'zgarmaydi.
 */
class UslugaService
{
    public const TIERS = [
        1 => '0–6 oy',
        2 => '6–12 oy',
        3 => '12–18 oy',
        4 => '18 oydan keyin',
    ];

    public const STATE_OFF = 'off';            // yoqilmagan yoki guruh sanadan oldin boshlangan
    public const STATE_NO_HIRE_DATE = 'no_hired_at';
    public const STATE_NO_RATES = 'no_rates';
    public const STATE_OK = 'ok';

    /** @var array<int, Branch|null> */
    private array $branches = [];

    /** To'liq oylar soni (ishga olingandan `$at` gacha). */
    public function tenureMonths(User $teacher, CarbonInterface $at): ?int
    {
        $hired = $teacher->hired_at;
        if (! $hired) {
            return null;
        }

        $months = ($at->year - $hired->year) * 12 + ($at->month - $hired->month) - ($at->day < $hired->day ? 1 : 0);

        return max(0, $months);
    }

    public function tierForMonths(int $months): int
    {
        return match (true) {
            $months < 6 => 1,
            $months < 12 => 2,
            $months < 18 => 3,
            default => 4,
        };
    }

    /** @return array{tier:int,student:int,bonus:int}|null  sozlamadagi summalar (sozlanmagan bo'lsa null) */
    public function ratesFor(Branch $branch, int $tier): ?array
    {
        $rates = $branch->usluga_rates;
        if (! is_array($rates) || ! isset($rates[$tier])) {
            return null;
        }

        return ['tier' => $tier, 'student' => (int) ($rates[$tier]['student'] ?? 0), 'bonus' => (int) ($rates[$tier]['bonus'] ?? 0)];
    }

    /** O'qituvchi kartochkasi uchun: ishga olingan sana, staj va hozirgi pog'ona. */
    public function tenureInfo(User $teacher): array
    {
        $months = $this->tenureMonths($teacher, today());

        return [
            'hired_at' => $teacher->hired_at,
            'months' => $months,
            'tier' => $months === null ? null : $this->tierForMonths($months),
            'tier_label' => $months === null ? null : self::TIERS[$this->tierForMonths($months)],
        ];
    }

    /**
     * Guruh uchun usluga summalari. Guruh boshlangan va hisoblash mumkin bo'lsa - guruhga qotiriladi.
     *
     * @return array{state:string,tier:?int,tier_label:?string,student:int,bonus:int,frozen:bool}
     */
    public function resolve(Group $group): array
    {
        if ($group->usluga_tier !== null) {
            return [
                'state' => self::STATE_OK, 'tier' => (int) $group->usluga_tier, 'tier_label' => self::TIERS[(int) $group->usluga_tier] ?? null,
                'student' => (int) $group->usluga_student_amount, 'bonus' => (int) $group->usluga_bonus_amount, 'frozen' => true,
            ];
        }

        $off = ['state' => self::STATE_OFF, 'tier' => null, 'tier_label' => null, 'student' => 0, 'bonus' => 0, 'frozen' => false];

        $branch = $this->branches[$group->branch_id] ??= Branch::find($group->branch_id);
        if (! $branch || ! $branch->usluga_from || $group->starts_on->lt($branch->usluga_from)) {
            return $off;
        }

        $teacher = $group->teacher;
        $months = $teacher ? $this->tenureMonths($teacher, $group->starts_on) : null;
        if ($months === null) {
            return ['state' => self::STATE_NO_HIRE_DATE] + $off;
        }

        $tier = $this->tierForMonths($months);
        $rates = $this->ratesFor($branch, $tier);
        if ($rates === null) {
            return ['state' => self::STATE_NO_RATES, 'tier' => $tier, 'tier_label' => self::TIERS[$tier]] + $off;
        }

        $result = ['state' => self::STATE_OK, 'tier' => $tier, 'tier_label' => self::TIERS[$tier], 'student' => $rates['student'], 'bonus' => $rates['bonus'], 'frozen' => false];

        // Guruh boshlangan bo'lsa summani qotiramiz (keyingi o'zgarishlar hisobga ta'sir qilmasin)
        if ($group->starts_on->lte(today())) {
            $group->forceFill(['usluga_tier' => $tier, 'usluga_student_amount' => $rates['student'], 'usluga_bonus_amount' => $rates['bonus']])->saveQuietly();
            $result['frozen'] = true;
        }

        return $result;
    }
}
