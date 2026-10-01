<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\HotspotSectionController;
use App\Http\Controllers\HotspotBrandingController;
use App\Http\Controllers\HotspotReportController;
use App\Http\Controllers\HotspotVoucherDocumentController;
use App\Http\Controllers\HotspotVoucherController;
use App\Http\Controllers\HotspotSellerController;
use Inertia\Inertia;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\RouterWireGuardController;
use App\Http\Controllers\HotspotPortalActionController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\IpRangeController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ClientRenewalController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\NotificationController;

/* Public homepage loaded from routes/public_reseller.php */

use App\Http\Controllers\DashboardController;

/*
 * MAIN_HOTSPOT_PUBLIC_MAC_RESET_V1
 *
 * Public captive-portal action.
 * Protected by router-specific HMAC token
 * plus application rate limiting.
 */
Route::post(
    'hotspot-portal/mac-reset/{router}/{token}',
    [
        HotspotPortalActionController::class,
        'resetMac',
    ]
)
    ->whereNumber(
        'router'
    )
    ->where(
        'token',
        '[a-f0-9]{64}'
    )
    ->withoutMiddleware([
        \App\Http\Middleware\SuperAdminPanelOnly::class,
        \App\Http\Middleware\ShareUnifiedFinance::class,
        \App\Http\Middleware\EnforceResellerAccess::class,
    ])
    ->name(
        'hotspot.portal.mac-reset'
    );

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


/*
 * RESELLER_ROUTER_WIREGUARD_V1
 */
Route::get(
    'routers/{router}/wireguard',
    [
        RouterWireGuardController::class,
        'show',
    ]
)->name(
    'routers.wireguard.show'
);

Route::post(
    'routers/{router}/wireguard',
    [
        RouterWireGuardController::class,
        'create',
    ]
)->name(
    'routers.wireguard.create'
);

Route::post(
    'routers/{router}/wireguard/check',
    [
        RouterWireGuardController::class,
        'refresh',
    ]
)->name(
    'routers.wireguard.check'
);

Route::post(
    'routers/{router}/wireguard/regenerate',
    [
        RouterWireGuardController::class,
        'rotate',
    ]
)->name(
    'routers.wireguard.rotate'
);

Route::post(
    'routers/{router}/wireguard/use-as-host',
    [
        RouterWireGuardController::class,
        'activateHost',
    ]
)->name(
    'routers.wireguard.activate-host'
);

Route::delete(
    'routers/{router}/wireguard',
    [
        RouterWireGuardController::class,
        'revoke',
    ]
)->name(
    'routers.wireguard.revoke'
);

/*
 * HOTSPOT_ROUTER_SETUP_WIZARD_PHASE1_V1
 *
 * Read-only RouterOS discovery page used before
 * MikroPanel applies any Hotspot configuration.
 */
Route::get(
    'routers/{router}/hotspot-setup',
    [
        RouterController::class,
        'hotspotSetup',
    ]
)->name(
    'routers.hotspot-setup'
);

/*
 * HOTSPOT_BRIDGE_SETUP_PHASE2_V2
 */
Route::post(
    'routers/{router}/hotspot-setup/bridge',
    [
        RouterController::class,
        'hotspotSetupBridge',
    ]
)->name(
    'routers.hotspot-setup.bridge'
);

/*
 * HOTSPOT_GATEWAY_STEP3_V1
 */
Route::post(
    'routers/{router}/hotspot-setup/gateway',
    [
        RouterController::class,
        'hotspotSetupGateway',
    ]
)->name(
    'routers.hotspot-setup.gateway'
);

/*
 * HOTSPOT_DHCP_STEP4_V1
 */
Route::post(
    'routers/{router}/hotspot-setup/dhcp',
    [
        RouterController::class,
        'hotspotSetupDhcp',
    ]
)->name(
    'routers.hotspot-setup.dhcp'
);

/*
 * HOTSPOT_WIZARD_FINAL_V1
 *
 * Steps 5-9 are completed in one safe operation:
 * Hotspot/Profile -> Login -> NAT -> Portal -> Validation.
 */
Route::post(
    'routers/{router}/hotspot-setup/finalize',
    [
        RouterController::class,
        'hotspotSetupFinalize',
    ]
)->name(
    'routers.hotspot-setup.finalize'
);

/*
 * MAIN_HOTSPOT_PORTAL_PACKAGE_V1
 */
Route::get(
    'routers/{router}/hotspot-portal',
    [
        RouterController::class,
        'downloadHotspotPortal',
    ]
)->name(
    'routers.hotspot-portal.download'
);


/*
 * RESELLER_VPN_FIRST_FLOW_V2
 *
 * VPN is created before Router registration.
 */
Route::get(
    '/reseller/mikrotik-vpn',
    [
        \App\Http\Controllers\Reseller\MikroTikVpnController::class,
        'index',
    ]
)->name(
    'reseller.mikrotik-vpn.index'
);

