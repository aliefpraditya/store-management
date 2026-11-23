<?php

namespace App\Http\Requests\Tenant;

use App\Models\Driver;
use App\Models\OpnameReason;
use App\Services\StoreSettingService;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The store's settings page, saved with one button: what its paper says and how wide the receipt roll is, two rules of
 * the stock opname, the reasons of an opname variance, and the drivers. The reasons and drivers are sent as the whole
 * list; a list that is left out is left as it is.
 */
class SaveStoreSettingsRequest extends FormRequest
{
    private const MAX_AMOUNT = 999999999999;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'receipt_name' => ['nullable', 'string', 'max:255'],
            'receipt_address' => ['nullable', 'string', 'max:500'],
            'receipt_phone' => ['nullable', 'string', 'max:100'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'paper_width' => ['required', 'integer', Rule::in(StoreSettingService::PAPER_WIDTHS)],
            'receipt_printer' => ['nullable', 'string', 'max:255'],
            'delivery_note_printer' => ['nullable', 'string', 'max:255'],
            'is_qz_tray_enabled' => ['required', 'boolean'],
            'large_variance_threshold' => ['nullable', 'numeric', 'gt:0', 'max:'.self::MAX_AMOUNT, 'decimal:0'],
            'lock_sales_during_opname' => ['required', 'boolean'],
            'auto_print_receipt' => ['required', 'boolean'],
            'selling_price_step' => ['nullable', 'integer', 'min:0', 'max:'.StoreSettingService::MAX_SELLING_PRICE_STEP],
            'opname_reasons' => ['sometimes', 'array'],
            'opname_reasons.*.id' => ['nullable', 'integer', Rule::exists('opname_reasons', 'id')],
            'opname_reasons.*.name' => ['required', 'string', 'max:100'],
            'opname_reasons.*.is_active' => ['required', 'boolean'],
            'drivers' => ['sometimes', 'array'],
            'drivers.*.id' => ['nullable', 'integer', Rule::exists('drivers', 'id')],
            'drivers.*.name' => ['required', 'string', 'max:100'],
            'drivers.*.is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->requireDistinctNames($validator, 'opname_reasons', OpnameReason::class, 'Alasan dengan nama itu sudah ada.'),
            fn (Validator $validator) => $this->requireDistinctNames($validator, 'drivers', Driver::class, 'Nama sopir sudah ada.'),
            $this->requireSystemReasonsStayOn(...),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'paper_width.in' => 'Lebar kertas nota hanya 58, 76, atau 80 mm.',
            'large_variance_threshold.numeric' => 'Ambang selisih harus berupa angka.',
            'large_variance_threshold.gt' => 'Ambang selisih harus lebih dari 0. Kosongkan untuk tanpa batas.',
            'large_variance_threshold.decimal' => 'Ambang selisih harus bilangan bulat rupiah.',
            'selling_price_step.integer' => 'Kelipatan pembulatan harus bilangan bulat rupiah.',
            'selling_price_step.min' => 'Kelipatan pembulatan tidak boleh negatif. Isi 0 untuk tanpa pembulatan.',
            'selling_price_step.max' => 'Kelipatan pembulatan maksimal Rp :max.',
            'opname_reasons.*.name.required' => 'Isi nama alasan.',
            'opname_reasons.*.name.max' => 'Nama alasan maksimal :max huruf.',
            'drivers.*.name.required' => 'Nama sopir wajib diisi.',
            'drivers.*.name.max' => 'Nama sopir maksimal :max karakter.',
        ];
    }

    /**
     * A name may appear once in the list, and not be the name of a row that was left out of the list (names are not
     * told apart by upper or lower case, like the database does not).
     *
     * @param  class-string<OpnameReason|Driver>  $model
     */
    private function requireDistinctNames(Validator $validator, string $key, string $model, string $message): void
    {
        $rows = $this->input($key);

        if (! is_array($rows)) {
            return;
        }

        $sentIds = collect($rows)->pluck('id')->filter()->all();
        $takenNames = $model::query()->whereNotIn('id', $sentIds)->pluck('name')->map(fn (string $name): string => mb_strtolower(trim($name)))->flip();

        foreach ($rows as $index => $row) {
            $name = is_array($row) && is_string($row['name'] ?? null) ? mb_strtolower(trim($row['name'])) : '';

            if ($name === '') {
                continue;
            }

            if ($takenNames->has($name)) {
                $validator->errors()->add("{$key}.{$index}.name", $message);
            }

            $takenNames->put($name, true);
        }
    }

    private function requireSystemReasonsStayOn(Validator $validator): void
    {
        $rows = $this->input('opname_reasons');

        if (! is_array($rows)) {
            return;
        }

        $systemReasonNames = OpnameReason::query()->where('is_system', true)->pluck('name', 'id');

        foreach ($rows as $index => $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;

            if ($id !== null && $systemReasonNames->has((int) $id) && ! filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $validator->errors()->add("opname_reasons.{$index}.is_active", "Alasan {$systemReasonNames[(int) $id]} bawaan toko dan tidak bisa dinonaktifkan.");
            }
        }
    }
}
