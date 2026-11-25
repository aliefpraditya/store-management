<?php

namespace App\Http\Requests\Tenant;

use App\Services\PurchaseAttachmentService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Nota photos or PDFs added to a purchase that already exists.
 */
class StorePurchaseAttachmentsRequest extends FormRequest
{
    private const MAX_FILE_KILOBYTES = 5120;

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
            'attachments' => ['required', 'array', 'min:1', 'max:'.PurchaseAttachmentService::MAX_PER_PURCHASE],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.self::MAX_FILE_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachments.required' => 'Pilih file nota yang mau diunggah.',
            'attachments.min' => 'Pilih file nota yang mau diunggah.',
            'attachments.max' => 'Satu nota paling banyak '.PurchaseAttachmentService::MAX_PER_PURCHASE.' lampiran.',
            'attachments.*.mimes' => 'Lampiran harus berupa foto (JPG, PNG, WebP) atau PDF.',
            'attachments.*.max' => 'Satu lampiran paling besar 5 MB.',
            'attachments.*.uploaded' => 'Lampiran gagal diunggah. Coba lagi.',
        ];
    }
}
