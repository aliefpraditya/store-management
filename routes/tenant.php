<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\BarcodeLabelController;
use App\Http\Controllers\Tenant\BulkPriceController;
use App\Http\Controllers\Tenant\CashierCodController;
use App\Http\Controllers\Tenant\CashierController;
use App\Http\Controllers\Tenant\CashierDeliveryNoteController;
use App\Http\Controllers\Tenant\CashierExpenseController;
use App\Http\Controllers\Tenant\CashierOrderController;
use App\Http\Controllers\Tenant\CashierProductController;
use App\Http\Controllers\Tenant\CashierSaleReturnController;
use App\Http\Controllers\Tenant\DailyClosingController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\DeliveryNoteController;
use App\Http\Controllers\Tenant\ExpenseCategoryController;
use App\Http\Controllers\Tenant\ExpenseController;
use App\Http\Controllers\Tenant\PriceLabelController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductSearchController;
use App\Http\Controllers\Tenant\ReportController;
use App\Http\Controllers\Tenant\SaleController;
use App\Http\Controllers\Tenant\SaleHistoryController;
use App\Http\Controllers\Tenant\SaleReturnController;
use App\Http\Controllers\Tenant\ShiftController;
use App\Http\Controllers\Tenant\ShiftRecapController;
use App\Http\Controllers\Tenant\ShiftReceiptController;
use App\Http\Controllers\Tenant\StockHistoryController;
use App\Http\Controllers\Tenant\StockOpnameController;
use App\Http\Controllers\Tenant\StoreSettingController;
use App\Http\Controllers\Tenant\UnitController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

/*
|--------------------------------------------------------------------------
| Store (tenant) routes
|--------------------------------------------------------------------------
|
| Every store has its own database. The store is identified by the snake_case
| store code in the URL: /t/{tenant}/... (e.g. /t/mitra_utama/cashier).
| These are web routes (session login, Inertia pages): the user must be logged
| in and allowed into that store.
|
*/

