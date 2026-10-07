<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /** Ijrochi yarata oladigan yoki boshqara oladigan rollar. */
    public static function manageableRoles(User $actor): array
    {
        if ($actor->isSuperAdmin()) {
            return [Role::Admin, Role::Manager, Role::Teacher, Role::Operator];
        }

        $roles = [];

        if ($actor->role === Role::Admin && $actor->hasPermission('staff.manage')) {
            $roles = [Role::Manager, Role::Teacher, Role::Operator];
        } elseif (in_array($actor->role, [Role::Admin, Role::Manager], true) && $actor->hasPermission('teachers.manage')) {
            $roles = [Role::Teacher];
        }

        return $roles;
    }

    /** Ijrochi ro'yxatda ko'ra oladigan rollar. */
    public static function viewableRoles(User $actor): array
    {
        if ($actor->hasPermission('staff.view')) {
            return [Role::Admin, Role::Manager, Role::Teacher, Role::Operator];
        }

        return $actor->hasPermission('teachers.view') ? [Role::Teacher] : [];
    }

    public function viewAny(User $actor): bool
    {
        return self::viewableRoles($actor) !== [];
    }

    public function create(User $actor, Role $role): bool
    {
        return in_array($role, self::manageableRoles($actor), true);
    }

    public function manage(User $actor, User $target): bool
    {
        if ($actor->is($target)) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return $target->role !== Role::SAdmin;
        }

        return in_array($target->role, self::manageableRoles($actor), true)
            && $actor->branch_id !== null
            && $actor->branch_id === $target->branch_id;
    }

    /** v13: hodim qaysi lavozimlarga o'tkazilishi mumkin (hozirgi lavozimidan boshqa). Bo'sh - o'zgartirib bo'lmaydi. */
    public static function assignableRoles(User $actor, User $target): array
    {
        if (! (new self)->manage($actor, $target)) {
            return [];
        }

        return array_values(array_filter(
            self::manageableRoles($actor),
            fn (Role $r) => $r !== $target->role,
        ));
    }

    public function changeRole(User $actor, User $target, Role $newRole): bool
    {
        return in_array($newRole, self::assignableRoles($actor, $target), true);
    }

    public function assignPermissions(User $actor, User $target): bool
    {
        if ($actor->is($target) || ! $target->role->hasConfigurablePermissions()) {
            return false;
        }

        if ($actor->isSuperAdmin()) {
            return true;
        }

        return $actor->role === Role::Admin
            && $actor->hasPermission('permissions.assign')
            && in_array($target->role, [Role::Manager, Role::Teacher, Role::Operator], true)
            && $actor->branch_id !== null
            && $actor->branch_id === $target->branch_id;
    }
}
