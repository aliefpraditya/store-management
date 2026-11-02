<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
use App\Exceptions\ExpenseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\SaveCashierExpenseRequest;
use App\Http\Resources\CashierExpenseResource;
use App\Http\Resources\ExpenseCategoryResource;
use App\Services\ExpenseCategoryService;
use App\Services\ExpenseService;
use App\Services\ShiftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Expenses from the till: a cashier takes money out of their own running drawer without leaving the cashier screen.
 * Correcting a wrong one is for an admin.
 */
class CashierExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService,
        private readonly ExpenseCategoryService $categoryService,
        private readonly ShiftService $shiftService,
    ) {}

    /**
     * What the dialog needs: the categories to choose from and what has been taken from this shift's drawer so far.
     */
    public function index(Request $request): JsonResponse
    {
        $shift = $this->shiftService->openShiftOf($request->user());

        if ($shift === null) {
            return $this->shiftNotOpen();
        }

        return response()->json(['data' => [
            'categories' => ExpenseCategoryResource::collection($this->categoryService->active())->resolve(),
            'expenses' => CashierExpenseResource::collection($this->expenseService->drawerExpensesOf($shift))->resolve(),
        ]]);
    }

    public function store(SaveCashierExpenseRequest $request): JsonResponse
    {
        $shift = $this->shiftService->openShiftOf($request->user());

        if ($shift === null) {
            return $this->shiftNotOpen();
        }

        try {
            $expense = $this->expenseService->recordFromDrawer($request->user(), $shift, $request->validated());
        } catch (ExpenseException $exception) {
            return $this->errorResponse(ApiErrorCode::ExpenseRefused, $exception->getMessage(), 'Perbaiki isian pengeluaran sesuai pesan ini, lalu coba lagi.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['data' => CashierExpenseResource::make($expense->load('category'))->resolve()], Response::HTTP_CREATED);
    }

    private function shiftNotOpen(): JsonResponse
    {
        return $this->errorResponse(ApiErrorCode::ShiftNotOpen, ExpenseException::shiftNotOpen()->getMessage(), 'Pengeluaran dari laci dicatat di shift yang sedang terbuka. Buka shift di kasir.', Response::HTTP_CONFLICT);
    }

    private function errorResponse(ApiErrorCode $code, string $message, string $moreInfo, int $status): JsonResponse
    {
        return response()->json(['code' => $code->value, 'message' => $message, 'more_info' => $moreInfo], $status);
    }
}