Route::prefix('t/{tenant}')
    ->middleware(['web', InitializeTenancyByPath::class, 'auth', 'store.access', 'shift.remember'])
    ->group(function () {
        Route::get('/', TenantDashboardController::class)->name('tenant.dashboard');

        Route::prefix('cashier')->name('tenant.cashier.')->middleware('permission:cashier.use')->group(function () {
            Route::get('/', [CashierController::class, 'index'])->name('index');
            Route::get('products', CashierProductController::class)->name('products');
            Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
            Route::post('shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
            Route::get('shifts/{shift}/report', [ShiftController::class, 'report'])->name('shifts.report');
            Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
            Route::get('receipts', [ShiftReceiptController::class, 'index'])->name('receipts');
            Route::post('receipts/{sale}/print', [ShiftReceiptController::class, 'print'])->name('receipts.print');
            Route::get('receipts/{sale}/sheet', [ShiftReceiptController::class, 'sheet'])->middleware('signed')->name('receipts.sheet');
            Route::get('receipts/{sale}/delivery', [CashierDeliveryNoteController::class, 'show'])->name('receipts.delivery');
            Route::post('receipts/{sale}/delivery-notes', [CashierDeliveryNoteController::class, 'store'])->name('receipts.delivery-notes.store');
            Route::post('receipts/{sale}/delivery-mode', [CashierDeliveryNoteController::class, 'changeToDelivery'])->name('receipts.delivery-mode');
            Route::post('delivery-notes/{delivery_note}/print', [CashierDeliveryNoteController::class, 'print'])->name('delivery-notes.print');
            Route::get('delivery-notes/{delivery_note}/sheet', [CashierDeliveryNoteController::class, 'sheet'])->middleware('signed')->name('delivery-notes.sheet');
            Route::get('returns/lookup', [CashierSaleReturnController::class, 'lookup'])->middleware('throttle:30,1')->name('returns.lookup');
            Route::get('receipts/{sale}/return', [CashierSaleReturnController::class, 'show'])->middleware('signed')->name('receipts.return');
            Route::post('receipts/{sale}/returns', [CashierSaleReturnController::class, 'store'])->middleware('signed')->name('receipts.returns.store');
            Route::get('returns/{sale_return}/sheet', [CashierSaleReturnController::class, 'sheet'])->name('returns.sheet');
            Route::get('cod', [CashierCodController::class, 'index'])->name('cod.index');
            Route::post('cod/{sale}/deliveries', [CashierCodController::class, 'delivered'])->name('cod.delivered');
            Route::post('cod/{sale}/voids', [CashierCodController::class, 'void'])->name('cod.void');
            Route::get('cod/settlements', [CashierCodController::class, 'settlements'])->middleware('permission:cod.receive,cod.approve')->name('cod.settlements');
            Route::post('cod/{sale}/settlements', [CashierCodController::class, 'settle'])->middleware('permission:cod.receive')->name('cod.settle');
            Route::post('cod/{sale}/settlements/approval', [CashierCodController::class, 'approve'])->middleware('permission:cod.approve')->name('cod.approve');
            Route::get('orders', [CashierOrderController::class, 'index'])->name('orders.index');
            Route::post('orders/{order}/arrivals', [CashierOrderController::class, 'ready'])->name('orders.ready');
            Route::post('orders/{order}/cancellations', [CashierOrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('orders/{order}/print', [CashierOrderController::class, 'print'])->name('orders.print');
            Route::get('orders/{order}/sheet', [CashierOrderController::class, 'sheet'])->middleware('signed')->name('orders.sheet');
            Route::get('expenses', [CashierExpenseController::class, 'index'])->name('expenses.index');
            Route::post('expenses', [CashierExpenseController::class, 'store'])->name('expenses.store');
        });

        Route::prefix('delivery-notes')->name('tenant.delivery-notes.')->group(function () {
            Route::middleware('permission:delivery_notes.manage')->group(function () {
                Route::get('create', [DeliveryNoteController::class, 'create'])->name('create');
                Route::get('sales', [DeliveryNoteController::class, 'sales'])->name('sales');
                Route::post('/', [DeliveryNoteController::class, 'store'])->name('store');
                Route::post('{delivery_note}/ship', [DeliveryNoteController::class, 'ship'])->name('ship');
                Route::post('{delivery_note}/complete', [DeliveryNoteController::class, 'complete'])->name('complete');
                Route::post('{delivery_note}/cancel', [DeliveryNoteController::class, 'cancel'])->name('cancel');
            });

            Route::middleware('permission:delivery_notes.view')->group(function () {
                Route::get('/', [DeliveryNoteController::class, 'index'])->name('index');
                Route::get('{delivery_note}', [DeliveryNoteController::class, 'show'])->name('show');
                Route::post('{delivery_note}/print', [DeliveryNoteController::class, 'print'])->name('print');
                Route::get('{delivery_note}/sheet', [DeliveryNoteController::class, 'sheet'])->middleware('signed')->name('sheet');
                Route::post('{delivery_note}/cod-settlement', [DeliveryNoteController::class, 'receiveCod'])->middleware('permission:cod.receive')->name('cod-settlement');
                Route::post('{delivery_note}/cod-settlement/approval', [DeliveryNoteController::class, 'approveCod'])->middleware('permission:cod.approve')->name('cod-settlement.approve');
            });
        });

        Route::prefix('shifts')->name('tenant.shifts.')->middleware('permission:shifts.review')->group(function () {
            Route::get('/', [ShiftRecapController::class, 'index'])->name('index');
            Route::get('{shift}', [ShiftRecapController::class, 'show'])->name('show');
            Route::get('{shift}/sheet', [ShiftRecapController::class, 'sheet'])->name('sheet');
            Route::post('{shift}/approve', [ShiftRecapController::class, 'approve'])->name('approve');
            Route::post('{shift}/reject', [ShiftRecapController::class, 'reject'])->name('reject');
        });

        Route::prefix('returns')->name('tenant.returns.')->middleware('permission:returns.manage')->group(function () {
            Route::get('/', [SaleReturnController::class, 'index'])->name('index');
            Route::get('create', [SaleReturnController::class, 'create'])->name('create');
            Route::get('sales', [SaleReturnController::class, 'sales'])->name('sales');
            Route::post('/', [SaleReturnController::class, 'store'])->name('store');
            Route::get('{sale_return}', [SaleReturnController::class, 'show'])->name('show');
            Route::post('{sale_return}/approve', [SaleReturnController::class, 'approve'])->name('approve');
            Route::post('{sale_return}/reject', [SaleReturnController::class, 'reject'])->name('reject');
            Route::get('{sale_return}/sheet', [SaleReturnController::class, 'sheet'])->name('sheet');
        });

        // A sheet is `signed` only when a POST first counts its print and hands out the address (receipts, orders, delivery notes); report sheets that anyone with this access may open again need no signature.
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('reports/sales', [ReportController::class, 'sales'])->name('tenant.reports.sales');
            Route::get('reports/sales/sheet', [ReportController::class, 'salesSheet'])->name('tenant.reports.sales.sheet');
            Route::get('reports/sales/detail-sheet', [ReportController::class, 'salesDetailSheet'])->name('tenant.reports.sales.detail-sheet');
            Route::get('reports/sales/detail-export', [ReportController::class, 'salesDetailExport'])->name('tenant.reports.sales.detail-export');
            Route::get('reports/stock', [ReportController::class, 'stock'])->name('tenant.reports.stock');
            Route::get('reports/stock/restock', [ReportController::class, 'restock'])->name('tenant.reports.stock.restock');
            Route::get('reports/stock/idle', [ReportController::class, 'idle'])->name('tenant.reports.stock.idle');
            Route::get('reports/stock/movements', [ReportController::class, 'movements'])->name('tenant.reports.stock.movements');
        });

        Route::prefix('daily-closings')->name('tenant.daily-closings.')->middleware('permission:daily_closings.manage')->group(function () {
            Route::get('/', [DailyClosingController::class, 'index'])->name('index');
            Route::get('{date}', [DailyClosingController::class, 'show'])->where('date', '\d{4}-\d{2}-\d{2}')->name('show');
            Route::post('{date}', [DailyClosingController::class, 'store'])->where('date', '\d{4}-\d{2}-\d{2}')->name('store');
            Route::post('{date}/reopenings', [DailyClosingController::class, 'reopen'])->where('date', '\d{4}-\d{2}-\d{2}')->name('reopen');
            Route::get('{date}/sheet', [DailyClosingController::class, 'sheet'])->where('date', '\d{4}-\d{2}-\d{2}')->name('sheet');
        });

        Route::prefix('expenses')->name('tenant.expenses.')->middleware('permission:expenses.manage')->group(function () {
            Route::get('/', [ExpenseController::class, 'index'])->name('index');
            Route::post('/', [ExpenseController::class, 'store'])->name('store');
            Route::post('categories', [ExpenseCategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [ExpenseCategoryController::class, 'update'])->name('categories.update');
            Route::post('{expense}/voids', [ExpenseController::class, 'void'])->name('void');
        });

        Route::get('sales', [SaleHistoryController::class, 'index'])->middleware('permission:sales.view')->name('tenant.sales.index');
        Route::get('sales/{sale}', [SaleHistoryController::class, 'show'])->middleware('permission:sales.view')->name('tenant.sales.show');
        Route::post('sales/{sale}/voids', [SaleHistoryController::class, 'void'])->middleware('permission:sales.void')->name('tenant.sales.void');
        Route::post('sales/{sale}/delivery-mode', [SaleHistoryController::class, 'changeToDelivery'])->middleware('permission:delivery_notes.manage')->name('tenant.sales.delivery-mode');
        Route::post('sales/{sale}/cod-settlement/approval', [SaleHistoryController::class, 'approveCodSettlement'])->middleware('permission:cod.approve')->name('tenant.sales.cod-settlement.approve');

        Route::get('products/search', ProductSearchController::class)->middleware('permission:stock_opnames.count,stock_opnames.post,stock_history.view')->name('tenant.products.search');

        Route::middleware('permission:products.manage')->group(function () {
            Route::post('products/bulk-prices/units', [BulkPriceController::class, 'units'])->name('tenant.bulk-prices.units');
            Route::post('products/bulk-prices/preview', [BulkPriceController::class, 'preview'])->name('tenant.bulk-prices.preview');
            Route::post('products/bulk-prices', [BulkPriceController::class, 'apply'])->name('tenant.bulk-prices.apply');
            Route::post('products/barcode-labels/products', [BarcodeLabelController::class, 'products'])->name('tenant.barcode-labels.products');
            Route::get('products/barcode-labels/sheet', [BarcodeLabelController::class, 'sheet'])->name('tenant.barcode-labels.sheet');
            Route::post('products/price-labels/units', [PriceLabelController::class, 'units'])->name('tenant.price-labels.units');
            Route::get('products/price-labels/sheet', [PriceLabelController::class, 'sheet'])->name('tenant.price-labels.sheet');
            Route::resource('products', ProductController::class)->except(['show'])->names('tenant.products');
        });

        Route::get('settings', [StoreSettingController::class, 'index'])->middleware('permission:settings.manage')->name('tenant.settings.index');
        Route::put('settings', [StoreSettingController::class, 'update'])->middleware('permission:settings.manage')->name('tenant.settings.update');
        // The item form adds units too, so whoever manages items may manage them.
        Route::resource('settings/units', UnitController::class)->only(['store', 'update', 'destroy'])->middleware('permission:settings.manage,products.manage')->names('tenant.units');

        Route::prefix('stock-opnames')->name('tenant.stock-opnames.')->scopeBindings()->group(function () {
            Route::middleware('permission:stock_opnames.count')->group(function () {
                Route::get('create', [StockOpnameController::class, 'create'])->name('create');
                Route::post('/', [StockOpnameController::class, 'store'])->name('store');
                Route::put('{stock_opname}/counts', [StockOpnameController::class, 'saveCounts'])->name('counts');
                Route::post('{stock_opname}/items', [StockOpnameController::class, 'addItem'])->name('items.store');
                Route::delete('{stock_opname}/items/{item}', [StockOpnameController::class, 'removeItem'])->name('items.destroy');
                Route::post('{stock_opname}/zero-uncounted', [StockOpnameController::class, 'countUncountedAsZero'])->name('zero-uncounted');
                Route::post('{stock_opname}/finish', [StockOpnameController::class, 'finish'])->name('finish');
                Route::post('{stock_opname}/cancel', [StockOpnameController::class, 'cancel'])->name('cancel');
            });

            Route::middleware('permission:stock_opnames.count,stock_opnames.post')->group(function () {
                Route::get('/', [StockOpnameController::class, 'index'])->name('index');
                Route::get('{stock_opname}', [StockOpnameController::class, 'show'])->withTrashed()->name('show');
                Route::post('{stock_opname}/reopen', [StockOpnameController::class, 'reopen'])->name('reopen');
            });

            Route::middleware('permission:stock_opnames.post')->group(function () {
                Route::put('{stock_opname}/review', [StockOpnameController::class, 'saveReview'])->name('review');
                Route::post('{stock_opname}/apply-reason', [StockOpnameController::class, 'applyReason'])->name('apply-reason');
                Route::post('{stock_opname}/post', [StockOpnameController::class, 'post'])->name('post');
            });
        });

        Route::get('stock-history', [StockHistoryController::class, 'index'])->middleware('permission:stock_history.view')->name('tenant.stock-history.index');
    });
