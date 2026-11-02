<?php

declare(strict_types=1);

use App\Http\Controllers\Tenant\CashierController;
use App\Http\Controllers\Tenant\CashierDeliveryNoteController;
use App\Http\Controllers\Tenant\CashierExpenseController;
use App\Http\Controllers\Tenant\CashierProductController;
use App\Http\Controllers\Tenant\CashierSaleReturnController;
use App\Http\Controllers\Tenant\DashboardController as TenantDashboardController;
use App\Http\Controllers\Tenant\DeliveryNoteController;
use App\Http\Controllers\Tenant\ExpenseCategoryController;
use App\Http\Controllers\Tenant\ExpenseController;
use App\Http\Controllers\Tenant\ProductController;
use App\Http\Controllers\Tenant\ProductSearchController;
use App\Http\Controllers\Tenant\SaleController;
use App\Http\Controllers\Tenant\SaleHistoryController;
use App\Http\Controllers\Tenant\SaleReturnController;
use App\Http\Controllers\Tenant\ShiftController;
use App\Http\Controllers\Tenant\ShiftRecapController;
use App\Http\Controllers\Tenant\ShiftReceiptController;
use App\Http\Controllers\Tenant\StockHistoryController;
use App\Http\Controllers\Tenant\StockOpnameController;
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

        Route::prefix('cashier')->name('tenant.cashier.')->group(function () {
            Route::get('/', [CashierController::class, 'index'])->name('index');
            Route::get('products', CashierProductController::class)->name('products');
            Route::post('shifts', [ShiftController::class, 'store'])->name('shifts.store');
            Route::post('shifts/{shift}/close', [ShiftController::class, 'close'])->name('shifts.close');
            Route::post('sales', [SaleController::class, 'store'])->name('sales.store');
            Route::get('receipts', [ShiftReceiptController::class, 'index'])->name('receipts');
            Route::post('receipts/{sale}/print', [ShiftReceiptController::class, 'print'])->name('receipts.print');
            Route::get('receipts/{sale}/sheet', [ShiftReceiptController::class, 'sheet'])->middleware('signed')->name('receipts.sheet');
            Route::get('receipts/{sale}/delivery', [CashierDeliveryNoteController::class, 'show'])->name('receipts.delivery');
            Route::post('receipts/{sale}/delivery-notes', [CashierDeliveryNoteController::class, 'store'])->name('receipts.delivery-notes.store');
            Route::post('delivery-notes/{delivery_note}/print', [CashierDeliveryNoteController::class, 'print'])->name('delivery-notes.print');
            Route::get('delivery-notes/{delivery_note}/sheet', [CashierDeliveryNoteController::class, 'sheet'])->middleware('signed')->name('delivery-notes.sheet');
            Route::get('returns/lookup', [CashierSaleReturnController::class, 'lookup'])->middleware('throttle:30,1')->name('returns.lookup');
            Route::get('receipts/{sale}/return', [CashierSaleReturnController::class, 'show'])->middleware('signed')->name('receipts.return');
            Route::post('receipts/{sale}/returns', [CashierSaleReturnController::class, 'store'])->middleware('signed')->name('receipts.returns.store');
            Route::get('returns/{sale_return}/sheet', [CashierSaleReturnController::class, 'sheet'])->name('returns.sheet');
            Route::get('expenses', [CashierExpenseController::class, 'index'])->name('expenses.index');
            Route::post('expenses', [CashierExpenseController::class, 'store'])->name('expenses.store');
        });

        Route::middleware('store.admin')->group(function () {
            Route::prefix('delivery-notes')->name('tenant.delivery-notes.')->group(function () {
                Route::get('/', [DeliveryNoteController::class, 'index'])->name('index');
                Route::get('create', [DeliveryNoteController::class, 'create'])->name('create');
                Route::get('sales', [DeliveryNoteController::class, 'sales'])->name('sales');
                Route::post('/', [DeliveryNoteController::class, 'store'])->name('store');
                Route::get('{delivery_note}', [DeliveryNoteController::class, 'show'])->name('show');
                Route::post('{delivery_note}/ship', [DeliveryNoteController::class, 'ship'])->name('ship');
                Route::post('{delivery_note}/complete', [DeliveryNoteController::class, 'complete'])->name('complete');
                Route::post('{delivery_note}/cancel', [DeliveryNoteController::class, 'cancel'])->name('cancel');
                Route::post('{delivery_note}/print', [DeliveryNoteController::class, 'print'])->name('print');
                Route::get('{delivery_note}/sheet', [DeliveryNoteController::class, 'sheet'])->middleware('signed')->name('sheet');
            });

            Route::prefix('shifts')->name('tenant.shifts.')->group(function () {
                Route::get('/', [ShiftRecapController::class, 'index'])->name('index');
                Route::get('{shift}', [ShiftRecapController::class, 'show'])->name('show');
                Route::post('{shift}/approve', [ShiftRecapController::class, 'approve'])->name('approve');
                Route::post('{shift}/reject', [ShiftRecapController::class, 'reject'])->name('reject');
                Route::post('{shift}/follow-up', [ShiftRecapController::class, 'followUp'])->name('follow-up');
            });

            Route::prefix('returns')->name('tenant.returns.')->group(function () {
                Route::get('/', [SaleReturnController::class, 'index'])->name('index');
                Route::get('create', [SaleReturnController::class, 'create'])->name('create');
                Route::get('sales', [SaleReturnController::class, 'sales'])->name('sales');
                Route::post('/', [SaleReturnController::class, 'store'])->name('store');
                Route::get('{sale_return}', [SaleReturnController::class, 'show'])->name('show');
                Route::post('{sale_return}/approve', [SaleReturnController::class, 'approve'])->name('approve');
                Route::post('{sale_return}/reject', [SaleReturnController::class, 'reject'])->name('reject');
                Route::get('{sale_return}/sheet', [SaleReturnController::class, 'sheet'])->name('sheet');
            });

            Route::prefix('expenses')->name('tenant.expenses.')->group(function () {
                Route::get('/', [ExpenseController::class, 'index'])->name('index');
                Route::post('/', [ExpenseController::class, 'store'])->name('store');
                Route::post('categories', [ExpenseCategoryController::class, 'store'])->name('categories.store');
                Route::put('categories/{category}', [ExpenseCategoryController::class, 'update'])->name('categories.update');
                Route::post('{expense}/voids', [ExpenseController::class, 'void'])->name('void');
            });

            Route::get('sales', [SaleHistoryController::class, 'index'])->name('tenant.sales.index');
            Route::get('sales/{sale}', [SaleHistoryController::class, 'show'])->name('tenant.sales.show');

            Route::get('products/search', ProductSearchController::class)->name('tenant.products.search');
            Route::resource('products', ProductController::class)->except(['show'])->names('tenant.products');
            Route::resource('settings/units', UnitController::class)->only(['index', 'store', 'update', 'destroy'])->names('tenant.units');

            Route::prefix('stock-opnames')->name('tenant.stock-opnames.')->scopeBindings()->group(function () {
                Route::get('/', [StockOpnameController::class, 'index'])->name('index');
                Route::get('create', [StockOpnameController::class, 'create'])->name('create');
                Route::post('/', [StockOpnameController::class, 'store'])->name('store');
                Route::get('{stock_opname}', [StockOpnameController::class, 'show'])->withTrashed()->name('show');
                Route::post('{stock_opname}/start', [StockOpnameController::class, 'start'])->name('start');
                Route::put('{stock_opname}/counts', [StockOpnameController::class, 'saveCounts'])->name('counts');
                Route::post('{stock_opname}/items', [StockOpnameController::class, 'addItem'])->name('items.store');
                Route::delete('{stock_opname}/items/{item}', [StockOpnameController::class, 'removeItem'])->name('items.destroy');
                Route::post('{stock_opname}/zero-uncounted', [StockOpnameController::class, 'countUncountedAsZero'])->name('zero-uncounted');
                Route::post('{stock_opname}/finish', [StockOpnameController::class, 'finish'])->name('finish');
                Route::post('{stock_opname}/reopen', [StockOpnameController::class, 'reopen'])->name('reopen');
                Route::post('{stock_opname}/post', [StockOpnameController::class, 'post'])->name('post');
                Route::post('{stock_opname}/cancel', [StockOpnameController::class, 'cancel'])->name('cancel');
            });

            Route::get('stock-history', [StockHistoryController::class, 'index'])->name('tenant.stock-history.index');
        });
    });
