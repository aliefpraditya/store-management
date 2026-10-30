<?php

namespace App\Http\Resources;

use App\Models\CashShift;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The open shift of the cashier screen. Expects `register` to be loaded.
 *
 * @mixin CashShift
 */
class CashShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'register' => ['id' => $this->register->id, 'code' => $this->register->code, 'name' => $this->register->name],
            'cashier_name' => $this->cashier_name,
            'opened_at' => $this->opened_at->toIso8601String(),
            'opening_cash' => $this->opening_cash,
            'close_url' => route('tenant.cashier.shifts.close', ['tenant' => tenant('code'), 'shift' => $this->id]),
        ];
    }
}
