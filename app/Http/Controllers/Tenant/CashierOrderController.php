<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\ApiErrorCode;
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
     * The open orders (ready ones first, see {@see CustomerOrderService::openOrdersFor()}), found by the `q` of the
     * search box on the server, so an order that scanned in is found even when many orders are open. `meta.total` is how
     * many orders match and `meta.counts` the numbers of the till's badge.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('q')->trim()->limit(100, '')->toString();
        $openOrders = $this->customerOrders->openOrdersFor($search, self::LIST_LIMIT);

        $undeliveredBySaleId = $this->deliveryNotes->undeliveredLinesOf($openOrders['orders']->pluck('originSale')->filter()->unique('id'));

        $rows = $openOrders['orders']
            ->map(fn (CustomerOrder $order): array => [
                ...CustomerOrderResource::make($order)->resolve(),
                'stock_check' => $openOrders['coverage'][$order->id] ?? null,
                'origin_sale_undelivered' => $undeliveredBySaleId[$order->origin_sale_id] ?? [],
                'origin_sale_cod_amount' => $order->originSale?->codAmount() ?? '0.00',
            ])
            ->all();

        return response()->json([
            'data' => $rows,
            'meta' => ['total' => $openOrders['total'], 'counts' => $openOrders['counts']],
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
