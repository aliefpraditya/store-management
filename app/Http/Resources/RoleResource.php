<?php

namespace App\Http\Resources;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects the `permissions` relation and `users_count` to be loaded.
 *
 * @mixin Role
 */
class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'is_system' => $this->is_system,
            'permission_names' => $this->permissions->pluck('name')->all(),
            'users_count' => $this->users_count,
            'edit_url' => route('central.roles.edit', $this->resource),
            'delete_url' => route('central.roles.destroy', $this->resource),
        ];
    }
}
