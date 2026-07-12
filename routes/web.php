<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\StockInController;
use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\DepartmentController as InventoryDepartmentController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\StockIssueController;
use App\Http\Controllers\Inventory\MoveController;
use App\Http\Controllers\Inventory\UomLibraryController;
use App\Http\Controllers\Inventory\StockCheckController;
use App\Http\Controllers\Purchase\PurchaseController;
use App\Http\Controllers\Purchase\VendorController as PurchaseVendorController;
use App\Http\Controllers\Purchase\OrderController as PurchaseOrderController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Pos\PosController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Employee\AttendanceController;
use App\Http\Controllers\Employee\DepartmentController;
use App\Http\Controllers\Employee\DesignationController;
use App\Http\Controllers\Employee\EmployeeController;
use App\Http\Controllers\Employee\EmployeeLoanController;
use App\Http\Controllers\Employee\EmployeeStaffCategoryController;
use App\Http\Controllers\Employee\PayrollController;
use App\Http\Controllers\Hr\HrController;
use App\Http\Controllers\Hr\LeaveRequestController;
use App\Http\Controllers\Expense\ExpenseController;
use App\Http\Controllers\Expense\ExpenseCategoryController;
use App\Http\Controllers\Accounts\AccountsController;
use App\Http\Controllers\Accounts\ChartOfAccountController;
use App\Http\Controllers\Accounts\JournalEntryController;
use App\Http\Controllers\Accounts\AccountReportController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CreditBookController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\GuestRoom\GuestRoomDashboardController;
use App\Http\Controllers\GuestRoom\RoomCategoryController;
use App\Http\Controllers\GuestRoom\RoomController as GuestRoomRoomController;
use App\Http\Controllers\GuestRoom\RoomRateController;
use App\Http\Controllers\GuestRoom\BookingController as GuestRoomBookingController;
use App\Http\Controllers\GuestRoom\BillingController as GuestRoomBillingController;
use App\Http\Controllers\GuestRoom\GuestRoomReportController;
use App\Http\Controllers\GuestRoom\RoomCleaningController;
use App\Http\Controllers\CustomFormReportsController;
use App\Http\Controllers\Manufacturing\ManufacturingController;
use App\Http\Controllers\Manufacturing\BomController as ManufacturingBomController;
use App\Http\Controllers\Manufacturing\OrderController as ManufacturingOrderController;
use App\Http\Controllers\CompanyUpdateController;
use App\Http\Controllers\ManualSystemUpdateController;
use App\Http\Controllers\DatabaseBackupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Auth\PasswordResetRequestController as GuestPasswordResetRequestController;
use App\Http\Controllers\Auth\TotpVerificationController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\Admin\PasswordResetRequestController as AdminPasswordResetRequestController;
use App\Http\Controllers\SyncStatusController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Auth::routes(['register' => false, 'reset' => false]);

