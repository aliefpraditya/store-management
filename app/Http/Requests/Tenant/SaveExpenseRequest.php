<?php

namespace App\Http\Requests\Tenant;

use App\Enums\ExpenseSource;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new expense as an admin records it: from the drawer of a running shift, or from another source on a given day.
 */
class SaveExpenseRequest extends FormRequest
{
    private const MAX_AMOUNT = 999999999999;

    private const DATE_FORMAT = 'Y-m-d';

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
            'client_uuid' => ['required', 'uuid'],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'source' => ['required', Rule::enum(ExpenseSource::class)],
            'cash_shift_id' => ['required_if:source,'.ExpenseSource::Drawer->value, 'nullable', 'integer', Rule::exists('cash_shifts', 'id')],
            'business_date' => ['required_if:source,'.ExpenseSource::Other->value, 'nullable', 'date_format:'.self::DATE_FORMAT, 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'paid_to' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'client_uuid.required' => 'Pengeluaran tidak punya penanda. Muat ulang layar lalu coba lagi.',
            'client_uuid.uuid' => 'Penanda pengeluaran tidak valid. Muat ulang layar lalu coba lagi.',
            'expense_category_id.required' => 'Pilih kategori.',
            'expense_category_id.exists' => 'Kategori tidak ditemukan.',
            'source.required' => 'Pilih sumber uang.',
            'cash_shift_id.required_if' => 'Pilih laci shift yang dipakai.',
            'cash_shift_id.exists' => 'Shift tidak ditemukan.',
            'business_date.required_if' => 'Isi tanggal pengeluaran.',
            'business_date.date_format' => 'Tanggal pengeluaran tidak valid.',
            'business_date.before_or_equal' => 'Tanggal pengeluaran tidak boleh di masa depan.',
            'description.required' => 'Keterangan wajib diisi.',
            'amount.required' => 'Isi nominal.',
            'amount.numeric' => 'Nominal harus berupa angka.',
            'amount.gt' => 'Nominal harus lebih dari nol.',
            'amount.decimal' => 'Nominal maksimal 2 angka desimal.',
        ];
    }

    /**
     * The day a non-drawer expense was paid; a drawer expense takes its shift's day, so none is sent.
     */
    public function businessDate(): ?CarbonImmutable
    {
        $businessDate = $this->validated('business_date');

        return blank($businessDate) ? null : CarbonImmutable::createFromFormat('!'.self::DATE_FORMAT, (string) $businessDate);
    }
}
