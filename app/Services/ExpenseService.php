<?php

namespace App\Services;

use App\Enums\CashShiftStatus;
use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Exceptions\ExpenseException;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Operating expenses (pengeluaran). An expense paid from the drawer belongs to a running shift and lowers the cash
 * that should be in that drawer; one paid from another source stands on its own date and does not touch any shift.
 * Nothing is deleted: a wrong expense is voided with a reason, which an admin does.
 *
 * `client_uuid` makes a double submit return the first expense instead of making a second.
 *
 * @phpstan-type ExpenseInput array{client_uuid: string, expense_category_id: int, description: string, paid_to?: string|null, amount: numeric-string|float|int}
 * @phpstan-type ExpenseFilters array{from: string|null, to: string|null, category: int|null, source: string|null, status: string|null, search: string|null}
 * @phpstan-type ExpenseTotals array{drawer: string, other: string, total: string}
 */
class ExpenseService
{
    private const NUMBER_TYPE = 'PGL';

    public function __construct(private readonly DocumentNumberGenerator $documentNumbers) {}

    /**
     * Newest first, narrowed by day, category, source, status and a search on the number, description or payee.
     *
     * @param  ExpenseFilters  $filters
     * @return LengthAwarePaginator<int, Expense>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(['category', 'cashShift.register'])
            ->orderByDesc('business_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * What the filtered list adds up to, voided expenses left out.
     *
     * @param  ExpenseFilters  $filters
     * @return ExpenseTotals
     */
    public function totals(array $filters): array
    {
        $recordedAmounts = $this->filtered($filters)
            ->where('status', ExpenseStatus::Recorded)
            ->groupBy('source')
            ->selectRaw('source, sum(amount) as amount')
            ->pluck('amount', 'source');

        $drawerTotal = BigDecimal::of((string) ($recordedAmounts[ExpenseSource::Drawer->value] ?? 0));
        $otherTotal = BigDecimal::of((string) ($recordedAmounts[ExpenseSource::Other->value] ?? 0));

        return [
            'drawer' => (string) $drawerTotal->toScale(2),
            'other' => (string) $otherTotal->toScale(2),
            'total' => (string) $drawerTotal->plus($otherTotal)->toScale(2),
        ];
    }

    /**
     * What came out of the drawer of a shift, voided expenses left out.
     */
    public function drawerTotalOf(CashShift $shift): string
    {
        return (string) BigDecimal::of((string) Expense::query()
            ->where('cash_shift_id', $shift->id)
            ->where('source', ExpenseSource::Drawer)
            ->where('status', ExpenseStatus::Recorded)
            ->sum('amount'))->toScale(2);
    }

    /**
     * The expenses taken out of one shift's drawer, oldest first.
     *
     * @return Collection<int, Expense>
     */
    public function drawerExpensesOf(CashShift $shift): Collection
    {
        return Expense::query()
            ->with('category')
            ->where('cash_shift_id', $shift->id)
            ->where('source', ExpenseSource::Drawer)
            ->orderBy('id')
            ->get();
    }

    /**
     * The drawers an admin can take an expense from: every shift still running.
     *
     * @return list<array{id: int, label: string}>
     */
    public function openDrawerOptions(): array
    {
        return CashShift::query()
            ->with('register')
            ->where('status', CashShiftStatus::Open)
            ->orderBy('id')
            ->get()
            ->map(fn (CashShift $shift): array => ['id' => $shift->id, 'label' => "{$shift->register->code} · {$shift->cashier_name}"])
            ->all();
    }

    /**
     * Records an expense from the source the admin chose: the given running shift's drawer, or somewhere else on the
     * given day.
     *
     * @param  ExpenseInput  $expenseInput
     *
     * @throws ExpenseException
     */
    public function record(User $creator, ExpenseSource $source, ?CashShift $shift, ?CarbonInterface $businessDate, array $expenseInput): Expense
    {
        return match ($source) {
            ExpenseSource::Drawer => $this->recordFromDrawer($creator, $shift ?? throw ExpenseException::shiftNotOpen(), $expenseInput),
            ExpenseSource::Other => $this->recordFromOtherSource($creator, $businessDate ?? today(), $expenseInput),
        };
    }

