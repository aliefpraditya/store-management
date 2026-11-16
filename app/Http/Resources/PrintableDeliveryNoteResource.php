<?php

namespace App\Http\Resources;

use App\Models\DeliveryNote;
use App\Services\StoreSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the browser prints for a delivery note: the note itself plus the store's letterhead and whether this print
 * is a copy. Expects `items` and `sale` to be loaded.
 *
 * @phpstan-import-type Letterhead from StoreSettingService
 *
 * @mixin DeliveryNote
 */
class PrintableDeliveryNoteResource extends JsonResource
{
    /**
     * @param  Letterhead  $letterhead
     */
    public function __construct(DeliveryNote $resource, private readonly bool $isCopy, private readonly array $letterhead)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...DeliveryNoteResource::make($this->resource)->resolve($request),
            'created_at' => $this->created_at->toIso8601String(),
            'is_copy' => $this->isCopy,
            'store' => $this->letterhead,
        ];
    }
}
