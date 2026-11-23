<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\CashShift;
use App\Models\Sale;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Looks receipts up: the store-wide history for admins, and the receipts of one shift for the till's dialog.
 *
 * @phpstan-type HistoryFilters array{from: string|null, to: string|null, search: string|null, cashier_id: int|null, status: string|null, delivery?: string|null}
 * @phpstan-type HistoryTotals array{receipt_count: int, total: string}
 */
class SaleHistoryService
{
    /** The `delivery` filter value that narrows the history to receipts not delivered yet. */
    public const PENDING_DELIVERY_FILTER = 'pending';

    /**
     * @param  HistoryFilters  $filters
     * @return LengthAwarePaginator<int, Sale>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->query($filters)
            ->with(['payments', 'deliveryNotes'])
            ->withCount('items')
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * What the completed receipts of the filter add up to; voided receipts are listed but not counted.
     *
     * @param  HistoryFilters  $filters
     * @return HistoryTotals
     */
    public function totals(array $filters): array
    {
        $completedQuery = $this->query($filters)->where('status', SaleStatus::Completed);

        return [
            'receipt_count' => (clone $completedQuery)->count(),
            'total' => (string) BigDecimal::of((string) (clone $completedQuery)->sum('total'))->toScale(2),
        ];
    }

    /**
     * The newest receipts of one shift, with their lines and payments, for the till's receipt dialog.
     *
     * @return Collection<int, Sale>
     */
    public function ofShift(CashShift $shift, ?string $search, int $limit): Collection
    {
        $term = trim((string) $search);

        return Sale::query()
            ->where('cash_shift_id', $shift->id)
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$term}%")
                ->orWhere('customer_name', 'like', "%{$term}%")))
            ->with(['items', 'payments', 'register', 'deliveryNotes'])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * How many completed receipts to be delivered have not reached the customer yet, of the whole store or of one shift.
     */
    public function pendingDeliveryCount(?CashShift $shift = null): int
    {
        return Sale::query()
            ->pendingDelivery()
            ->when($shift, fn (Builder $query, CashShift $shift) => $query->where('cash_shift_id', $shift->id))
            ->count();
    }

    /**
     * The cashiers who rang up at least one receipt, for the cashier filter.
     *
     * @return list<array{id: int, name: string}>
     */
    public function cashiers(): array
    {
        return Sale::query()
            ->select('cashier_id', 'cashier_name')
            ->distinct()
            ->orderBy('cashier_name')
            ->get()
            ->map(fn (Sale $sale): array => ['id' => (int) $sale->cashier_id, 'name' => $sale->cashier_name])
            ->all();
    }

    /**
     * @param  HistoryFilters  $filters
     * @return Builder<Sale>
     */
    private function query(array $filters): Builder
    {
        return Sale::query()
            ->when($filters['cashier_id'], fn (Builder $query, int $cashierId) => $query->where('cashier_id', $cashierId))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('business_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('business_date', '<=', $to))
            ->when(SaleStatus::tryFrom((string) $filters['status']), fn (Builder $query, SaleStatus $status) => $query->where('status', $status))
            ->when(($filters['delivery'] ?? null) === self::PENDING_DELIVERY_FILTER, fn (Builder $query) => $query->pendingDelivery())
            ->when(filled($filters['search']), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$filters['search']}%")
                ->orWhere('customer_name', 'like', "%{$filters['search']}%")));
    }
}
