<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ExpenseSource;
use App\Enums\ExpenseStatus;
use App\Exceptions\ExpenseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\DateRangeRequest;
use App\Http\Requests\Tenant\SaveExpenseRequest;
use App\Http\Requests\Tenant\VoidExpenseRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Http\Resources\ExpenseResource;
use App\Models\CashShift;
use App\Models\Expense;
use App\Services\ExpenseCategoryService;
use App\Services\ExpenseService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Expenses (pengeluaran) for admins: the list with its totals, recording one from a running drawer or another
 * source, and voiding a wrong one. Cashiers take money from their own drawer at the till.
 */
class ExpenseController extends Controller
{
    private const LIST_PER_PAGE = 25;

    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly ExpenseCategoryService $categoryService,
    ) {}

    public function index(DateRangeRequest $request): Response
    {
        $filters = [
            'from' => $request->fromDate()?->toDateString(),
            'to' => $request->toDate()?->toDateString(),
            'category' => $request->integer('category') ?: null,
            'source' => $request->string('source')->toString() ?: null,
            'status' => $request->string('status')->toString() ?: null,
            'search' => $request->string('search')->trim()->toString() ?: null,
        ];

        return Inertia::render('Tenant/Expenses/Index', [
            'expenses' => $this->expenseService->paginate($filters, self::LIST_PER_PAGE)
                ->through(fn (Expense $expense): array => ExpenseResource::make($expense)->resolve()),
            'filters' => $filters,
            'totals' => $this->expenseService->totals($filters),
            'categories' => ExpenseCategoryResource::collection($this->categoryService->all())->resolve(),
            'sources' => ExpenseSource::options(),
            'statuses' => ExpenseStatus::options(),
            'open_shifts' => $this->expenseService->openDrawerOptions(),
            'urls' => [
                'store' => route('tenant.expenses.store', ['tenant' => tenant('code')]),
                'category_store' => route('tenant.expenses.categories.store', ['tenant' => tenant('code')]),
            ],
        ]);
    }

    public function store(SaveExpenseRequest $request): RedirectResponse
    {
        $expenseInput = $request->safe()->only(['client_uuid', 'expense_category_id', 'description', 'paid_to', 'amount']);

        $source = ExpenseSource::from($request->validated('source'));
        $shift = $source === ExpenseSource::Drawer ? CashShift::query()->findOrFail($request->validated('cash_shift_id')) : null;

        try {
            $this->expenseService->record($request->user(), $source, $shift, $request->businessDate(), $expenseInput);
        } catch (ExpenseException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pengeluaran dicatat.');
    }

    public function void(VoidExpenseRequest $request, Expense $expense): RedirectResponse
    {
        try {
            $this->expenseService->void($expense, $request->user(), $request->validated('reason'));
        } catch (ExpenseException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pengeluaran dibatalkan.');
    }
}
