<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Group;
use Carbon\CarbonInterface;

/**
 * Oldindan to'lov chegirmasi muddati: guruh boshlanishidan `discount_days_before` kun oldin
 * va `discount_days_after` kun keyingacha (filial sozlamalarida belgilanadi).
 */
class DiscountWindow
{
    /** @var array<int, Branch> */
    private array $branches = [];

    public function allows(Group $group, ?CarbonInterface $on = null): bool
    {
        [$before, $after] = $this->days($group->branch_id);
        $on ??= today();

        return $on->gte($group->starts_on->copy()->subDays($before)) && $on->lte($group->starts_on->copy()->addDays($after));
    }

    /** @return array{0:int,1:int} [oldin, keyin] */
    public function days(int $branchId): array
    {
        $branch = $this->branches[$branchId] ??= Branch::findOrFail($branchId);

        return [(int) $branch->discount_days_before, (int) $branch->discount_days_after];
    }

    public function describe(int $branchId): string
    {
        [$before, $after] = $this->days($branchId);

        return "guruh boshlanishidan {$before} kun oldin va {$after} kun keyingacha";
    }
}
