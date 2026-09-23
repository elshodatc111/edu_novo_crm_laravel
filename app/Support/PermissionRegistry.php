<?php

namespace App\Support;

use App\Enums\Role;

/**
 * config/permissions.php faylidagi ruxsatlar ro'yxati bilan ishlash.
 */
class PermissionRegistry
{
    /** @return array<string, array{label: string, items: array<string, array{label: string, roles: array<int,string>}>}> */
    public static function groups(): array
    {
        return config('permissions.groups', []);
    }

    /** @return array<int, string> */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::groups() as $group) {
            $keys = array_merge($keys, array_keys($group['items']));
        }

        return $keys;
    }

    public static function exists(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    public static function label(string $key): string
    {
        foreach (self::groups() as $group) {
            if (isset($group['items'][$key])) {
                return $group['items'][$key]['label'];
            }
        }

        return $key;
    }

    /** Berilgan rol ega bo'la oladigan barcha ruxsatlar. */
    public static function forRole(Role $role): array
    {
        $result = [];
        foreach (self::groups() as $group) {
            foreach ($group['items'] as $key => $item) {
                if (in_array($role->value, $item['roles'], true)) {
                    $result[] = $key;
                }
            }
        }

        return $result;
    }

    /** Yangi hodim uchun boshlang'ich ruxsatlar. */
    public static function defaultsFor(Role $role): array
    {
        $defaults = config('permissions.defaults.'.$role->value, []);
        $allowed = self::forRole($role);

        if ($defaults === '*') {
            return $allowed;
        }

        return array_values(array_intersect($defaults, $allowed));
    }

    /** Interfeys uchun: faqat berilgan rolga mos guruhlar. */
    public static function groupsForRole(Role $role): array
    {
        $result = [];
        foreach (self::groups() as $groupKey => $group) {
            $items = array_filter($group['items'], fn ($item) => in_array($role->value, $item['roles'], true));
            if ($items) {
                $result[$groupKey] = ['label' => $group['label'], 'items' => $items];
            }
        }

        return $result;
    }
}
