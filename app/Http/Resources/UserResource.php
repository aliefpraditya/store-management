<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the `stores` relation to be loaded (and `permissions` for the edit form). Never exposes the password or email.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->role;
        $store = $this->assignedStore();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'role' => $role === null ? null : ['value' => $role->name, 'label' => $role->label, 'is_store_role' => ! $role->isSuperAdmin()],
            'store' => $store === null ? null : ['id' => $store->id, 'name' => $store->name],
            'extra_permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('name')->all(), []),
            'is_active' => $this->is_active,
            'is_self' => $this->is($request->user()),
            'edit_url' => route('central.users.edit', $this->resource),
            'status_url' => route('central.users.status', $this->resource),
        ];
    }
}
