<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PermissionAssignmentService;
use App\Support\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function edit(User $user, PermissionAssignmentService $service)
    {
        $this->authorize('assignPermissions', $user);

        return view('staff.permissions', [
            'staff' => $user,
            'groups' => PermissionRegistry::groupsForRole($user->role),
            'current' => $user->permissions()->pluck('permission')->all(),
            'grantable' => $service->grantable(auth()->user(), $user),
        ]);
    }

    public function update(Request $request, User $user, PermissionAssignmentService $service): RedirectResponse
    {
        $this->authorize('assignPermissions', $user);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $service->update($request->user(), $user, $data['permissions'] ?? []);

        return redirect()->route('staff.permissions.edit', $user)->with('success', 'Ruxsatlar saqlandi.');
    }
}
