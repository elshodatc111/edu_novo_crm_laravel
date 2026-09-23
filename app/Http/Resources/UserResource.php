<?php

namespace App\Http\Resources;

use App\Support\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'photo_url' => $this->photoUrl(),
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'branch' => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null,
            'permissions' => $this->isSuperAdmin() ? PermissionRegistry::keys() : $this->permissionKeys(),
            'created_at' => $this->created_at?->toDateString(),
        ];
    }
}
