<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveOpnameReasonRequest;
use App\Models\OpnameReason;
use App\Services\OpnameReasonService;
use Illuminate\Http\RedirectResponse;

/**
 * The reasons of an opname variance are managed on the store settings page, so saving goes back to where it came from.
 */
class OpnameReasonController extends Controller
{
    public function __construct(private readonly OpnameReasonService $reasons) {}

    public function store(SaveOpnameReasonRequest $request): RedirectResponse
    {
        $this->reasons->create($request->validated('name'));

        return back()->with('success', 'Alasan ditambahkan.');
    }

    public function update(SaveOpnameReasonRequest $request, OpnameReason $opnameReason): RedirectResponse
    {
        $this->reasons->update($opnameReason, $request->validated());

        return back()->with('success', 'Alasan disimpan.');
    }
}
