<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * When the store is busy: the receipts of a period by the hour they were rung up and by the weekday of their business day,
 * for the sales report's tab of busy hours and days. Only receipts that stand count (a voided one is no sale), at their total
 * before returns, since a return has no hour of its own. Must run while the store's tenancy is initialized.
 *
 * The weekday is that of the receipt's business day, the hour that of the time it was sold, so a receipt rung up after
 * midnight in a shift that opened the day before is of the earlier weekday and of the small hours. Weekdays number from
 * Monday (1) to Sunday (7).
 *
 * Every figure comes with its average per trading day, a day on which at least one receipt stands: the days of the period
 * are not all alike (five Mondays against four Tuesdays, a day the store was shut), so totals alone would crown the weekday
 * that happens to come most often. A weekday's average is over the days of that weekday the store traded, and an hour's
 * over all the days it traded. Money is returned as decimal strings.
 *
 * The days that had no receipt are told too (`days_without_receipts`): the days of the period, counting up to today, that are
 * not trading days. Whether the store was shut or simply sold nothing cannot be told from the receipts, so they stay out of the
 * averages. Today is left out while it has no receipt, since it is not over yet, and days after today are not counted.
 *
 * @phpstan-type BusyFigures array{receipts: int, sales: string, days: int, days_without_receipts: int, receipts_per_day: string, sales_per_day: string}
 * @phpstan-type BusyReport array{days: int, days_without_receipts: int, receipts: int, sales: string, hours: list<array{hour: int}&BusyFigures>, weekdays: list<array{weekday: int}&BusyFigures>, cells: list<array{weekday: int, hour: int}&BusyFigures>}
 */
class SalesBusyTimeReportService
{
    private const HOURS_IN_A_DAY = 24;

    private const DAYS_IN_A_WEEK = 7;

    /**
     * The hours (all 24, the quiet ones too), the weekdays (all 7) and the cells of weekday and hour that had a receipt.
     *
     * @return BusyReport
     */
    public function report(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tradingDates = $this->tradingDates($from, $to);
        $tradingDays = array_count_values(array_column($tradingDates, 'weekday'));
        $totalDays = count($tradingDates);
        $quietDays = $this->daysWithoutReceipts($from, $to, array_column($tradingDates, 'date'));
        $totalQuietDays = array_sum($quietDays);

        $cells = [];
        $hours = array_fill(0, self::HOURS_IN_A_DAY, ['receipts' => 0, 'sales' => BigDecimal::zero()]);
        $weekdays = array_fill(1, self::DAYS_IN_A_WEEK, ['receipts' => 0, 'sales' => BigDecimal::zero()]);

        foreach ($this->receiptsByWeekdayAndHour($from, $to) as $row) {
            $weekday = (int) $row->weekday;
            $hour = (int) $row->hour;
            $receipts = (int) $row->receipts;
            $sales = BigDecimal::of((string) $row->sales);

            $cells[] = ['weekday' => $weekday, 'hour' => $hour, ...$this->figures($receipts, $sales, $tradingDays[$weekday] ?? 0, $quietDays[$weekday])];
            $hours[$hour] = ['receipts' => $hours[$hour]['receipts'] + $receipts, 'sales' => $hours[$hour]['sales']->plus($sales)];
            $weekdays[$weekday] = ['receipts' => $weekdays[$weekday]['receipts'] + $receipts, 'sales' => $weekdays[$weekday]['sales']->plus($sales)];
        }

        return [
            'days' => $totalDays,
            'days_without_receipts' => $totalQuietDays,
            'receipts' => array_sum(array_column($hours, 'receipts')),
            'sales' => $this->money(array_reduce($hours, fn (BigDecimal $sum, array $hour): BigDecimal => $sum->plus($hour['sales']), BigDecimal::zero())),
            'hours' => array_map(fn (int $hour): array => ['hour' => $hour, ...$this->figures($hours[$hour]['receipts'], $hours[$hour]['sales'], $totalDays, $totalQuietDays)], array_keys($hours)),
            'weekdays' => array_map(fn (int $weekday): array => ['weekday' => $weekday, ...$this->figures($weekdays[$weekday]['receipts'], $weekdays[$weekday]['sales'], $tradingDays[$weekday] ?? 0, $quietDays[$weekday])], array_keys($weekdays)),
            'cells' => $cells,
        ];
    }

    /**
     * The days of the period on which at least one receipt stands, each with its weekday.
     *
     * @return list<array{date: string, weekday: int}>
     */
    private function tradingDates(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->standingReceipts($from, $to)
            ->selectRaw('business_date as date, weekday(business_date) + 1 as weekday')
            ->distinct()
            ->get()
            ->map(fn (object $day): array => ['date' => (string) $day->date, 'weekday' => (int) $day->weekday])
            ->all();
    }

    /**
     * How many days of each weekday the period had no receipt, keyed by weekday (1 to 7): the days from its start up to
     * today (or its end, if that comes first) that are not in `$tradingDates`, except today while it has none.
     *
     * @param  list<string>  $tradingDates  Days with a receipt, as `Y-m-d`.
     * @return array<int, int>
     */
    private function daysWithoutReceipts(CarbonImmutable $from, CarbonImmutable $to, array $tradingDates): array
    {
        $traded = array_flip($tradingDates);
        $today = CarbonImmutable::today();
        $lastDay = $to->startOfDay()->lessThan($today) ? $to->startOfDay() : $today;
        $quietDays = array_fill(1, self::DAYS_IN_A_WEEK, 0);

        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($lastDay); $day = $day->addDay()) {
            if (! isset($traded[$day->toDateString()]) && ! $day->isSameDay($today)) {
                $quietDays[$day->dayOfWeekIso]++;
            }
        }

        return $quietDays;
    }

    /**
     * @return Collection<int, object{weekday: int|string, hour: int|string, receipts: int|string, sales: string}>
     */
    private function receiptsByWeekdayAndHour(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->standingReceipts($from, $to)
            ->selectRaw('weekday(business_date) + 1 as weekday, hour(sold_at) as hour, count(*) as receipts, coalesce(sum(total), 0) as sales')
            ->groupBy('weekday', 'hour')
            ->get();
    }

    private function standingReceipts(CarbonImmutable $from, CarbonImmutable $to): QueryBuilder
    {
        return Sale::query()
            ->toBase()
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('business_date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * @return BusyFigures
     */
    private function figures(int $receipts, BigDecimal $sales, int $days, int $daysWithoutReceipts): array
    {
        return [
            'receipts' => $receipts,
            'sales' => $this->money($sales),
            'days' => $days,
            'days_without_receipts' => $daysWithoutReceipts,
            'receipts_per_day' => $days > 0 ? (string) BigDecimal::of($receipts)->dividedBy($days, 1, RoundingMode::HalfUp) : '0.0',
            'sales_per_day' => $days > 0 ? $this->money($sales->dividedBy($days, 2, RoundingMode::HalfUp)) : '0.00',
        ];
    }

    private function money(BigDecimal $amount): string
    {
        return (string) $amount->toScale(2, RoundingMode::HalfUp);
    }
}
