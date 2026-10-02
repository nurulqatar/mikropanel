<?php

use App\Http\Controllers\Compliance\AuthController;
use App\Http\Controllers\Compliance\BridgeController;
use App\Http\Controllers\Compliance\RetentionController;
use App\Http\Controllers\Compliance\StorageController;
use App\Http\Controllers\Compliance\CollectorApiController;
use App\Http\Controllers\Compliance\CollectorController;
use App\Http\Controllers\Compliance\DashboardController;
use App\Http\Controllers\Compliance\FilteringController;
use App\Http\Controllers\Compliance\InvestigationController;
use App\Http\Controllers\Compliance\NetworkController;
use App\Http\Controllers\Compliance\RouterController;
use App\Http\Controllers\Compliance\RouterRuntimeController;
use App\Http\Controllers\SuperAdmin\Compliance\DashboardController as SuperAdminComplianceDashboardController;
use App\Http\Middleware\Compliance\AuthenticateComplianceCollector;
use App\Http\Middleware\Compliance\EnsureComplianceSession;
use App\Http\Middleware\Compliance\EnsureComplianceSuperAdmin;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NETWORK_COMPLIANCE_SAME_PANEL_BRIDGE_V1
|--------------------------------------------------------------------------
*/

Route::get(
    '/reseller/compliance',
    [BridgeController::class, 'reseller']
)
    ->middleware('auth')
    ->name('reseller.compliance.enter');

Route::get(
    '/hotel/compliance',
    [BridgeController::class, 'hotel']
)->name('hotel.compliance.enter');

/*
|--------------------------------------------------------------------------
| Standalone Compliance Portal
|--------------------------------------------------------------------------
*/

Route::get(
    '/compliance/login',
    [
        AuthController::class,
        'create',
    ]
)->name('compliance.login');

Route::post(
    '/compliance/login',
    [
        AuthController::class,
        'store',
    ]
)->name('compliance.login.store');

Route::middleware(
    EnsureComplianceSession::class
)
    ->prefix('compliance')
    ->name('compliance.')
    ->group(
        function (): void {
            Route::get(
                '/',
                DashboardController::class
            )->name('dashboard');

            Route::post(
                '/logout',
                [
                    AuthController::class,
                    'destroy',
                ]
            )->name('logout');

            Route::get(
                '/networks',
                [
                    NetworkController::class,
                    'index',
                ]
            )->name(
                'networks.index'
            );

            Route::post(
                '/networks',
                [
                    NetworkController::class,
                    'store',
                ]
            )->name(
                'networks.store'
            );

            Route::get(
                '/routers',
                [
                    RouterController::class,
                    'index',
                ]
            )->name(
                'routers.index'
            );

            Route::post(
                '/routers',
                [
                    RouterController::class,
                    'store',
                ]
            )->name(
                'routers.store'
            );

            Route::get(
                '/collectors',
                [
                    CollectorController::class,
                    'index',
                ]
            )->name(
                'collectors.index'
            );

            Route::post(
                '/collectors',
                [
                    CollectorController::class,
                    'store',
                ]
            )->name(
                'collectors.store'
            );

            Route::get(
                '/investigation',
                [
                    InvestigationController::class,
                    'index',
                ]
            )->name(
                'investigation.index'
            );

            Route::get(
                '/filtering',
                [
                    FilteringController::class,
                    'index',
                ]
            )->name(
                'filtering.index'
            );

            Route::post(
                '/filtering/rules',
                [
                    FilteringController::class,
                    'storeRule',
                ]
            )->name(
                'filtering.rules.store'
            );

            Route::post(
                '/filtering/signatures',
                [
                    FilteringController::class,
                    'storeSignature',
                ]
            )->name(
                'filtering.signatures.store'
            );

            Route::post(
                '/filtering/deploy',
                [
                    FilteringController::class,
                    'deploy',
                ]
            )->name(
                'filtering.deploy'
            );

            Route::post(
                '/routers/{router}/runtime-test',
                [
                    RouterRuntimeController::class,
                    'test',
                ]
            )->name(
                'routers.runtime-test'
            );

            Route::post(
                '/routers/{router}/wan-interface',
                [
                    RouterRuntimeController::class,
                    'setWan',
                ]
            )->name(
                'routers.wan-interface'
            );

            Route::post(
                '/routers/{router}/configure-logging',
                [
                    RouterRuntimeController::class,
                    'configureLogging',
                ]
            )->name(
                'routers.configure-logging'
            );


            Route::get(
                '/storage',
                [StorageController::class, 'index']
            )->name('storage.index');

            Route::post(
                '/storage',
                [StorageController::class, 'store']
            )->name('storage.store');

            Route::post(
                '/storage/{storageTarget}/test',
                [StorageController::class, 'test']
            )->name('storage.test');

            Route::delete(
                '/storage/{storageTarget}',
                [StorageController::class, 'destroy']
            )->name('storage.destroy');

            Route::get(
                '/retention',
                [RetentionController::class, 'index']
            )->name('retention.index');

            Route::post(
                '/retention/policy',
                [RetentionController::class, 'storePolicy']
            )->name('retention.policy.store');

            Route::post(
                '/retention/holds',
                [RetentionController::class, 'storeHold']
            )->name('retention.holds.store');

            Route::post(
                '/retention/holds/{hold}/release',
                [RetentionController::class, 'releaseHold']
            )->name('retention.holds.release');

            Route::get(
                '/investigation/csv',
                [InvestigationController::class, 'csv']
            )->name('investigation.csv');
        }
    );

