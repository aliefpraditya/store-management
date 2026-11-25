<?php

namespace App\Http\Controllers\Tenant;

use App\Exceptions\PurchaseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StorePurchaseAttachmentsRequest;
use App\Models\Purchase;
use App\Models\PurchaseAttachment;
use App\Services\PurchaseAttachmentService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The nota photos and PDFs of a purchase: adding more, opening one, and removing one. They are on the private disk, so
 * opening goes through here and the permission of the purchases menu.
 */
class PurchaseAttachmentController extends Controller
{
    public function __construct(private readonly PurchaseAttachmentService $attachmentService) {}

    public function store(StorePurchaseAttachmentsRequest $request, Purchase $purchase): RedirectResponse
    {
        try {
            $this->attachmentService->storeMany($purchase, $request->file('attachments'), $request->user());
        } catch (PurchaseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Lampiran ditambahkan.');
    }

    public function show(Purchase $purchase, PurchaseAttachment $attachment): StreamedResponse
    {
        return $this->attachmentService->response($attachment);
    }

    public function destroy(Purchase $purchase, PurchaseAttachment $attachment): RedirectResponse
    {
        try {
            $this->attachmentService->delete($attachment);
        } catch (PurchaseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Lampiran dihapus.');
    }
}