Route::get('/logout', function () {
    if (auth()->check()) {
        auth()->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    return redirect()->route('login');
})->name('logout.get');

Route::middleware('guest')->group(function () {
    Route::get('/login/verify-totp', [TotpVerificationController::class, 'show'])->name('login.verify-totp');
    Route::post('/login/verify-totp', [TotpVerificationController::class, 'verify'])->name('login.verify-totp.submit');
    Route::get('/request-password-reset', [GuestPasswordResetRequestController::class, 'create'])->name('password-reset-request.create');
    Route::post('/request-password-reset', [GuestPasswordResetRequestController::class, 'store'])->name('password-reset-request.store');
});

Route::middleware(['auth', 'employee', 'passwordChanged'])->group(function () {
    Route::get('/sync/status', [SyncStatusController::class, 'status'])->name('sync.status');
    Route::post('/sync/push', [SyncStatusController::class, 'push'])->name('sync.push');

    Route::middleware(['tenant', 'company', 'companyTenantReady'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/two-factor/setup', [TwoFactorController::class, 'setup'])->name('profile.two-factor.setup');
    Route::get('/profile/two-factor/reset', [TwoFactorController::class, 'resetSetup'])->name('profile.two-factor.reset');
    Route::post('/profile/two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('profile.two-factor.confirm');
    Route::get('/profile/two-factor/recovery', [TwoFactorController::class, 'recovery'])->name('profile.two-factor.recovery');
    Route::post('/profile/two-factor/disable', [TwoFactorController::class, 'disable'])->name('profile.two-factor.disable');

    Route::get('/updates', [CompanyUpdateController::class, 'tenantIndex'])->name('updates.index');
    Route::post('/updates/install/{companyUpdate}', [CompanyUpdateController::class, 'installFeature'])->name('updates.install');

    Route::middleware('role:super_admin')->group(function () {
        Route::get('/platform/manual-update', [ManualSystemUpdateController::class, 'index'])->name('platform.manual-update.index');
        Route::post('/platform/manual-update', [ManualSystemUpdateController::class, 'store'])->name('platform.manual-update.store');
        Route::get('/platform/updates', [CompanyUpdateController::class, 'platformUpdatesIndex'])->name('platform.updates.index');
        Route::get('/platform/updates/create', [CompanyUpdateController::class, 'platformUpdatesCreate'])->name('platform.updates.create');
        Route::post('/platform/updates', [CompanyUpdateController::class, 'platformUpdatesStore'])->name('platform.updates.store');
        Route::get('/platform/updates/{update}/edit', [CompanyUpdateController::class, 'platformUpdatesEdit'])->name('platform.updates.edit');
        Route::put('/platform/updates/{update}', [CompanyUpdateController::class, 'platformUpdatesUpdate'])->name('platform.updates.update');
        Route::delete('/platform/updates/{update}', [CompanyUpdateController::class, 'platformUpdatesDestroy'])->name('platform.updates.destroy');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics')->middleware('moduleAccess');

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index')->middleware('role:company_admin,super_admin');

    Route::prefix('admin')->name('admin.')->middleware('role:company_admin,super_admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::get('/password-reset-requests', [AdminPasswordResetRequestController::class, 'index'])->name('password-reset-requests.index');
        Route::post('/password-reset-requests/{passwordResetRequest}/reset', [AdminPasswordResetRequestController::class, 'reset'])->name('password-reset-requests.reset');
    });

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::middleware('moduleAccess')->group(function () {
        Route::get('/', [InventoryController::class, 'index'])->name('index');
        Route::get('/low-stock', [InventoryController::class, 'lowStock'])->name('low-stock');
        Route::resource('products', ProductController::class)->except(['show']);
        Route::post('products/{product}/toggle-star', [ProductController::class, 'toggleStar'])->name('products.toggle-star');
        Route::put('products/{product}/purchase-lines/{line}', [ProductController::class, 'updatePurchaseHistoryLine'])
            ->name('products.purchase-lines.update')
            ->middleware('role:super_admin');
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('departments', InventoryDepartmentController::class)->except(['show']);
        Route::get('issues', [StockIssueController::class, 'index'])->name('issues.index');
        Route::get('issues/warehouse-stock/print', [StockIssueController::class, 'warehouseStockPrint'])->name('issues.warehouse-stock-print');
        Route::get('issues/create', [StockIssueController::class, 'create'])->name('issues.create');
        Route::post('issues', [StockIssueController::class, 'store'])->name('issues.store');
        Route::resource('moves', MoveController::class)->only(['index', 'create', 'store']);
        Route::get('/stock-in', [StockInController::class, 'index'])->name('stock-in.index');
        Route::post('/stock-in/{order}/receive', [StockInController::class, 'receive'])->name('stock-in.receive');
        Route::get('/uom-library', [UomLibraryController::class, 'index'])->name('uom-library.index');
        Route::post('/uom-library/units', [UomLibraryController::class, 'storeUnit'])->name('uom-library.units.store');
        Route::delete('/uom-library/units/{unit}', [UomLibraryController::class, 'destroyUnit'])->name('uom-library.units.destroy');
        Route::post('/uom-library/conversions', [UomLibraryController::class, 'storeConversion'])->name('uom-library.conversions.store');
        Route::delete('/uom-library/conversions/{conversion}', [UomLibraryController::class, 'destroyConversion'])->name('uom-library.conversions.destroy');

        Route::post('stock-check/{stockCheck}/submit', [StockCheckController::class, 'submit'])->name('stock-check.submit');
        Route::post('stock-check/{stockCheck}/approve', [StockCheckController::class, 'approve'])
            ->name('stock-check.approve')
            ->middleware('role:company_admin,super_admin,admin');
        Route::post('stock-check/{stockCheck}/reject', [StockCheckController::class, 'reject'])
            ->name('stock-check.reject')
            ->middleware('role:company_admin,super_admin,admin');
        Route::resource('stock-check', StockCheckController::class)->parameters(['stock-check' => 'stockCheck']);
        });
    });

    Route::prefix('manufacturing')->name('manufacturing.')->group(function () {
        Route::middleware('moduleAccess')->group(function () {
            Route::get('/', [ManufacturingController::class, 'index'])->name('index');
            Route::resource('boms', ManufacturingBomController::class);
            Route::post('orders/{order}/complete', [ManufacturingOrderController::class, 'complete'])->name('orders.complete');
            Route::resource('orders', ManufacturingOrderController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        });
    });

    // Guest Rooms module disabled for Signature
    Route::any('/guest-rooms/{any?}', fn () => abort(404))->where('any', '.*');

    Route::prefix('maintenance')->name('maintenance.')->middleware('moduleAccess')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index'])->name('index');
        Route::post('/items', [MaintenanceController::class, 'storeItem'])->name('items.store');
        Route::post('/demands', [MaintenanceController::class, 'storeDemand'])->name('demands.store');
        Route::get('/demands/{demand}/edit', [MaintenanceController::class, 'editDemand'])->name('demands.edit');
        Route::put('/demands/{demand}', [MaintenanceController::class, 'updateDemand'])->name('demands.update');
        Route::post('/demands/{demand}/receive', [MaintenanceController::class, 'receiveDemand'])->name('demands.receive');
        Route::post('/issues', [MaintenanceController::class, 'issue'])->name('issues.store');
        Route::post('/locations', [MaintenanceController::class, 'storeLocation'])->name('locations.store');
        Route::post('/categories', [MaintenanceController::class, 'storeCategory'])->name('categories.store');
        Route::post('/opening-stock', [MaintenanceController::class, 'setOpeningStock'])->name('opening-stock.set');
        Route::delete('/purge', [MaintenanceController::class, 'purgeAll'])->name('purge');
    });

    Route::prefix('custom-forms')->name('custom-forms.')->middleware('moduleAccess')->group(function () {
        Route::get('/', [CustomFormReportsController::class, 'index'])->name('index');
        Route::post('/templates', [CustomFormReportsController::class, 'storeTemplate'])->name('templates.store');
        Route::get('/templates/{template}/edit', [CustomFormReportsController::class, 'editTemplate'])->name('templates.edit');
        Route::put('/templates/{template}', [CustomFormReportsController::class, 'updateTemplate'])->name('templates.update');
        Route::delete('/templates/{template}', [CustomFormReportsController::class, 'destroyTemplate'])->name('templates.destroy');
        Route::get('/templates/{template}/fill', [CustomFormReportsController::class, 'fill'])->name('fill');
        Route::post('/templates/{template}/fill', [CustomFormReportsController::class, 'saveFill'])->name('fill.save');
        Route::get('/reports/{report}', [CustomFormReportsController::class, 'showReport'])->name('reports.show');
        Route::delete('/reports/{report}', [CustomFormReportsController::class, 'destroyReport'])->name('reports.destroy');
    });

    Route::prefix('purchase')->name('purchase.')->group(function () {
        Route::middleware('moduleAccess')->group(function () {
        Route::get('/', [PurchaseController::class, 'index'])->name('index');
        Route::resource('vendors', PurchaseVendorController::class)->except(['show']);
        Route::post('orders/quick-product', [PurchaseOrderController::class, 'quickAddProduct'])->name('orders.quick-product');
        Route::put('orders/quick-product/{product}', [PurchaseOrderController::class, 'quickEditProduct'])->name('orders.quick-product.update');
        Route::resource('orders', PurchaseOrderController::class)->except(['show', 'destroy']);
        Route::post('orders/{order}/confirm', [PurchaseOrderController::class, 'confirm'])->name('orders.confirm');
        Route::post('orders/{order}/pay', [PurchaseOrderController::class, 'markPaid'])->name('orders.pay');
        });
    });

    Route::redirect('/pos', '/restaurant-pos');
    Route::redirect('/pos/resume/{order}', '/restaurant-pos/resume/{order}');
    Route::redirect('/pos/receipt/{order}', '/restaurant-pos/receipt/{order}');

    Route::prefix('restaurant-pos')->name('restaurant-pos.')->group(function () {
        Route::middleware('moduleAccess')->group(function () {
            Route::get('/', [PosController::class, 'restaurant'])->name('index');
            Route::get('/sync', [PosController::class, 'sync'])->name('sync');
            Route::post('/session/open', [PosController::class, 'openSession'])->name('session.open');
            Route::post('/session/close', [PosController::class, 'closeSession'])->name('session.close');
            Route::post('/cash-movement', [PosController::class, 'addCashMovement'])->name('cash-movement');
            Route::post('/checkout', [PosController::class, 'checkout'])->name('checkout');
            Route::post('/hold', [PosController::class, 'hold'])->name('hold');
            Route::delete('/hold/{orderId}', [PosController::class, 'discardHeld'])->whereNumber('orderId')->name('hold.discard');
            Route::get('/resume/{order}', [PosController::class, 'resume'])->name('resume');
            Route::post('/reopen/{order}', [PosController::class, 'reopenPaidBill'])->name('reopen');
            Route::get('/receipt/{order}/unpaid', [PosController::class, 'unpaidReceipt'])->name('receipt.unpaid');
            Route::get('/kitchen/{order}', [PosController::class, 'kitchenSlip'])->name('kitchen');
            Route::get('/receipt/{order}', [PosController::class, 'receipt'])->name('receipt');
        });
    });

    Route::prefix('order-taker')->name('order-taker.')->middleware('moduleAccess')->group(function () {
        Route::get('/', [\App\Http\Controllers\OrderTaker\OrderTakerController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\OrderTaker\OrderTakerController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\OrderTaker\OrderTakerController::class, 'store'])->name('store');
        Route::get('/{order}/edit', [\App\Http\Controllers\OrderTaker\OrderTakerController::class, 'edit'])->name('edit');
        Route::put('/{order}', [\App\Http\Controllers\OrderTaker\OrderTakerController::class, 'update'])->name('update');
    });

    Route::prefix('kitchen')->name('kitchen.')->middleware('moduleAccess')->group(function () {
        Route::get('/', [\App\Http\Controllers\Kitchen\KitchenController::class, 'index'])->name('index');
        Route::get('/board', [\App\Http\Controllers\Kitchen\KitchenController::class, 'board'])->name('board');
        Route::get('/summary', [\App\Http\Controllers\Kitchen\KitchenController::class, 'summary'])->name('summary');
        Route::get('/today-consumption', [\App\Http\Controllers\Kitchen\KitchenController::class, 'todayConsumption'])->name('today-consumption');
        Route::post('/position/{order}', [\App\Http\Controllers\Kitchen\KitchenController::class, 'position'])->name('position');
        Route::post('/reorder', [\App\Http\Controllers\Kitchen\KitchenController::class, 'reorder'])->name('reorder');
        Route::post('/{order}/complete', [\App\Http\Controllers\Kitchen\KitchenController::class, 'complete'])->name('complete');
        Route::post('/{order}/status/{step}', [\App\Http\Controllers\Kitchen\KitchenController::class, 'status'])->name('status');
        Route::post('/{order}/items/{item}/serve', [\App\Http\Controllers\Kitchen\KitchenController::class, 'serveItem'])->name('item.serve');
    });

    Route::prefix('order-status')->name('order-status.')->middleware('moduleAccess')->group(function () {
        Route::get('/', [\App\Http\Controllers\OrderStatus\OrderStatusController::class, 'index'])->name('index');
        Route::get('/board', [\App\Http\Controllers\OrderStatus\OrderStatusController::class, 'board'])->name('board');
    });

    Route::prefix('hr')->name('hr.')->middleware('moduleAccess')->group(function () {
        Route::get('/', [HrController::class, 'index'])->name('index');

        Route::get('/leave', [LeaveRequestController::class, 'index'])->name('leave.index');
        Route::get('/leave/create', [LeaveRequestController::class, 'create'])->name('leave.create');
        Route::post('/leave', [LeaveRequestController::class, 'store'])->name('leave.store');
        Route::get('/leave/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('leave.show');
        Route::post('/leave/{leaveRequest}/approve', [LeaveRequestController::class, 'approve'])->name('leave.approve');
        Route::post('/leave/{leaveRequest}/reject', [LeaveRequestController::class, 'reject'])->name('leave.reject');
        Route::delete('/leave/{leaveRequest}', [LeaveRequestController::class, 'destroy'])->name('leave.destroy');
    });

    Route::prefix('employees')->name('employees.')->group(function () {
        Route::middleware('moduleAccess')->group(function () {
            Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
            Route::post('/attendance/grid', [AttendanceController::class, 'saveGrid'])->name('attendance.grid');

            Route::get('/', [EmployeeController::class, 'index'])->name('index');
            Route::get('/create', [EmployeeController::class, 'create'])->name('create');
            Route::post('/', [EmployeeController::class, 'store'])->name('store');

            Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
            Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create');
            Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
            Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
            Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
            Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('departments.destroy');

            Route::get('/designations', [DesignationController::class, 'index'])->name('designations.index');
            Route::get('/designations/create', [DesignationController::class, 'create'])->name('designations.create');
            Route::post('/designations', [DesignationController::class, 'store'])->name('designations.store');
            Route::get('/designations/{designation}/edit', [DesignationController::class, 'edit'])->name('designations.edit');
            Route::put('/designations/{designation}', [DesignationController::class, 'update'])->name('designations.update');
            Route::delete('/designations/{designation}', [DesignationController::class, 'destroy'])->name('designations.destroy');

            Route::get('/staff-categories', [EmployeeStaffCategoryController::class, 'index'])->name('staff-categories.index');
            Route::post('/staff-categories/{staffCategory}/assign', [EmployeeStaffCategoryController::class, 'assign'])->name('staff-categories.assign');
            Route::delete('/staff-categories/{staffCategory}/employees/{employee}', [EmployeeStaffCategoryController::class, 'removeEmployee'])->name('staff-categories.remove-employee');

            Route::get('/loans', [EmployeeLoanController::class, 'index'])->name('loans.index');
            Route::get('/loans/create', [EmployeeLoanController::class, 'create'])->name('loans.create');
            Route::post('/loans', [EmployeeLoanController::class, 'store'])->name('loans.store');
            Route::get('/loans/{loan}/edit', [EmployeeLoanController::class, 'edit'])->name('loans.edit');
            Route::put('/loans/{loan}', [EmployeeLoanController::class, 'update'])->name('loans.update');
            Route::delete('/loans/{loan}', [EmployeeLoanController::class, 'destroy'])->name('loans.destroy');

            Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
            Route::get('/payroll/print', [PayrollController::class, 'printSalaryRecord'])->name('payroll.print');
            Route::get('/payroll/{payrollEntry}/slip', [PayrollController::class, 'printSlip'])->name('payroll.slip');
            Route::post('/payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
            Route::put('/payroll/{payrollEntry}', [PayrollController::class, 'update'])->name('payroll.update');
            Route::post('/payroll/{payrollEntry}/paid', [PayrollController::class, 'markPaid'])->name('payroll.paid');

            Route::get('/{employee}/edit', [EmployeeController::class, 'edit'])->name('edit');
            Route::put('/{employee}', [EmployeeController::class, 'update'])->name('update');
            Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->name('destroy');
        });

        Route::middleware('role:company_admin,super_admin')->group(function () {
            Route::post('/{employee}/reset-password', [EmployeeController::class, 'resetPassword'])->name('reset-password');
        });
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    // Reports (Report Builder disabled for Signature)
    Route::prefix('reports')->name('reports.')->middleware('moduleAccess')->group(function () {
        Route::get('/',          [ReportsController::class, 'index'])     ->name('index');
        Route::get('/pos-bills', [ReportsController::class, 'posBills'])   ->name('pos-bills');
        Route::delete('/pos-bills/{order}', [PosController::class, 'destroyPaidBill'])
            ->middleware('role:super_admin')
            ->name('pos-bills.destroy');
        Route::get('/summary',   [ReportsController::class, 'summary'])   ->name('summary');
        Route::get('/sales',     [ReportsController::class, 'sales'])     ->name('sales');
        Route::get('/purchases', [ReportsController::class, 'purchases']) ->name('purchases');
        Route::get('/purchases/print', [ReportsController::class, 'purchasesPrint'])->name('purchases.print');
        Route::get('/inventory', [ReportsController::class, 'inventory']) ->name('inventory');
        Route::get('/inventory/products/print', [ReportsController::class, 'inventoryProductsPrint'])->name('inventory.print');
        Route::get('/issue-stock', [ReportsController::class, 'issueStock'])->name('issue-stock');
        Route::get('/issue-stock/print', [ReportsController::class, 'issueStockPrint'])->name('issue-stock.print');
        Route::get('/employees', [ReportsController::class, 'employees']) ->name('employees');
    });

    // Contacts
    Route::prefix('contacts')->name('contacts.')->middleware('moduleAccess')->group(function () {
        Route::get('/',                [ContactController::class, 'index'])  ->name('index');
        Route::get('/search',          [ContactController::class, 'search']) ->name('search');
        Route::post('/categories',     [ContactController::class, 'storeCategory'])->name('categories.store');
        Route::delete('/categories/{slug}', [ContactController::class, 'destroyCategory'])->name('categories.destroy');
        Route::get('/create',          [ContactController::class, 'create']) ->name('create');
        Route::post('/',               [ContactController::class, 'store'])  ->name('store');
        Route::get('/{contact}',       [ContactController::class, 'show'])   ->name('show');
        Route::get('/{contact}/edit',  [ContactController::class, 'edit'])   ->name('edit');
        Route::put('/{contact}',       [ContactController::class, 'update']) ->name('update');
        Route::delete('/{contact}',    [ContactController::class, 'destroy'])->name('destroy');
    });

    // Credit Book
    Route::prefix('credit-book')->name('credit-book.')->middleware('moduleAccess')->group(function () {
        Route::get('/',               [CreditBookController::class, 'index'])  ->name('index');
        Route::get('/pos-sale/{order}', [CreditBookController::class, 'showPosSale'])->name('pos-sale');
        Route::post('/',              [CreditBookController::class, 'store'])  ->name('store');
        Route::delete('/{entry}',     [CreditBookController::class, 'destroy'])->name('destroy');
    });

    // Calendar
    Route::prefix('calendar')->name('calendar.')->middleware('moduleAccess')->group(function () {
        Route::get('/',              [CalendarController::class, 'index'])  ->name('index');
        Route::get('/feed',          [CalendarController::class, 'feed'])   ->name('feed');
        Route::post('/',             [CalendarController::class, 'store'])  ->name('store');
        Route::get('/{calendar}',    [CalendarController::class, 'show'])   ->name('show');
        Route::put('/{calendar}',    [CalendarController::class, 'update']) ->name('update');
        Route::delete('/{calendar}', [CalendarController::class, 'destroy'])->name('destroy');
    });

    // Accounts
    Route::prefix('accounts')->name('accounts.')->middleware('moduleAccess')->group(function () {
        Route::get('/', [AccountsController::class, 'index'])->name('index');

        Route::get('/chart-of-accounts', [ChartOfAccountController::class, 'index'])->name('chart-of-accounts.index');
        Route::get('/chart-of-accounts/create', [ChartOfAccountController::class, 'create'])->name('chart-of-accounts.create');
        Route::post('/chart-of-accounts', [ChartOfAccountController::class, 'store'])->name('chart-of-accounts.store');
        Route::get('/chart-of-accounts/{chartOfAccount}/edit', [ChartOfAccountController::class, 'edit'])->name('chart-of-accounts.edit');
        Route::put('/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'update'])->name('chart-of-accounts.update');
        Route::delete('/chart-of-accounts/{chartOfAccount}', [ChartOfAccountController::class, 'destroy'])->name('chart-of-accounts.destroy');

        Route::get('/journal-entries', [JournalEntryController::class, 'index'])->name('journal-entries.index');
        Route::get('/journal-entries/create', [JournalEntryController::class, 'create'])->name('journal-entries.create');
        Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('journal-entries.store');
        Route::get('/journal-entries/{journalEntry}', [JournalEntryController::class, 'show'])->name('journal-entries.show');
        Route::get('/journal-entries/{journalEntry}/edit', [JournalEntryController::class, 'edit'])->name('journal-entries.edit');
        Route::put('/journal-entries/{journalEntry}', [JournalEntryController::class, 'update'])->name('journal-entries.update');
        Route::delete('/journal-entries/{journalEntry}', [JournalEntryController::class, 'destroy'])->name('journal-entries.destroy');
        Route::post('/journal-entries/{journalEntry}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');

        Route::get('/reports/trial-balance', [AccountReportController::class, 'trialBalance'])->name('reports.trial-balance');
    });

    // Expenses
    Route::prefix('expenses')->name('expenses.')->middleware('moduleAccess')->group(function () {
        Route::get('/',           [ExpenseController::class, 'index'])   ->name('index');
        Route::get('/create',     [ExpenseController::class, 'create'])  ->name('create');
        Route::post('/',          [ExpenseController::class, 'store'])   ->name('store');
        Route::get('/{expense}',  [ExpenseController::class, 'show'])    ->name('show');
        Route::get('/{expense}/edit', [ExpenseController::class, 'edit'])->name('edit');
        Route::put('/{expense}',  [ExpenseController::class, 'update'])  ->name('update');
        Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
        // Workflow
        Route::post('/{expense}/submit',   [ExpenseController::class, 'submit'])   ->name('submit');
        Route::post('/{expense}/approve',  [ExpenseController::class, 'approve'])  ->name('approve');
        Route::post('/{expense}/refuse',   [ExpenseController::class, 'refuse'])   ->name('refuse');
        Route::post('/{expense}/mark-paid',[ExpenseController::class, 'markPaid']) ->name('markPaid');
        // Categories (admin only)
        Route::get('/categories/list',         [ExpenseCategoryController::class, 'index'])  ->name('categories.index')  ->middleware('role:company_admin,super_admin');
        Route::get('/categories/create',       [ExpenseCategoryController::class, 'create']) ->name('categories.create') ->middleware('role:company_admin,super_admin');
        Route::post('/categories',             [ExpenseCategoryController::class, 'store'])  ->name('categories.store')  ->middleware('role:company_admin,super_admin');
        Route::get('/categories/{category}/edit', [ExpenseCategoryController::class, 'edit'])->name('categories.edit')   ->middleware('role:company_admin,super_admin');
        Route::put('/categories/{category}',   [ExpenseCategoryController::class, 'update']) ->name('categories.update') ->middleware('role:company_admin,super_admin');
        Route::delete('/categories/{category}',[ExpenseCategoryController::class, 'destroy'])->name('categories.destroy')->middleware('role:company_admin,super_admin');
    });

    // Settings (company admin / super admin in context)
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index')->middleware('role:company_admin,super_admin');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update')->middleware('role:company_admin,super_admin');
    Route::post('/settings/pos-tables', [SettingsController::class, 'storePosTable'])->name('settings.pos-tables.store')->middleware('role:company_admin,super_admin');
    Route::delete('/settings/pos-tables/{posTable}', [SettingsController::class, 'destroyPosTable'])->name('settings.pos-tables.destroy')->middleware('role:company_admin,super_admin');
    Route::post('/settings/database-backup', [DatabaseBackupController::class, 'download'])->name('settings.database-backup')->middleware('role:company_admin,super_admin');

    Route::get('/admin', function () {
        return view('dashboard.admin');
    })->middleware('role:company_admin,super_admin')->name('admin');

    Route::get('/home', fn () => redirect()->route('dashboard'))->name('home');
    });
});
