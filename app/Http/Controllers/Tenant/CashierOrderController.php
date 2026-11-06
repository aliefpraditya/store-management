<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
use App\Enums\OrderStatus;
use App\Exceptions\CustomerOrderException;
use App\Http\Controllers\Concerns\AnswersJsonErrors;
use App\Http\Controllers\Concerns\AnswersPrints;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CancelCustomerOrderRequest;
use App\Http\Resources\CustomerOrderResource;
use App\Models\CustomerOrder;
use App\Services\CustomerOrderService;
use App\Services\PrintCounter;
use App\Services\StoreSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer orders (PO) from the till: the orders still open, from any day, are listed so the cashier can mark that the
 * goods came in, cancel one (the deposit goes back), or print it again. Collecting an order is a sale, made through the
 * sales endpoint. Any cashier of the store may do these, like voiding a COD sale.
 */
class CashierOrderController extends Controller
{
    use AnswersJsonErrors, AnswersPrints;

    private const LIST_LIMIT = 50;

    public function __construct(
        private readonly CustomerOrderService $customerOrders,
        private readonly PrintCounter $printCounter,
        private readonly StoreSettingService $settings,
    ) {}

    /**
     * The open orders, ready ones first (they are waiting for the customer), and how many are open in all.
     */
    public function index(): JsonResponse
    {
        $openOrdersQuery = CustomerOrder::query()->whereIn('status', OrderStatus::unfinished());

        $orders = (clone $openOrdersQuery)
            ->with(['items', 'payments', 'originSale'])
            ->orderByRaw("status = 'ready' desc")
            ->orderBy('expected_date')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();

        return response()->json([
            'data' => CustomerOrderResource::collection($orders)->resolve(),
            'meta' => ['total' => $openOrdersQuery->count()],
        ]);
    }

    public function ready(CustomerOrder $order): JsonResponse
    {
        try {
            $this->customerOrders->markReady($order);
        } catch (CustomerOrderException $exception) {
            return $this->refused($exception);
        }

        return response()->json(['data' => ['number' => $order->number]]);
    }

    public function cancel(CancelCustomerOrderRequest $request, CustomerOrder $order): JsonResponse
    {
        try {
            $this->customerOrders->cancel($order, $request->user(), $request->validated('reason'), $request->validated('refund_account'));
        } catch (CustomerOrderException $exception) {
            return $this->refused($exception);
        }

        return response()->json(['data' => ['number' => $order->number]]);
    }

    /**
     * Notes one more print and answers the sheet to open in a new window.
     */
    public function print(CustomerOrder $order): JsonResponse
    {
        return $this->recordPrintAnswer($this->printCounter, $order, 'tenant.cashier.orders.sheet', 'order');
    }

    /**
     * The printable order slip: what was ordered at what price, the deposit, and what is left to pay on collection.
     */
    public function sheet(Request $request, CustomerOrder $order): InertiaResponse
    {
        return Inertia::render('Tenant/Print/Order', [
            'order' => [
                ...CustomerOrderResource::make($order->load(['items', 'payments', 'originSale']))->resolve(),
                'is_copy' => $this->printNumberOf($request) > 1,
                'store' => $this->settings->letterhead(),
                'footer' => $this->settings->receiptFooter(),
                'paper_width' => $this->settings->paperWidth(),
            ],
        ]);
    }

    private function refused(CustomerOrderException $exception): JsonResponse
    {
        return $this->errorResponse(ApiErrorCode::OrderRefused, $exception->getMessage(), 'Muat ulang daftar pesanan untuk melihat keadaan yang terbaru, lalu coba lagi.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
