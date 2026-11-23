<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Http\Middleware\EnsureUserCanAccessStore;
use App\Models\DeliveryNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A delivery note as a reference inside another list (the COD lists): its number and status, and the page to open it for
 * those who may view delivery notes (`show_url` is null for the rest).
 *
 * @mixin DeliveryNote
 */
class DeliveryNoteLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'show_url' => EnsureUserCanAccessStore::grants($request, Permission::DeliveryNotesView)
                ? route('tenant.delivery-notes.show', ['tenant' => tenant('code'), 'delivery_note' => $this->id])
                : null,
        ];
    }
}
