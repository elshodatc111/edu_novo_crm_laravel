<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\PermissionRegistry;

/**
 * Ruxsat berish qoidalari:
 *  - sAdmin istalgan admin, menejer va o'qituvchiga istalgan ruxsatni bera oladi;
 *  - admin faqat O'ZIDA BOR ruxsatlarni, o'z filialidagi menejer va o'qituvchilarga bera oladi;
 *  - admin ruxsatlarini faqat sAdmin belgilaydi.
 */
class PermissionAssignmentService
{
    /** Ijrochi ushbu foydalanuvchiga bera oladigan (yoki olib tashlay oladigan) ruxsatlar. */
    public function grantable(User $actor, User $target): array
    {
        if (! $target->role->hasConfigurablePermissions()) {
            return [];
        }

        $forRole = PermissionRegistry::forRole($target->role);

        if ($actor->isSuperAdmin()) {
            return $forRole;
        }

        return array_values(array_intersect($forRole, $actor->permissionKeys()));
    }

    /**
     * Ruxsatlarni yangilaydi. Ijrochining vakolatidan tashqaridagi ruxsatlar o'zgarmaydi.
     *
     * @return array{added: array<int,string>, removed: array<int,string>}
     */
    public function update(User $actor, User $target, array $requested): array
    {
        $grantable = $this->grantable($actor, $target);
        $current = $target->permissions()->pluck('permission')->all();

        $requested = array_values(array_intersect($requested, $grantable));
        $untouched = array_values(array_diff($current, $grantable));
        $final = array_values(array_unique(array_merge($untouched, $requested)));

        $added = array_values(array_diff($final, $current));
        $removed = array_values(array_diff($current, $final));

        $target->syncPermissions($final);

        if ($added || $removed) {
            AuditLog::record(
                'permissions.updated',
                $target,
                "{$target->name} ruxsatlari o'zgartirildi",
                ['removed' => $removed],
                ['added' => $added],
            );
        }

        return ['added' => $added, 'removed' => $removed];
    }

    /** Yangi hodimga boshlang'ich ruxsatlarni beradi (ijrochi vakolati doirasida). */
    public function giveDefaults(User $actor, User $target): void
    {
        $defaults = PermissionRegistry::defaultsFor($target->role);
        $target->syncPermissions(array_values(array_intersect($defaults, $this->grantable($actor, $target))));
    }
}
