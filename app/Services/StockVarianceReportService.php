<?php

namespace App\Services;

use App\Enums\StockOpnameReason;
use App\Enums\StockOpnameStatus;
use App\Models\OpnameReason;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\Concerns\PaginatesReportRows;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The shortages of the stock counts posted in a period: what went missing, in what it was worth when the count was posted (the
 * variance times the purchase price at posting; an item without a purchase price is worth nothing, which is told). Only the
 * shortages are here: a count that finds more than the system holds is, until purchases are recorded, mostly stock that was
 * bought, which is no variance to report. Only counts of what is on the shelf are here ({@see StockOpnameReason::isStockCheck()}):
 * opening stock, arrivals and the inspection of returned goods are not variances. A count belongs to the day it was posted, as
 * in the sales report's dead stock loss, and the units of different products are never added, only their values. Money is
 * returned as decimal strings. Must run while the store's tenancy is initialized.
 *
 * A shortage given the reason "Rusak" is split by the admin into units sent to dead stock (already a loss in the profit of the
 * sales report, from the day the count is posted), units sold on as damaged goods (a loss only when sold, for less than they
 * cost) and units that were fine after all and stay on the shelf. So `unbooked_value` is the shortage that is a loss nowhere
 * else: what the shortage is worth, less those three parts.
 *
 * @phpstan-import-type ReportPage from PaginatesReportRows
 *
 * @phpstan-type VarianceSummary array{opnames_count: int, shortage_items: int, shortage_value: string, dead_stock_value: string, damaged_goods_value: string, full_price_value: string, unbooked_value: string, items_without_cost: int}
 * @phpstan-type VarianceReason array{label: string, items_count: int, shortage_value: string, dead_stock_value: string, damaged_goods_value: string, full_price_value: string}
 */
class StockVarianceReportService
{
    use PaginatesReportRows;

    /** How many counts a page of the list may hold, the first being the one it starts with. */
    public const OPNAME_PER_PAGE_OPTIONS = [10, 25, 50];

    /** How many products the report ranks by what went missing. */
    public const TOP_PRODUCTS = 7;

    /** What an item is worth per unit when it was posted; counts posted before the price was kept have none. */
    private const COST_SQL = 'coalesce(stock_opname_items.cost_price_at_posting, 0)';

    /**
     * The headline figures of the counts posted in the period.
     *
     * @return VarianceSummary
     */
    public function summary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $figures = $this->varianceItems($from, $to)->toBase()->selectRaw(<<<'SQL'
            count(*) as shortage_items,
            count(case when coalesce(stock_opname_items.cost_price_at_posting, 0) = 0 then 1 end) as items_without_cost,
        SQL.$this->valueSums())->first();

        $shortage = BigDecimal::of((string) $figures->shortage_value);
        $deadStock = BigDecimal::of((string) $figures->dead_stock_value);
        $damagedGoods = BigDecimal::of((string) $figures->damaged_goods_value);
        $fullPrice = BigDecimal::of((string) $figures->full_price_value);