    /**
     * Takes money out of a shift's drawer. The shift must still be open; it is locked, so an expense cannot slip in
     * while the shift is being closed.
     *
     * @param  ExpenseInput  $expenseInput
     *
     * @throws ExpenseException
     */
    public function recordFromDrawer(User $creator, CashShift $shift, array $expenseInput): Expense
    {
        return DB::transaction(function () use ($creator, $shift, $expenseInput): Expense {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);

            $alreadyRecordedExpense = $this->alreadyRecorded($expenseInput['client_uuid'], $creator, ExpenseSource::Drawer, $shift);

            if ($alreadyRecordedExpense !== null) {
                return $alreadyRecordedExpense;
            }

            if ($shift->status !== CashShiftStatus::Open) {
                throw ExpenseException::shiftNotOpen();
            }

            return $this->create($creator, ExpenseSource::Drawer, $shift->business_date, $shift, $expenseInput);
        });
    }

    /**
     * Records money paid from somewhere other than a drawer, on the day it was paid. Nothing is locked here, so two
     * sends of the same expense at the same moment can both pass the check; the second one is stopped by the unique
     * marker, its transaction is rolled back (the number is not used up), and it gets the first expense back.
     *
     * @param  ExpenseInput  $expenseInput
     *
     * @throws ExpenseException
     */
    public function recordFromOtherSource(User $creator, CarbonInterface $businessDate, array $expenseInput): Expense
    {
        try {
            return DB::transaction(function () use ($creator, $businessDate, $expenseInput): Expense {
                $alreadyRecordedExpense = $this->alreadyRecorded($expenseInput['client_uuid'], $creator, ExpenseSource::Other, null);

                return $alreadyRecordedExpense ?? $this->create($creator, ExpenseSource::Other, $businessDate, null, $expenseInput);
            });
        } catch (UniqueConstraintViolationException $exception) {
            return $this->alreadyRecorded($expenseInput['client_uuid'], $creator, ExpenseSource::Other, null) ?? throw $exception;
        }
    }

    /**
     * Cancels an expense; it stays on the list as voided. A drawer expense can only be cancelled while its shift is
     * open, since the cash of a closed shift has already been counted against it ({@see Expense::voidBlocker()}).
     *
     * @throws ExpenseException
     */
    public function void(Expense $expense, User $admin, string $reason): Expense
    {
        return DB::transaction(function () use ($expense, $admin, $reason): Expense {
            $lockedExpense = Expense::query()->lockForUpdate()->findOrFail($expense->id);

            if ($lockedExpense->source === ExpenseSource::Drawer) {
                $lockedExpense->setRelation('cashShift', CashShift::query()->lockForUpdate()->findOrFail($lockedExpense->cash_shift_id));
            }

            $voidBlocker = $lockedExpense->voidBlocker();

            if ($voidBlocker !== null) {
                throw $voidBlocker;
            }

            $lockedExpense->forceFill([
                'status' => ExpenseStatus::Voided,
                'void_reason' => trim($reason),
                'voided_at' => now(),
                'voided_by_name' => $admin->name,
            ])->save();

            return $lockedExpense;
        });
    }

    /**
     * @param  ExpenseInput  $expenseInput
     *
     * @throws ExpenseException
     */
    private function create(User $creator, ExpenseSource $source, CarbonInterface $businessDate, ?CashShift $shift, array $expenseInput): Expense
    {
        $category = ExpenseCategory::query()->findOrFail($expenseInput['expense_category_id']);

        if (! $category->is_active) {
            throw ExpenseException::categoryInactive();
        }

        return Expense::query()->create([
            'client_uuid' => $expenseInput['client_uuid'],
            'number' => $this->documentNumbers->next(self::NUMBER_TYPE, $businessDate),
            'business_date' => $businessDate->toDateString(),
            'cash_shift_id' => $shift?->id,
            'expense_category_id' => $category->id,
            'source' => $source,
            'description' => trim($expenseInput['description']),
            'paid_to' => filled($expenseInput['paid_to'] ?? null) ? trim($expenseInput['paid_to']) : null,
            'amount' => (string) BigDecimal::of((string) $expenseInput['amount'])->toScale(2),
            'status' => ExpenseStatus::Recorded,
            'created_by' => $creator->id,
            'created_by_name' => $creator->name,
        ]);
    }

    /**
     * The expense a retried request made the first time, so a double tap cannot record it twice. A marker that belongs
     * to someone else's expense, or to an expense from another source or drawer, is refused: it is not a retry, and
     * handing back that other expense would hide that nothing new was recorded.
     *
     * @throws ExpenseException
     */
    private function alreadyRecorded(string $clientUuid, User $creator, ExpenseSource $source, ?CashShift $shift): ?Expense
    {
        $recordedExpense = Expense::query()->where('client_uuid', $clientUuid)->first();

        if ($recordedExpense === null) {
            return null;
        }

        $isSameRequest = $recordedExpense->created_by === $creator->id
            && $recordedExpense->source === $source
            && $recordedExpense->cash_shift_id === $shift?->id;

        return $isSameRequest ? $recordedExpense : throw ExpenseException::expenseIdInUse();
    }

    /**
     * @param  ExpenseFilters  $filters
     * @return Builder<Expense>
     */
    private function filtered(array $filters): Builder
    {
        return Expense::query()
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('business_date', '>=', $from))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('business_date', '<=', $to))
            ->when($filters['category'], fn (Builder $query, int $categoryId) => $query->where('expense_category_id', $categoryId))
            ->when(ExpenseSource::tryFrom((string) $filters['source']), fn (Builder $query, ExpenseSource $source) => $query->where('source', $source))
            ->when(ExpenseStatus::tryFrom((string) $filters['status']), fn (Builder $query, ExpenseStatus $status) => $query->where('status', $status))
            ->when(filled($filters['search']), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$filters['search']}%")
                ->orWhere('description', 'like', "%{$filters['search']}%")
                ->orWhere('paid_to', 'like', "%{$filters['search']}%")));
    }
}
