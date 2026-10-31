<?php

namespace App\Http\Requests\Tenant;

use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Saves the counts typed on one page of an opname. Each row is an item with one or more quantities, each in
 * one of that product's units, e.g. 5 DUS and 3 PAIL.
 */
class SaveStockOpnameCountsRequest extends FormRequest
{
    private const MAX_QUANTITY = 999999999;

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
            'counts' => ['required', 'array', 'min:1'],
            'counts.*.item_id' => ['required', 'integer'],
            'counts.*.parts' => ['present', 'array'],
            'counts.*.parts.*.unit_code' => ['required', 'string'],
            'counts.*.parts.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'],
            'counts.*.reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Each row must be an item of this opname, and each unit one of that product's units.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var StockOpname $opname */
                $opname = $this->route('stock_opname');
                $rows = $this->input('counts');

                $itemsById = StockOpnameItem::query()
                    ->where('stock_opname_id', $opname->id)
                    ->whereIn('id', array_column($rows, 'item_id'))
                    ->with('product.productUnits')
                    ->get()
                    ->keyBy('id');

                foreach ($rows as $index => $row) {
                    $item = $itemsById->get($row['item_id']);

                    if ($item === null) {
                        $validator->errors()->add("counts.{$index}.item_id", 'Barang tidak ada di opname ini.');

                        continue;
                    }

                    $unitCodes = $item->product->productUnits->pluck('unit_code')->all();

                    foreach ($row['parts'] as $partIndex => $part) {
                        if (! in_array($part['unit_code'], $unitCodes, true)) {
                            $validator->errors()->add("counts.{$index}.parts.{$partIndex}.unit_code", 'Satuan tidak dipakai barang ini.');
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counts.*.parts.*.quantity.numeric' => 'Jumlah harus berupa angka.',
            'counts.*.parts.*.quantity.min' => 'Jumlah tidak boleh negatif.',
            'counts.*.parts.*.quantity.decimal' => 'Jumlah maksimal 3 angka desimal.',
            'counts.*.parts.*.quantity.max' => 'Jumlah terlalu besar.',
        ];
    }
}
