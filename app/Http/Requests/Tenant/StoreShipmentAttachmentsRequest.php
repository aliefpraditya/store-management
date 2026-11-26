<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\ValidatesPackingListFiles;
use App\Services\ShipmentAttachmentService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Packing list files added to a shipment that already exists.
 */
class StoreShipmentAttachmentsRequest extends FormRequest
{
    use ValidatesPackingListFiles;

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
            'attachments' => ['required', 'array', 'min:1', 'max:'.ShipmentAttachmentService::MAX_PER_SHIPMENT],
            'attachments.*' => $this->packingListFileRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachments.required' => 'Pilih file packing list yang mau diunggah.',
            'attachments.min' => 'Pilih file packing list yang mau diunggah.',
            ...$this->packingListFileMessages(),
        ];
    }
}