Route::post(
    '/reseller/mikrotik-vpn',
    [
        \App\Http\Controllers\Reseller\MikroTikVpnController::class,
        'store',
    ]
)->name(
    'reseller.mikrotik-vpn.store'
);

Route::post(
    '/reseller/mikrotik-vpn/{peer}/check',
    [
        \App\Http\Controllers\Reseller\MikroTikVpnController::class,
        'check',
    ]
)->name(
    'reseller.mikrotik-vpn.check'
);

Route::post(
    '/reseller/mikrotik-vpn/{peer}/regenerate',
    [
        \App\Http\Controllers\Reseller\MikroTikVpnController::class,
        'rotate',
    ]
)->name(
    'reseller.mikrotik-vpn.rotate'
);

Route::delete(
    '/reseller/mikrotik-vpn/{peer}',
    [
        \App\Http\Controllers\Reseller\MikroTikVpnController::class,
        'revoke',
    ]
)->name(
    'reseller.mikrotik-vpn.revoke'
);

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
        'clients/{client}/refund-preview',
        [
            \App\Http\Controllers\ClientRefundController::class,
            'preview',
        ]
    )->name('payments.refund.preview');

    Route::post(
        'clients/{client}/refund',
        [
            \App\Http\Controllers\ClientRefundController::class,
            'store',
        ]
    )->name('payments.refund.store');

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

    Route::post(
        '/clients/{client}/renew-all-devices',
        [
            \App\Http\Controllers\ClientBulkRenewalController::class,
            'store',
        ]
    )->name('clients.renew-all');



    Route::resource(
        'users',
        \App\Http\Controllers\UserManagementController::class
    )->except(['show']);


    /*
     * Hotspot module.
     * Access is protected by reseller tenancy,
     * Network Zone isolation and granular
     * panel permissions.
     */
    Route::prefix('hotspot')
        ->name('hotspot.')
        ->group(function () {

            /*
             * HOTSPOT_SELLER_LEDGER_V1
             */
            Route::get(
                'sellers',
                [
                    HotspotSellerController::class,
                    'index',
                ]
            )->name(
                'sellers.index'
            );

            Route::post(
                'sellers',
                [
                    HotspotSellerController::class,
                    'store',
                ]
            )->name(
                'sellers.store'
            );

            Route::put(
                'sellers/{seller}',
                [
                    HotspotSellerController::class,
                    'update',
                ]
            )->name(
                'sellers.update'
            );

            Route::post(
                'sellers/{seller}/collections',
                [
                    HotspotSellerController::class,
                    'collect',
                ]
            )->name(
                'sellers.collections.store'
            );

            Route::post(
                'seller-assignments/batch',
                [
                    HotspotSellerController::class,
                    'assignBatch',
                ]
            )->name(
                'sellers.assign-batch'
            );

            Route::post(
                'seller-assignments/voucher',
                [
                    HotspotSellerController::class,
                    'assignVoucher',
                ]
            )->name(
                'sellers.assign-voucher'
            );
            Route::get(
                '/',
                [
                    HotspotSectionController::class,
                    'dashboard',
                ]
            )->name('index');

            Route::get(
                'servers',
                [
                    HotspotSectionController::class,
                    'servers',
                ]
            )->name(
                'servers.index'
            );

            Route::get(
                'plans',
                [
                    HotspotSectionController::class,
                    'plans',
                ]
            )->name(
                'plans.index'
            );

            Route::get(
                'vouchers',
                [
                    HotspotSectionController::class,
                    'vouchers',
                ]
            )->name(
                'vouchers.index'
            );

            Route::get(
                'sessions',
                [
                    HotspotSectionController::class,
                    'sessions',
                ]
            )->name(
                'sessions.index'
            );

            Route::get(
                'billing',
                [
                    HotspotSectionController::class,
                    'billing',
                ]
            )->name(
                'billing.index'
            );

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


/*
|--------------------------------------------------------------------------
| Reseller MAC Client Migration
|--------------------------------------------------------------------------
*/
Route::middleware([
    'auth',
    'active.panel.user',
    \App\Http\Middleware\EnforceResellerAccess::class,
])
    ->prefix('reseller/mac-clients')
    ->name('reseller.mac-clients.')
    ->group(function () {
        Route::get(
            '/migration',
            [
                \App\Http\Controllers\Reseller\ClientMigrationController::class,
                'index',
            ]
        )->name('migration');

        Route::get(
            '/template',
            [
                \App\Http\Controllers\Reseller\ClientMigrationController::class,
                'template',
            ]
        )->name('template');

        Route::get(
            '/export',
            [
                \App\Http\Controllers\Reseller\ClientMigrationController::class,
                'export',
            ]
        )->name('export');

        Route::post(
            '/import',
            [
                \App\Http\Controllers\Reseller\ClientMigrationController::class,
                'import',
            ]
        )->name('import');

        Route::get(
            '/form-fields',
            [
                \App\Http\Controllers\Reseller\ClientFormFieldController::class,
                'index',
            ]
        )->name('form-fields.index');

        Route::post(
            '/form-fields',
            [
                \App\Http\Controllers\Reseller\ClientFormFieldController::class,
                'store',
            ]
        )->name('form-fields.store');

        Route::patch(
            '/form-fields/{clientCustomField}/toggle',
            [
                \App\Http\Controllers\Reseller\ClientFormFieldController::class,
                'toggle',
            ]
        )->name('form-fields.toggle');

        Route::delete(
            '/form-fields/{clientCustomField}',
            [
                \App\Http\Controllers\Reseller\ClientFormFieldController::class,
                'destroy',
            ]
        )->name('form-fields.destroy');
    });

Route::post(
    '/clients/identity-scan',
    \App\Http\Controllers\ClientIdentityScanController::class
)
    ->middleware([
        'auth',
        'active.panel.user',
    ])
    ->name('clients.identity-scan');


Route::get(
    '/clients/{client}/identity-image/{kind}',
    \App\Http\Controllers\ClientIdentityImageController::class
)
    ->middleware([
        'auth',
        'active.panel.user',
    ])
    ->name('clients.identity-image');


Route::get(
    '/clients/identity-scan-preview/{token}',
    [
        \App\Http\Controllers\ClientIdentityImageController::class,
        'preview',
    ]
)
    ->middleware([
        'auth',
        'active.panel.user',
    ])
    ->whereUuid('token')
    ->name('clients.identity-scan-preview');


Route::get(
    '/clients/identity-face-preview/{token}',
    [
        \App\Http\Controllers\ClientIdentityImageController::class,
        'previewFace',
    ]
)
    ->middleware([
        'auth',
        'active.panel.user',
    ])
    ->whereUuid('token')
    ->name('clients.identity-face-preview');

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

require __DIR__.'/super_admin.php';
require __DIR__.'/reseller.php';


/*
|--------------------------------------------------------------------------
| Reseller MAC Client POS
|--------------------------------------------------------------------------
*/
\Illuminate\Support\Facades\Route::middleware([
    'auth',
    \App\Http\Middleware\EnforceResellerAccess::class,
])->get(
    '/reseller/mac-pos',
    \App\Http\Controllers\Reseller\MacClientPosController::class
)->name('reseller.mac-pos');

/*
 * MAC_POS_DEVICE_TRANSFER_ROUTE_V2
 */
\Illuminate\Support\Facades\Route::middleware([
    'auth',
    \App\Http\Middleware\EnforceResellerAccess::class,
])->post(
    '/reseller/mac-pos/transfer-device',
    [
        \App\Http\Controllers\Reseller\MacClientPosController::class,
        'transferDevice',
    ]
)->name(
    'reseller.mac-pos.transfer-device'
);



/*
|--------------------------------------------------------------------------
| Universal Windows Scanner Agent
|--------------------------------------------------------------------------
|
| Authenticated browser sessions receive a short-lived signed token.
| The local Windows Agent validates the signature before allowing a
| panel origin to pair/re-pair.
|
*/

Route::get(
    '/scanner/agent-token',
    \App\Http\Controllers\ScannerAgentTokenController::class,
)
    ->middleware('auth')
    ->name('scanner.agent-token');

require __DIR__.'/manager_cash.php';

require __DIR__.'/public_reseller.php';

/*
 * HOTEL_HOTSPOT_ROUTE_INCLUDE_V1
 */
require __DIR__.'/hotel.php';

/*
 * HOTEL_COMMERCIAL_SUPERADMIN_V1
 */
Route::middleware([
    'auth',
])
    ->prefix(
        'super-admin/hotel-hotspot'
    )
    ->name(
        'superadmin.hotel.'
    )
    ->group(
        function (): void {
            Route::get(
                'commercial',
                [
                    \App\Http\Controllers\SuperAdmin\Hotel\CommercialController::class,
                    'index',
                ]
            )->name(
                'commercial.index'
            );

            Route::post(
                'commercial/invoices/{invoice}/payment',
                [
                    \App\Http\Controllers\SuperAdmin\Hotel\CommercialController::class,
                    'payment',
                ]
            )->name(
                'commercial.payment'
            );

            Route::post(
                'commercial/hotels/{hotel}/renew',
                [
                    \App\Http\Controllers\SuperAdmin\Hotel\CommercialController::class,
                    'renew',
                ]
            )->name(
                'commercial.renew'
            );
        }
    );

require __DIR__.'/compliance.php';

/* RENTAL_CUSTOMER_STATUS_PORTAL_V1 */
\Illuminate\Support\Facades\Route::get(
    '/rental/status/{token}',
    [\App\Http\Controllers\PublicRentalStatusController::class, 'show']
)->where('token', '[a-f0-9]{64}')->name('rental.status');

\Illuminate\Support\Facades\Route::post(
    '/rental/status/{token}/ticket',
    [\App\Http\Controllers\PublicRentalStatusController::class, 'ticket']
)->where('token', '[a-f0-9]{64}')->middleware('throttle:10,1')->name('rental.status.ticket');
