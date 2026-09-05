<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\HotspotBrandingController;
use App\Http\Controllers\HotspotReportController;
use App\Http\Controllers\HotspotVoucherDocumentController;
use App\Http\Controllers\HotspotVoucherController;
use Inertia\Inertia;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\IpRangeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ClientRenewalController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\NotificationController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

use App\Http\Controllers\DashboardController;

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware([
        'auth',
        'verified',
        'active.panel.user',
        'panel.permission',
    ])
    ->name('dashboard');

Route::middleware([
    'auth',
    'active.panel.user',
    'panel.permission',
])->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
Route::post(
    'routers/{router}/ping',
    [RouterController::class, 'ping']
)->name('routers.ping');

Route::post(
    'routers/{router}/sync',
    [RouterController::class, 'sync']
)->name('routers.sync');

    Route::resource('routers', RouterController::class);
    Route::resource('packages', PackageController::class);
Route::get(
    'invoices/print-all',
    [
        \App\Http\Controllers\InvoiceDocumentController::class,
        'printAll',
    ]
)->name('invoices.print-all');

Route::get(
    'invoices/download-all',
    [
        \App\Http\Controllers\InvoiceDocumentController::class,
        'downloadAll',
    ]
)->name('invoices.download-all');

Route::get(
    'invoices/{invoice}/print',
    [
        \App\Http\Controllers\InvoiceDocumentController::class,
        'print',
    ]
)->name('invoices.print');

