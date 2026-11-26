<?php

namespace App\Http\Requests\Concerns;

use App\Services\ShipmentAttachmentService;

/**
 * What a packing list file may be (a PDF, an Excel or Word sheet, or a photo) and how many a shipment can keep. Shared by the
 * request that adds files to an existing shipment and the one that saves a shipment with files.
 */
trait ValidatesPackingListFiles
{
    private const MAX_FILE_KILOBYTES = 10240;

    /**
     * @return array<int, string>
     */
    protected function packingListFileRules(): array
    {
        return ['file', 'mimes:pdf,xls,xlsx,csv,doc,docx,jpg,jpeg,png,webp', 'max:'.self::MAX_FILE_KILOBYTES];
    }

    /**
     * @return array<string, string>
     */
    protected function packingListFileMessages(): array
    {
        return [
            'attachments.max' => 'Satu pengiriman paling banyak '.ShipmentAttachmentService::MAX_PER_SHIPMENT.' file.',
            'attachments.*.mimes' => 'File harus berupa PDF, Excel, Word, atau foto (JPG, PNG, WebP).',
            'attachments.*.max' => 'Satu file paling besar 10 MB.',
            'attachments.*.uploaded' => 'File gagal diunggah. Coba lagi.',
        ];
    }
}
