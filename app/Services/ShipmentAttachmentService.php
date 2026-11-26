<?php

namespace App\Services;

use App\Enums\ShipmentStatus;
use App\Exceptions\ShipmentException;
use App\Models\Shipment;
use App\Models\ShipmentAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The carrier's packing list files (PDF, Excel, a photo) kept with a shipment. They live on the private disk under the store's
 * own folder and are only served through a route that checks the permission, never by a public address. Files can be added to a
 * shipment that has departed too, since the packing list often arrives after that.
 */
class ShipmentAttachmentService
{
    public const MAX_PER_SHIPMENT = 10;

    private const DISK = 'local';

    /**
     * Keeps the files with the shipment. All of them are refused together when they would take the shipment past the limit.
     *
     * @param  list<UploadedFile>  $files
     *
     * @throws ShipmentException
     */
    public function storeMany(Shipment $shipment, array $files, User $uploader): void
    {
        if ($files === []) {
            return;
        }

        if ($shipment->status === ShipmentStatus::Cancelled) {
            throw ShipmentException::attachmentsLocked();
        }

        if ($shipment->attachments()->count() + count($files) > self::MAX_PER_SHIPMENT) {
            throw ShipmentException::tooManyAttachments(self::MAX_PER_SHIPMENT);
        }

        foreach ($files as $file) {
            $shipment->attachments()->create([
                'path' => $file->store($this->folderOf($shipment), self::DISK),
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'uploaded_by' => $uploader->id,
            ]);
        }
    }

    /**
     * @throws ShipmentException
     */
    public function delete(ShipmentAttachment $attachment): void
    {
        if ($attachment->shipment->status === ShipmentStatus::Cancelled) {
            throw ShipmentException::attachmentsLocked();
        }

        Storage::disk(self::DISK)->delete($attachment->path);
        $attachment->delete();
    }

    /**
     * The file as the browser opens it (a PDF or photo is shown in place, an Excel sheet is downloaded).
     */
    public function response(ShipmentAttachment $attachment): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($attachment->path, $attachment->original_name);
    }

    private function folderOf(Shipment $shipment): string
    {
        return 'shipments/'.tenant('code').'/'.$shipment->id;
    }
}