Route::get(
    'invoices/{invoice}/download',
    [
        \App\Http\Controllers\InvoiceDocumentController::class,
        'download',
    ]
)->name('invoices.download');

    Route::get(
        'clients/{client}/invoices/print',
        [
            \App\Http\Controllers\InvoiceDocumentController::class,
            'printClient',
        ]
    )->name('clients.invoices.print');

    Route::get(
        'clients/{client}/invoices/download',
        [
            \App\Http\Controllers\InvoiceDocumentController::class,
            'downloadClient',
        ]
    )->name('clients.invoices.download');

    Route::resource('clients', ClientController::class);
    Route::resource('invoices', \App\Http\Controllers\InvoiceController::class)
    ->except(['show']);
    Route::resource('ip-ranges', IpRangeController::class);

    Route::post('/clients/{client}/suspend', [ClientController::class, 'suspend'])
        ->name('clients.suspend');

    Route::post('/clients/{client}/unsuspend', [ClientController::class, 'unsuspend'])
        ->name('clients.unsuspend');
    Route::resource('payments', \App\Http\Controllers\PaymentController::class)
    ->except(['show', 'edit', 'update']);

    Route::get(
        'accounting/print',
        [AccountingController::class, 'print']
    )->name('accounting.print');

    Route::get(
        'accounting/download',
        [AccountingController::class, 'download']
    )->name('accounting.download');

    Route::get(
        'accounting',
        [AccountingController::class, 'index']
    )->name('accounting.index');


    Route::get(
        'settings',
        [SettingController::class, 'index']
    )->name('settings.index');

    Route::post(
        'settings',
        [SettingController::class, 'update']
    )->name('settings.update');

    Route::delete(
        'settings/logo',
        [SettingController::class, 'removeLogo']
    )->name('settings.logo.destroy');

    Route::post(
        'settings/clear-cache',
        [SettingController::class, 'clearCache']
    )->name('settings.cache.clear');

    Route::get(
        'settings/export',
        [SettingController::class, 'export']
    )->name('settings.export');

    Route::get(
        'notifications',
        [NotificationController::class, 'index']
    )->name('notifications.index');

    Route::post(
        'notifications/read-all',
        [NotificationController::class, 'readAll']
    )->name('notifications.read-all');

    Route::delete(
        'notifications/clear-read',
        [NotificationController::class, 'clearRead']
    )->name('notifications.clear-read');

    Route::post(
        'notifications/{notification}/read',
        [NotificationController::class, 'read']
    )->name('notifications.read');

    Route::resource('expenses', ExpenseController::class);
    Route::post(
    '/clients/{client}/renew',
    [ClientRenewalController::class, 'store']
)->name('clients.renew');


    Route::resource(
        'users',
        \App\Http\Controllers\UserManagementController::class
    )->except(['show']);


    /*
     * Hotspot module.
     * Controller currently enforces admin-only
     * access until granular Hotspot permissions
     * are installed in the next phase.
     */
    Route::prefix('hotspot')
        ->name('hotspot.')
        ->group(function () {
            Route::get(
                '/',
                [
                    HotspotController::class,
                    'index',
                ]
            )->name('index');

            Route::get(
                'reports',
                [
                    HotspotReportController::class,
                    'index',
                ]
            )->name(
                'reports.index'
            );

            Route::get(
                'reports/csv',
                [
                    HotspotReportController::class,
                    'csv',
                ]
            )->name(
                'reports.csv'
            );

            Route::get(
                'reports/pdf',
                [
                    HotspotReportController::class,
                    'pdf',
                ]
            )->name(
                'reports.pdf'
            );

            Route::get(
                'branding',
                [
                    HotspotBrandingController::class,
                    'index',
                ]
            )->name(
                'branding.index'
            );

            Route::put(
                'branding',
                [
                    HotspotBrandingController::class,
                    'update',
                ]
            )->name(
                'branding.update'
            );

            Route::get(
                'branding/portal',
                [
                    HotspotBrandingController::class,
                    'portal',
                ]
            )->name(
                'branding.portal'
            );

            Route::post(
                'discover',
                [
                    HotspotController::class,
                    'discover',
                ]
            )->name('discover');

            Route::post(
                'servers/{server}/sync',
                [
                    HotspotController::class,
                    'syncServer',
                ]
            )->name(
                'servers.sync'
            );

            Route::post(
                'plans',
                [
                    HotspotController::class,
                    'storePlan',
                ]
            )->name(
                'plans.store'
            );

            Route::put(
                'plans/{plan}',
                [
                    HotspotController::class,
                    'updatePlan',
                ]
            )->name(
                'plans.update'
            );

            Route::delete(
                'plans/{plan}',
                [
                    HotspotController::class,
                    'destroyPlan',
                ]
            )->name(
                'plans.destroy'
            );

            Route::post(
                'vouchers/generate',
                [
                    HotspotController::class,
                    'generateVouchers',
                ]
            )->name(
                'vouchers.generate'
            );

            Route::get(
                'vouchers/{voucher}',
                [
                    HotspotVoucherController::class,
                    'show',
                ]
            )->name(
                'vouchers.show'
            );

            Route::post(
                'vouchers/{voucher}/renew',
                [
                    HotspotVoucherController::class,
                    'renew',
                ]
            )->name(
                'vouchers.renew'
            );

            Route::post(
                'vouchers/{voucher}/suspend',
                [
                    HotspotVoucherController::class,
                    'suspend',
                ]
            )->name(
                'vouchers.suspend'
            );

            Route::post(
                'vouchers/{voucher}/activate',
                [
                    HotspotVoucherController::class,
                    'activate',
                ]
            )->name(
                'vouchers.activate'
            );

            Route::put(
                'vouchers/{voucher}/mac',
                [
                    HotspotVoucherController::class,
                    'updateMac',
                ]
            )->name(
                'vouchers.mac'
            );

            Route::delete(
                'vouchers/{voucher}/archive',
                [
                    HotspotVoucherController::class,
                    'archive',
                ]
            )->name(
                'vouchers.archive'
            );

            Route::get(
                'vouchers/{voucher}/print',
                [
                    HotspotVoucherDocumentController::class,
                    'printVoucher',
                ]
            )->name(
                'vouchers.print'
            );

            Route::get(
                'vouchers/{voucher}/pdf',
                [
                    HotspotVoucherDocumentController::class,
                    'downloadVoucher',
                ]
            )->name(
                'vouchers.pdf'
            );

            Route::get(
                'batches',
                [
                    HotspotVoucherController::class,
                    'batches',
                ]
            )->name(
                'batches.index'
            );

            Route::get(
                'batches/{batch}/print',
                [
                    HotspotVoucherDocumentController::class,
                    'printBatch',
                ]
            )->name(
                'batches.print'
            );

            Route::get(
                'batches/{batch}/pdf',
                [
                    HotspotVoucherDocumentController::class,
                    'downloadBatch',
                ]
            )->name(
                'batches.pdf'
            );

            Route::post(
                'vouchers/{voucher}/sell',
                [
                    HotspotController::class,
                    'sellVoucher',
                ]
            )->name(
                'vouchers.sell'
            );

            Route::post(
                'invoices/{invoice}/pay',
                [
                    HotspotController::class,
                    'receiveInvoicePayment',
                ]
            )->name(
                'invoices.pay'
            );

            Route::post(
                'sessions/{session}/disconnect',
                [
                    HotspotController::class,
                    'disconnectSession',
                ]
            )->name(
                'sessions.disconnect'
            );
        });

});
require __DIR__.'/auth.php';