/*
|--------------------------------------------------------------------------
| Super Admin Compliance Control
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    EnsureComplianceSuperAdmin::class,
])
    ->prefix(
        'super-admin/compliance'
    )
    ->name(
        'superadmin.compliance.'
    )
    ->group(
        function (): void {
            Route::get(
                '/',
                [
                    SuperAdminComplianceDashboardController::class,
                    'index',
                ]
            )->name(
                'dashboard'
            );

            Route::post(
                '/plans',
                [
                    SuperAdminComplianceDashboardController::class,
                    'storePlan',
                ]
            )->name(
                'plans.store'
            );

            Route::post(
                '/organizations',
                [
                    SuperAdminComplianceDashboardController::class,
                    'storeOrganization',
                ]
            )->name(
                'organizations.store'
            );

            Route::post(
                '/subscriptions',
                [
                    SuperAdminComplianceDashboardController::class,
                    'storeSubscription',
                ]
            )->name(
                'subscriptions.store'
            );

            Route::post(
                '/access-grants',
                [
                    SuperAdminComplianceDashboardController::class,
                    'storeGrant',
                ]
            )->name(
                'grants.store'
            );

            /* NETWORK_COMPLIANCE_COMMERCIAL_RENTAL_V1 */
            Route::put('/plans/{plan}', [SuperAdminComplianceDashboardController::class, 'updatePlan'])->name('plans.update');
            Route::post('/rentals/standalone', [SuperAdminComplianceDashboardController::class, 'rentStandalone'])->name('rentals.standalone');
            Route::post('/rentals/addon', [SuperAdminComplianceDashboardController::class, 'rentAddon'])->name('rentals.addon');
            /* COMPLIANCE_RENTAL_PLAN_CHANGE_V2 */
            Route::put('/rentals/{rental}/plan', [SuperAdminComplianceDashboardController::class, 'changeRentalPlan'])->name('rentals.change-plan');
            Route::post('/rentals/{rental}/renew', [SuperAdminComplianceDashboardController::class, 'renewRental'])->name('rentals.renew');
            Route::post('/rentals/{rental}/suspend', [SuperAdminComplianceDashboardController::class, 'suspendRental'])->name('rentals.suspend');
            Route::post('/rentals/{rental}/reactivate', [SuperAdminComplianceDashboardController::class, 'reactivateRental'])->name('rentals.reactivate');
            Route::post('/organizations/{organization}/reset-password', [SuperAdminComplianceDashboardController::class, 'resetPassword'])->name('organizations.reset-password');
        }
    );

/*
|--------------------------------------------------------------------------
| NETWORK_COMPLIANCE_COLLECTOR_API_V1
|--------------------------------------------------------------------------
|
| Metadata only. No payload/body/password capture.
| Collector UUID + bearer token are mandatory.
|
*/

Route::prefix(
    'api/compliance/v1'
)
    ->withoutMiddleware([
        ValidateCsrfToken::class,
    ])
    ->middleware([
        'throttle:240,1',
        AuthenticateComplianceCollector::class,
    ])
    ->group(
        function (): void {
            Route::post(
                '/heartbeat',
                [
                    CollectorApiController::class,
                    'heartbeat',
                ]
            );

            Route::post(
                '/batch',
                [
                    CollectorApiController::class,
                    'batch',
                ]
            );
        }
    );
