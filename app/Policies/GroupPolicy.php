<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Group;
use App\Models\User;

class GroupPolicy
{
    private function ownsAsTeacher(User $user, Group $group): bool
    {
        return $user->role === Role::Teacher && $group->teacher_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === Role::Teacher ? $user->hasPermission('attendance.view') : $user->hasPermission('groups.view');
    }

    public function view(User $user, Group $group): bool
    {
        if ($user->role === Role::Teacher) {
            return $this->ownsAsTeacher($user, $group) && $user->hasPermission('attendance.view');
        }

        return $user->hasPermission('groups.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('groups.create');
    }

    public function update(User $user, Group $group): bool
    {
        return $user->hasPermission('groups.update');
    }

    /** v13: boshlanmagan guruhni arxivlash - faqat `groups.delete` (admin/sAdmin). */
    public function delete(User $user, Group $group): bool
    {
        return $user->role !== Role::Teacher && $user->hasPermission('groups.delete');
    }

    public function manageMembers(User $user, Group $group): bool
    {
        return $user->hasPermission('groups.members');
    }

    public function takeAttendance(User $user, Group $group): bool
    {
        if (! $user->hasPermission('attendance.take')) {
            return false;
        }

        return $user->role === Role::Teacher ? $this->ownsAsTeacher($user, $group) : true;
    }

    /** v8: o'tgan kun davomatini tuzatish - faqat sAdmin/admin (`attendance.edit_past`), o'qituvchiga emas. */
    public function editPastAttendance(User $user, Group $group): bool
    {
        return $user->hasPermission('attendance.edit_past');
    }
}
