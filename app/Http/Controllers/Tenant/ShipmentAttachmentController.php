<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\ShipmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreShipmentAttachmentsRequest;
use App\Models\Shipment;
use App\Models\ShipmentAttachment;
use App\Services\ShipmentAttachmentService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The packing list files of a shipment: adding more, opening one, and removing one. They are on the private disk, so opening
 * goes through here and the permission of the shipments menu.
 */
class ShipmentAttachmentController extends Controller
{
    public function __construct(private readonly ShipmentAttachmentService $attachmentService) {}

    public function store(StoreShipmentAttachmentsRequest $request, Shipment $shipment): RedirectResponse
    {
        try {
            $this->attachmentService->storeMany($shipment, $request->file('attachments'), $request->user());
        } catch (ShipmentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'File ditambahkan.');
    }

    public function show(Shipment $shipment, ShipmentAttachment $attachment): StreamedResponse
    {
        return $this->attachmentService->response($attachment);
    }

    public function destroy(Shipment $shipment, ShipmentAttachment $attachment): RedirectResponse
    {
        try {
            $this->attachmentService->delete($attachment);
        } catch (ShipmentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'File dihapus.');
    }
}
