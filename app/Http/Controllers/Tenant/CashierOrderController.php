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
use App\Services\DeliveryNoteService;
use App\Services\PrintCounter;
use App\Services\StoreSettingService;
use Brick\Math\BigDecimal;
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
        private readonly DeliveryNoteService $deliveryNotes,
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
            ->with(['items.product', 'payments', 'originSale.items', 'originSale.payments'])
            ->orderByRaw("status = 'ready' desc")
            ->orderBy('expected_date')
            ->orderByDesc('id')
            ->limit(self::LIST_LIMIT)
            ->get();

        $coverage = $this->customerOrders->stockCoverageOf($orders);

        $rows = $orders
            ->sortBy(fn (CustomerOrder $order): int => match (true) {
                $order->status === OrderStatus::Ready => 0,
                $coverage[$order->id]['is_covered'] ?? false => 1,
                default => 2,
            })
            ->map(fn (CustomerOrder $order): array => [
                ...CustomerOrderResource::make($order)->resolve(),
                'stock_check' => $coverage[$order->id] ?? null,
                'origin_sale_undelivered' => $this->undeliveredOfOriginSale($order),
                'origin_sale_cod_amount' => $order->originSale?->codAmount() ?? '0.00',
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $rows,
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

    /**
     * What of the receipt made together with the order has still not gone out on a delivery note, so the cashier can
     * deliver it together with the order instead of in a second trip.
     *
     * @return list<array{name: string, unit: string, remaining: string}>
     */
    private function undeliveredOfOriginSale(CustomerOrder $order): array
    {
        if ($order->originSale === null) {
            return [];
        }

        return collect($this->deliveryNotes->deliverableLines($order->originSale))
            ->filter(fn (array $line): bool => BigDecimal::of($line['remaining'])->isPositive())
            ->map(fn (array $line): array => ['name' => $line['name'], 'unit' => $line['unit'], 'remaining' => $line['remaining']])
            ->values()
            ->all();
    }

    private function refused(CustomerOrderException $exception): JsonResponse
    {
        return $this->errorResponse(ApiErrorCode::OrderRefused, $exception->getMessage(), 'Muat ulang daftar pesanan untuk melihat keadaan yang terbaru, lalu coba lagi.', Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
