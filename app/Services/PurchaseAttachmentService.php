<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseException;
use App\Models\Purchase;
use App\Models\PurchaseAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The photos and PDFs of the supplier's nota kept with a purchase. They live on the private disk under the store's own
 * folder and are only served through a route that checks the permission, never by a public address.
 */
class PurchaseAttachmentService
{
    public const MAX_PER_PURCHASE = 10;

    private const DISK = 'local';

    /**
     * Keeps the files with the purchase. All of them are refused together when they would take the purchase past the limit.
     *
     * @param  list<UploadedFile>  $files
     *
     * @throws PurchaseException
     */
    public function storeMany(Purchase $purchase, array $files, User $uploader): void
    {
        if ($files === []) {
            return;
        }

        if ($purchase->status === PurchaseStatus::Cancelled) {
            throw PurchaseException::attachmentsLocked();
        }

        if ($purchase->attachments()->count() + count($files) > self::MAX_PER_PURCHASE) {
            throw PurchaseException::tooManyAttachments(self::MAX_PER_PURCHASE);
        }

        foreach ($files as $file) {
            $purchase->attachments()->create([
                'path' => $file->store($this->folderOf($purchase), self::DISK),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'uploaded_by' => $uploader->id,
            ]);
        }
    }

    /**
     * @throws PurchaseException
     */
    public function delete(PurchaseAttachment $attachment): void
    {
        if ($attachment->purchase->status === PurchaseStatus::Cancelled) {
            throw PurchaseException::attachmentsLocked();
        }

        Storage::disk(self::DISK)->delete($attachment->path);
        $attachment->delete();
    }

    /**
     * The file as the browser opens it (a photo or PDF is shown in place).
     */
    public function response(PurchaseAttachment $attachment): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($attachment->path, $attachment->original_name);
    }

    private function folderOf(Purchase $purchase): string
    {
        return 'purchases/'.tenant('code').'/'.$purchase->id;
    }
}