/*
|--------------------------------------------------------------------------
| CLIENT_FORM_BUILDER_ROUTES
|--------------------------------------------------------------------------
| Dynamic Client Information / Form Builder.
| Core MikroTik and billing fields remain outside this builder.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('settings')
    ->group(function () {
        Route::get(
            '/client-form-builder',
            [
                \App\Http\Controllers\ClientCustomFieldController::class,
                'index',
            ]
        )->name(
            'settings.client-form-builder.index'
        );

        Route::post(
            '/client-form-builder',
            [
                \App\Http\Controllers\ClientCustomFieldController::class,
                'store',
            ]
        )->name(
            'settings.client-form-builder.store'
        );

        Route::put(
            '/client-form-builder/{clientCustomField}',
            [
                \App\Http\Controllers\ClientCustomFieldController::class,
                'update',
            ]
        )->name(
            'settings.client-form-builder.update'
        );

        Route::patch(
            '/client-form-builder/{clientCustomField}/toggle',
            [
                \App\Http\Controllers\ClientCustomFieldController::class,
                'toggle',
            ]
        )->name(
            'settings.client-form-builder.toggle'
        );

        Route::patch(
            '/client-form-builder/{clientCustomField}/order',
            [
                \App\Http\Controllers\ClientCustomFieldController::class,
                'order',
            ]
        )->name(
            'settings.client-form-builder.order'
        );

        Route::delete(
            '/client-form-builder/{clientCustomField}',
            [
                \App\Http\Controllers\ClientCustomFieldController::class,
                'destroy',
            ]
        )->name(
            'settings.client-form-builder.destroy'
        );
    });


/*
|--------------------------------------------------------------------------
| CLIENT_CUSTOM_FIELD_DATA_ROUTE
|--------------------------------------------------------------------------
| Read-only field definitions and saved client values.
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->get(
        '/client-custom-fields/data',
        [
            \App\Http\Controllers\ClientCustomFieldController::class,
            'data',
        ]
    )
    ->name(
        'client-custom-fields.data'
    );


/*
|--------------------------------------------------------------------------
| CLIENT_CUSTOM_FIELD_LIST_DATA_ROUTE
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->get(
        '/client-custom-fields/list-data',
        [
            \App\Http\Controllers\ClientCustomFieldController::class,
            'listData',
        ]
    )
    ->name(
        'client-custom-fields.list-data'
    );

/* MIKROPANEL_PERMISSION_ROUTE_HARDENING_START */

/*
|--------------------------------------------------------------------------
| MikroPanel Permission Route Hardening
|--------------------------------------------------------------------------
|
| Some features were added after the original route group.
| This final pass ensures every current panel module receives:
|
|   auth
|   active.panel.user
|   panel.permission
|
| even when a route was registered outside the older protected group.
|
*/

$mikropanelProtectedRoute = static function (
    ?string $routeName
): bool {
    if (!$routeName) {
        return false;
    }

    if ($routeName === 'dashboard') {
        return true;
    }

    foreach (
        [
            'dashboard.',
            'clients.',
            'routers.',
            'packages.',
            'ip-ranges.',
            'invoices.',
            'payments.',
            'expenses.',
            'accounting.',
            'notifications.',
            'notification.',
            'settings.',
            'users.',
            'client-custom-fields.',
        ]
        as $prefix
    ) {
        if (
            str_starts_with(
                $routeName,
                $prefix
            )
        ) {
            return true;
        }
    }

    return false;
};

foreach (
    Route::getRoutes()
    as $mikropanelRoute
) {
    $mikropanelRouteName =
        $mikropanelRoute->getName();

    if (
        !$mikropanelProtectedRoute(
            $mikropanelRouteName
        )
    ) {
        continue;
    }

    $currentMiddleware =
        $mikropanelRoute->middleware();

    foreach (
        [
            'auth',
            'active.panel.user',
            'panel.permission',
        ]
        as $requiredMiddleware
    ) {
        if (
            !in_array(
                $requiredMiddleware,
                $currentMiddleware,
                true
            )
        ) {
            $mikropanelRoute->middleware(
                $requiredMiddleware
            );

            $currentMiddleware[] =
                $requiredMiddleware;
        }
    }
}

unset(
    $mikropanelProtectedRoute,
    $mikropanelRoute,
    $mikropanelRouteName,
    $currentMiddleware,
    $requiredMiddleware
);

/* MIKROPANEL_PERMISSION_ROUTE_HARDENING_END */
