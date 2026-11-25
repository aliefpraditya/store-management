<?php

namespace App\Services;

use App\Models\Carrier;
use App\Models\Supplier;

/**
 * The provinces and cities already typed on the store's suppliers and carriers, offered as suggestions while typing a new one.
 * They are free text, so this keeps spellings consistent without a master list.
 */
class RegionSuggestions
{
    /**
     * @return array{provinces: list<string>, cities: list<string>}
     */
    public function all(): array
    {
        return [
            'provinces' => $this->distinct('province'),
            'cities' => $this->distinct('city'),
        ];
    }

    /**
     * @return list<string>
     */
    private function distinct(string $column): array
    {
        return Supplier::query()->whereNotNull($column)->pluck($column)
            ->merge(Carrier::query()->whereNotNull($column)->pluck($column))
            ->unique(fn (string $value): string => mb_strtolower($value))
            ->sortBy(fn (string $value): string => mb_strtolower($value))
            ->values()
            ->all();
    }
}