        return [
            'opnames_count' => $this->postedStockChecks($from, $to)->count(),
            'shortage_items' => (int) $figures->shortage_items,
            'shortage_value' => $this->money($shortage),
            'dead_stock_value' => $this->money($deadStock),
            'damaged_goods_value' => $this->money($damagedGoods),
            'full_price_value' => $this->money($fullPrice),
            'unbooked_value' => $this->money($shortage->minus($deadStock)->minus($damagedGoods)->minus($fullPrice)),
            'items_without_cost' => (int) $figures->items_without_cost,
        ];
    }

    /**
     * The shortages split by the reason given for them, the biggest first. An item's own reason is used, and the
     * reason of its count for the older counts that were made before items had one; the same label is one row.
     *
     * @return list<VarianceReason>
     */
    public function reasons(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = $this->varianceItems($from, $to)->toBase()
            ->selectRaw('stock_opname_items.reason_id, case when stock_opname_items.reason_id is null then stock_opnames.reason_code end as count_reason, count(*) as items_count, '.$this->valueSums())
            ->groupBy('stock_opname_items.reason_id', 'count_reason')
            ->get();
        $reasonNames = OpnameReason::query()->pluck('name', 'id');

        return $rows
            ->groupBy(fn (object $row): string => $this->reasonLabel($row, $reasonNames->all()))
            ->map(function ($sameLabel, string $label): array {
                $sum = fn (string $column): BigDecimal => $sameLabel->reduce(fn (BigDecimal $total, object $row): BigDecimal => $total->plus((string) $row->{$column}), BigDecimal::zero());

                return [
                    'label' => $label,
                    'items_count' => (int) $sameLabel->sum('items_count'),
                    'shortage_value' => $this->money($sum('shortage_value')),
                    'dead_stock_value' => $this->money($sum('dead_stock_value')),
                    'damaged_goods_value' => $this->money($sum('damaged_goods_value')),
                    'full_price_value' => $this->money($sum('full_price_value')),
                ];
            })
            ->sort(fn (array $first, array $second): int => [(float) $second['shortage_value'], $first['label']] <=> [(float) $first['shortage_value'], $second['label']])
            ->values()
            ->all();
    }

    /**
     * The products that lost the most, by what their shortages were worth, the biggest first.
     *
     * @return list<array{product_id: int, name: string, shortage_value: string}>
     */
    public function topProducts(CarbonImmutable $from, CarbonImmutable $to, int $limit): array
    {
        $rows = $this->varianceItems($from, $to)->toBase()
            ->selectRaw('stock_opname_items.product_id, sum(-stock_opname_items.variance * '.self::COST_SQL.') as shortage_value')
            ->groupBy('stock_opname_items.product_id')
            ->having('shortage_value', '>', 0)
            ->orderByDesc('shortage_value')
            ->orderBy('stock_opname_items.product_id')
            ->limit($limit)
            ->get();
        $names = Product::query()->withTrashed()->whereIn('id', $rows->pluck('product_id'))->pluck('name', 'id');

        return $rows->map(fn (object $row): array => [
            'product_id' => (int) $row->product_id,
            'name' => $names[$row->product_id] ?? '-',
            'shortage_value' => $this->money($row->shortage_value),
        ])->all();
    }

    /**
     * One page of the counts posted in the period, the latest first, each with what its shortages were worth; a count with no
     * shortage is listed too, since a count that came out even is worth knowing. A page past the last gives the last.
     *
     * @return array{data: list<array{id: int, number: string, posted_on: string, warehouse: string, reason: string, items_count: int, shortage_value: string}>}&ReportPage
     */
    public function opnamesPage(CarbonImmutable $from, CarbonImmutable $to, int $page, int $perPage): array
    {
        $paginator = $this->clampedPage(
            $this->postedStockChecks($from, $to)->with('warehouse:id,name')->orderByDesc('posted_at')->orderByDesc('id'),
            $page,
            $perPage,
        );

        $figures = $this->varianceItems($from, $to)->toBase()
            ->whereIn('stock_opname_items.stock_opname_id', $paginator->getCollection()->pluck('id'))
            ->selectRaw('stock_opname_items.stock_opname_id, count(*) as items_count, '.$this->valueSums())
            ->groupBy('stock_opname_items.stock_opname_id')
            ->get()
            ->keyBy('stock_opname_id');

        return [
            'data' => $paginator->getCollection()->map(function (StockOpname $opname) use ($figures): array {
                $row = $figures[$opname->id] ?? null;

                return [
                    'id' => $opname->id,
                    'number' => $opname->number,
                    'posted_on' => $opname->posted_at->toDateString(),
                    'warehouse' => $opname->warehouse->name,
                    'reason' => $opname->reason_code?->label() ?? '-',
                    'items_count' => (int) ($row->items_count ?? 0),
                    'shortage_value' => $this->money($row->shortage_value ?? 0),
                ];
            })->values()->all(),
            ...$this->pageOf($paginator),
        ];
    }

    /**
     * The counts of the shelf that were posted in the period.
     *
     * @return Builder<StockOpname>
     */
    private function postedStockChecks(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return StockOpname::query()
            ->where('status', StockOpnameStatus::Posted)
            ->whereIn('reason_code', $this->stockCheckReasons())
            ->whereBetween('posted_at', [$from->startOfDay(), $to->endOfDay()]);
    }

    /**
     * The items of those counts that came out short, with `stock_opnames` joined.
     *
     * @return Builder<StockOpnameItem>
     */
    private function varianceItems(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return StockOpnameItem::query()
            ->join('stock_opnames', 'stock_opnames.id', '=', 'stock_opname_items.stock_opname_id')
            ->where('stock_opnames.status', StockOpnameStatus::Posted)
            ->whereIn('stock_opnames.reason_code', $this->stockCheckReasons())
            ->whereBetween('stock_opnames.posted_at', [$from->startOfDay(), $to->endOfDay()])
            ->where('stock_opname_items.variance', '<', 0);
    }

    /**
     * What the shortages are worth, summed, and of them the units sent to dead stock, sold on as damaged goods and found fine
     * after all, each at the price when the count was posted.
     */
    private function valueSums(): string
    {
        $cost = self::COST_SQL;

        return <<<SQL
            coalesce(sum(-stock_opname_items.variance * {$cost}), 0) as shortage_value,
            coalesce(sum(coalesce(stock_opname_items.dead_stock_quantity, 0) * {$cost}), 0) as dead_stock_value,
            coalesce(sum(coalesce(stock_opname_items.damaged_goods_quantity, 0) * {$cost}), 0) as damaged_goods_value,
            coalesce(sum(coalesce(stock_opname_items.full_price_quantity, 0) * {$cost}), 0) as full_price_value
        SQL;
    }

    /**
     * @return list<string>
     */
    private function stockCheckReasons(): array
    {
        return array_map(fn (StockOpnameReason $reason): string => $reason->value, StockOpnameReason::stockChecks());
    }

    /**
     * @param  array<int, string>  $reasonNames  The names of the store's reasons, by id.
     */
    private function reasonLabel(object $row, array $reasonNames): string
    {
        if ($row->reason_id !== null) {
            return $reasonNames[$row->reason_id] ?? '-';
        }

        return StockOpnameReason::tryFrom((string) $row->count_reason)?->label() ?? '-';
    }

    private function money(BigDecimal|string|int|float|null $amount): string
    {
        return (string) BigDecimal::of((string) ($amount ?? 0))->toScale(2, RoundingMode::HalfUp);
    }
}
