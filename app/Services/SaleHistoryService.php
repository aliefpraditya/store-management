<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Models\CashShift;
use App\Models\Sale;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Looks receipts up: the store-wide history for admins, and the receipts of one shift for the till's dialog.
 *
 * @phpstan-type HistoryFilters array{from: string|null, to: string|null, search: string|null, cashier_id: int|null, status: string|null, delivery?: string|null, method?: string|null}
 * @phpstan-type HistoryTotals array{receipt_count: int, total: string}
 */
class SaleHistoryService
{
    /** The `delivery` filter value that narrows the history to receipts not delivered yet. */
    public const PENDING_DELIVERY_FILTER = 'pending';

    /** How many receipts a page of the receipt list of the sales report may hold, the first being the one it starts with. */
    public const RECEIPT_LIST_PER_PAGE_OPTIONS = [15, 30, 50];

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
     * A page of receipts with their lines and payments, for the receipt list of the sales report, newest business day
     * first and, within a day, newest first (see {@see self::inReportOrder()}). A page past the last gives the last, so an
     * address typed by hand never lands on an empty page.
     *
     * @param  HistoryFilters  $filters
     * @return LengthAwarePaginator<int, Sale>
     */
    public function paginateWithLines(array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $listing = fn (int $pageNumber): LengthAwarePaginator => $this->inReportOrder($this->query($filters))
            ->with(['items', 'payments'])
            ->paginate($perPage, ['*'], 'page', $pageNumber);

        $paginator = $listing($page);

        return $page > $paginator->lastPage() ? $listing($paginator->lastPage()) : $paginator;
    }

    /**
     * Whether the first receipt of a page is of the same business day as the last one of the page before it, so the page
     * opens in the middle of a day the list started on the page before. Always false for the first page.
     *
     * @param  HistoryFilters  $filters
     * @param  LengthAwarePaginator<int, Sale>  $paginator  A page made by {@see self::paginateWithLines()}.
     */
    public function continuesPreviousDay(array $filters, LengthAwarePaginator $paginator): bool
    {
        $firstOnPage = $paginator->getCollection()->first();
        $offsetOfFirst = ($paginator->currentPage() - 1) * $paginator->perPage();

        if ($firstOnPage === null || $offsetOfFirst === 0) {
            return false;
        }

        $previousDay = $this->inReportOrder($this->query($filters))->skip($offsetOfFirst - 1)->take(1)->value('business_date');

        return $previousDay !== null && CarbonImmutable::parse($previousDay)->toDateString() === $firstOnPage->business_date->toDateString();
    }

    /**
     * What the completed receipts of the filter come to on each business day that has any, for the day headings of the
     * receipt list. A day has only its count and total here: the returns and voids of a day are not tied to a payment
     * method or to a search, so they are left `null` rather than made up (see {@see SalesReportService::dayFigures()} for
     * a whole day).
     *
     * @param  HistoryFilters  $filters
     * @return array<string, array{receipt_count: int, gross_total: string, returns_total: null, net_total: null, voids_count: null, voids_total: null}>
     */
    public function totalsByDay(array $filters): array
    {
        return $this->query($filters)
            ->where('status', SaleStatus::Completed)
            ->groupBy('business_date')
            ->selectRaw('business_date as day, count(*) as receipts, sum(total) as total')
            ->get()
            ->mapWithKeys(fn (Sale $row): array => [(string) $row->getAttribute('day') => [
                'receipt_count' => (int) $row->getAttribute('receipts'),
                'gross_total' => (string) BigDecimal::of((string) $row->getAttribute('total'))->toScale(2),
                'returns_total' => null,
                'net_total' => null,
                'voids_count' => null,
                'voids_total' => null,
            ]])
            ->all();
    }

    /**
     * The order of the receipt list: by business day, since a day is what it is grouped by (a shift that runs past midnight
     * keeps its opening day, so the time alone would mix the days), then newest first.
     *
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    private function inReportOrder(Builder $query): Builder
    {
        return $query->orderByDesc('business_date')->orderByDesc('sold_at')->orderByDesc('id');
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
            ->when(PaymentMethod::tryFrom((string) ($filters['method'] ?? '')), fn (Builder $query, PaymentMethod $method) => $query->whereHas('payments', fn (Builder $payments) => $payments->where('method', $method)))
            ->when(filled($filters['search']), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$filters['search']}%")
                ->orWhere('customer_name', 'like', "%{$filters['search']}%")));
    }
}
