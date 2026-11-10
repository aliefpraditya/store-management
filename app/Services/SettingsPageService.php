<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Saves the store settings page in one go: the paper and opname rules, the reasons of an opname variance, and the drivers.
 * The page has a single Save button, so all of it is written together or not at all.
 *
 * @phpstan-type NamedRow array{id?: int|null, name: string, is_active: bool}
 * @phpstan-type PageInput array{opname_reasons?: list<NamedRow>, drivers?: list<NamedRow>}
 */
class SettingsPageService
{
    public function __construct(
        private readonly StoreSettingService $settings,
        private readonly OpnameReasonService $opnameReasons,
        private readonly DriverService $drivers,
    ) {}

    /**
     * A list that is left out is left as it is.
     *
     * @param  array<string, mixed>  $input  the settings, plus the `opname_reasons` and `drivers` rows when they were sent
     */
    public function save(array $input): void
    {
        DB::transaction(function () use ($input): void {
            $this->settings->update($input);

            if (isset($input['opname_reasons'])) {
                $this->opnameReasons->sync($input['opname_reasons']);
            }

            if (isset($input['drivers'])) {
                $this->drivers->sync($input['drivers']);
            }
        });
    }
}
