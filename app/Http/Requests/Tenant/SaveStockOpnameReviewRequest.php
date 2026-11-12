<?php

namespace App\Http\Requests\Tenant;

use App\Models\OpnameReason;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saves the review of one page of an opname: for each item its reason, a note, whether the admin reviewed it, and for
 * damaged goods where the damaged units go (dead stock, a DG product with its selling price per base unit, or full price).
 */
class SaveStockOpnameReviewRequest extends FormRequest
{
    private const MAX_QUANTITY = 999999999;

    private const MAX_PRICE = 999999999999;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $quantity = ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,3'];
        $packs = ['nullable', 'integer', 'min:0', 'max:'.self::MAX_QUANTITY];

        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.item_id' => ['required', 'integer'],
            'rows.*.reason_id' => ['nullable', 'integer', Rule::exists('opname_reasons', 'id')],
            'rows.*.note' => ['nullable', 'string', 'max:255'],
            'rows.*.is_reviewed' => ['required', 'boolean'],
            'rows.*.dead_stock_quantity' => $quantity,
            'rows.*.damaged_goods_quantity' => $quantity,
            'rows.*.full_price_quantity' => $quantity,
            'rows.*.damaged_goods_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PRICE, 'decimal:0,2'],
            'rows.*.dead_stock_packs' => $packs,
            'rows.*.damaged_goods_packs' => $packs,
            'rows.*.full_price_packs' => $packs,
        ];
    }

    /**
     * Each row must be an item of this opname, and a reason switched off may only stay on the item it already has.
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
                $rows = $this->input('rows');

                $itemsById = StockOpnameItem::query()->where('stock_opname_id', $opname->id)->whereIn('id', array_column($rows, 'item_id'))->get()->keyBy('id');
                $inactiveReasonIds = OpnameReason::query()->where('is_active', false)->pluck('id')->all();

                foreach ($rows as $index => $row) {
                    $item = $itemsById->get($row['item_id']);

                    if ($item === null) {
                        $validator->errors()->add("rows.{$index}.item_id", 'Barang tidak ada di opname ini.');

                        continue;
                    }

                    $reasonId = $row['reason_id'] ?? null;

                    if ($reasonId !== null && in_array((int) $reasonId, $inactiveReasonIds, true) && (int) $reasonId !== $item->reason_id) {
                        $validator->errors()->add("rows.{$index}.reason_id", 'Alasan itu sudah dinonaktifkan.');
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
            'rows.*.reason_id.exists' => 'Alasan tidak dikenal.',
            'rows.*.dead_stock_quantity.numeric' => 'Jumlah dead stock harus berupa angka.',
            'rows.*.dead_stock_quantity.min' => 'Jumlah dead stock tidak boleh negatif.',
            'rows.*.dead_stock_quantity.decimal' => 'Jumlah dead stock maksimal 3 angka desimal.',
            'rows.*.damaged_goods_quantity.numeric' => 'Jumlah barang DG harus berupa angka.',
            'rows.*.damaged_goods_quantity.min' => 'Jumlah barang DG tidak boleh negatif.',
            'rows.*.damaged_goods_quantity.decimal' => 'Jumlah barang DG maksimal 3 angka desimal.',
            'rows.*.full_price_quantity.numeric' => 'Jumlah layak jual penuh harus berupa angka.',
            'rows.*.full_price_quantity.min' => 'Jumlah layak jual penuh tidak boleh negatif.',
            'rows.*.full_price_quantity.decimal' => 'Jumlah layak jual penuh maksimal 3 angka desimal.',
            'rows.*.dead_stock_packs.integer' => 'Jumlah rol utuh dead stock harus bilangan bulat.',
            'rows.*.damaged_goods_packs.integer' => 'Jumlah rol utuh barang DG harus bilangan bulat.',
            'rows.*.full_price_packs.integer' => 'Jumlah rol utuh yang kembali ke stok jual harus bilangan bulat.',
            'rows.*.damaged_goods_price.numeric' => 'Harga jual barang DG harus berupa angka.',
            'rows.*.damaged_goods_price.min' => 'Harga jual barang DG tidak boleh negatif.',
            'rows.*.damaged_goods_price.decimal' => 'Harga jual barang DG maksimal 2 angka desimal.',
        ];
    }
}
